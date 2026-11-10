<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/TimeTrackingHelpers.php';

function logManual(User $user, Task $task, array $data): TestResponse
{
    return test()->actingAs($user)->postJson("/api/v1/tasks/{$task->code}/time-entries", [
        'date' => today('America/Argentina/Buenos_Aires')->subDay()->toDateString(), 'from' => '09:00', 'to' => '11:00',
        'comment' => 'Reunión con el cliente', ...$data,
    ]);
}

it('loads forgotten hours with a mandatory note, in the person timezone (RF-05.09)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 10 * 3600);

    $entry = logManual($dev, $task, [])->assertCreated()->json('data');
    expect($entry['elapsed_seconds'])->toBe(7200)->and($entry['source'])->toBe('manual')
        ->and($entry['started_at'])->toContain('T12:00:00'); // 09:00 Buenos Aires = 12:00 UTC
    expect($project->fresh()->consumed_seconds)->toBe(7200);

    logManual($dev, $task, ['comment' => ''])->assertUnprocessable();
});

it('QA-06: rejects manual hours that exceed the bag, whole (R-4)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);

    logManual($dev, $task, [])->assertUnprocessable()->assertJsonPath('data.code', 'manual_exceeds_bag');
    expect(TimeEntry::query()->count())->toBe(0);
});

it('limits manual hours to the configured window unless time.manage (P-06, CA-11.03)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev, $admin], 50 * 3600);
    $tenDaysAgo = today('America/Argentina/Buenos_Aires')->subDays(10)->toDateString();

    logManual($dev, $task, ['date' => $tenDaysAgo])->assertUnprocessable()->assertJsonPath('data.code', 'outside_manual_window');
    logManual($admin, $task, ['date' => $tenDaysAgo])->assertCreated();

    $this->actingAs($admin)->putJson('/api/v1/company', ['name' => 'NexoSmart', 'currency' => 'ARS', 'timezone' => 'America/Argentina/Buenos_Aires', 'settings' => ['manual_entry_days_back' => 3]]);
    logManual($dev->fresh(), $task, ['date' => today('America/Argentina/Buenos_Aires')->subDays(5)->toDateString()])->assertUnprocessable();
    expect(TimeEntry::query()->count())->toBe(1); // existing entries are not revalidated
});

it('lets people correct their own entries and Admin anyone, audited (RF-05.15, RNF-07)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $other = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev, $other], 10 * 3600);
    $id = logManual($dev, $task, [])->json('data.id');
    $date = today('America/Argentina/Buenos_Aires')->subDay()->toDateString();

    $this->actingAs($dev)->putJson("/api/v1/time-entries/{$id}", ['date' => $date, 'from' => '09:00', 'to' => '10:00', 'comment' => 'Reunión corta con el cliente'])
        ->assertOk()->assertJsonPath('data.elapsed_seconds', 3600);
    expect($project->fresh()->consumed_seconds)->toBe(3600);

    $this->actingAs($other)->putJson("/api/v1/time-entries/{$id}", ['comment' => 'No es mío pero edito'])->assertNotFound();
    $this->actingAs($admin)->deleteJson("/api/v1/time-entries/{$id}")->assertOk();
    expect($project->fresh()->consumed_seconds)->toBe(0);

    $events = $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/activity")->json('data.*.event');
    expect($events)->toContain('time.corrected', 'time.deleted');
});

it('shows "Mi semana" grouped by local day with comments (RF-05.12, RF-05.18)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 10 * 3600);
    logManual($dev, $task, []);
    $yesterday = today('America/Argentina/Buenos_Aires')->subDay();

    $sheet = $this->actingAs($dev)->getJson('/api/v1/me/timesheet?from='.$yesterday->copy()->subDays(2)->toDateString().'&to='.$yesterday->toDateString())
        ->assertOk()->json('data');

    expect($sheet['total_seconds'])->toBe(7200)
        ->and($sheet['days'])->toHaveCount(3)
        ->and($sheet['days'][2]['entries'][0]['comment'])->toBe('Reunión con el cliente');
});

it('handles extension requests: one click to ask, approve loads hours (RF-05.08)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 0);

    $id = $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/extension-requests", ['message' => 'Falta el footer'])->assertCreated()->json('data.id');
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/extension-requests")->assertJsonPath('data.id', $id);
    $this->actingAs($dev)->getJson('/api/v1/extension-requests')->assertForbidden();

    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    $this->actingAs($manager)->postJson("/api/v1/extension-requests/{$id}/approve", ['seconds' => 7200, 'reason' => 'Ampliación footer'])
        ->assertOk()->assertJsonPath('data.status', 'approved');
    expect($project->fresh()->allocated_seconds)->toBe(7200);
    $this->actingAs($manager)->postJson("/api/v1/extension-requests/{$id}/reject")->assertUnprocessable();
});
