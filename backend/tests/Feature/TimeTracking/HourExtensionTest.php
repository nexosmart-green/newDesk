<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\TimeTracking\Models\TimeEntry;

require_once __DIR__.'/TimeTrackingHelpers.php';

it('lets only hours.allocate approve or reject, with the same reason as loading hours (RF-05.08)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 0);
    $id = $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/extension-requests")->assertCreated()->json('data.id');

    $this->actingAs($dev)->postJson("/api/v1/extension-requests/{$id}/approve", ['seconds' => 3600, 'reason' => 'Me apruebo'])
        ->assertForbidden()->assertJsonPath('data.code', 'cannot_allocate');
    $this->actingAs($dev)->postJson("/api/v1/extension-requests/{$id}/reject")
        ->assertForbidden()->assertJsonPath('message', 'Tu rol no tiene permiso para cargar horas.');

    $this->actingAs(userWithRole($admin, DefaultRoles::MANAGER))->postJson("/api/v1/extension-requests/{$id}/reject")
        ->assertOk()->assertJsonPath('data.status', 'rejected');
    expect(ActivityLog::query()->pluck('event')->all())
        ->toContain('hours.extension_requested', 'hours.extension_rejected');
});

it('lets only the owner complete the comment of a cut entry; others do not learn it exists (RF-05.17)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $other = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev, $other], 3600);
    startTimer($dev, $task);
    $this->travel(2)->hours();
    $this->artisan('timers:cut');
    $entry = TimeEntry::query()->where('user_id', $dev->id)->sole();

    $this->actingAs($other)->putJson("/api/v1/time-entries/{$entry->id}/comment", ['comment' => 'No es mío'])->assertNotFound();
    $this->actingAs($admin)->putJson("/api/v1/time-entries/{$entry->id}/comment", ['comment' => 'Tampoco mío'])
        ->assertForbidden()->assertJsonPath('data.code', 'not_entry_owner');
    $this->actingAs($dev)->putJson("/api/v1/time-entries/{$entry->id}/comment", ['comment' => 'Maqueta de la home'])->assertOk();

    expect(ActivityLog::query()->where('event', 'time_entry.comment_completed')->count())->toBe(1);
});
