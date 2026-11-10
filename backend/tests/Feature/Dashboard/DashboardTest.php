<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\ChangeProjectStatus;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../Tasks/ParityHelpers.php';

// Wednesday 2026-11-11, 12:00 in Buenos Aires.
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-11 15:00:00', 'UTC')));

function dueTask(User $admin, Project $project, string $title, ?string $dueOn, User $assignee): Task
{
    return app(CreateTask::class)($admin, $project, ['title' => $title, 'due_on' => $dueOn], [$assignee->id]);
}

function setConsumed(Project $project, int $allocatedHours, int $consumedHours): void
{
    Project::query()->withoutGlobalScopes()->whereKey($project->id)
        ->update(['allocated_seconds' => $allocatedHours * 3600, 'consumed_seconds' => $consumedHours * 3600]);
}

it('shows my open tasks, overdue, due today and the next five to expire', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $teammate = userWithRole($admin);
    ['project' => $project, 'task' => $noDueDate] = bagProject($admin, [$dev, $teammate], 3600);

    dueTask($admin, $project, 'Vencida', '2026-11-10', $dev);
    $today = dueTask($admin, $project, 'Vence hoy', '2026-11-11', $dev);
    $soon = collect(range(12, 17))->map(fn (int $day) => dueTask($admin, $project, "Día {$day}", "2026-11-{$day}", $dev));
    $closed = dueTask($admin, $project, 'Cerrada', '2026-11-11', $dev);
    app(ChangeTaskStatus::class)($admin, $closed, TaskStatus::query()->forCompany($admin->company_id)->where('name', 'Completada')->firstOrFail());
    dueTask($admin, $project, 'Del compañero', '2026-11-11', $teammate);

    $response = $this->actingAs($dev)->getJson('/api/v1/me/dashboard')->assertOk()
        ->assertJsonPath('data.tasks.open', 9) // no date + overdue + today + 6 upcoming
        ->assertJsonPath('data.tasks.overdue', 1)
        ->assertJsonPath('data.tasks.due_today', 1)
        ->assertJsonCount(5, 'data.tasks.due_soon')
        ->assertJsonPath('data.tasks.due_soon.0.code', $today->code)
        ->assertJsonPath('data.tasks.due_soon.0.logged_seconds', 0)
        ->assertJsonStructure(['data' => ['tasks' => ['due_soon' => [['id', 'code', 'title', 'project', 'status', 'due_on', 'logged_seconds', 'category', 'labels', 'assignees']]]]]);

    expect(array_column($response->json('data.tasks.due_soon'), 'code'))
        ->toBe([$today->code, ...$soon->take(4)->pluck('code')->all()])
        ->not->toContain($noDueDate->code);
});

it('counts the active projects I can see', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    bagProject($admin, [$dev], 3600, 'act');
    ['project' => $paused] = bagProject($admin, [$dev], 3600, 'pau');
    app(ChangeProjectStatus::class)($admin, $paused, ProjectStatus::Paused);
    bagProject($admin, [], 3600, 'aje'); // not a member

    $this->actingAs($dev)->getJson('/api/v1/me/dashboard')->assertJsonPath('data.projects.active', 1);
    $this->actingAs($admin)->getJson('/api/v1/me/dashboard')->assertJsonPath('data.projects.active', 2);
});

it('sums my hours of the local week, Monday to Sunday, running timer included', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $teammate = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev, $teammate], 100 * 3600);
    $tz = 'America/Argentina/Buenos_Aires';

    logEntry($dev, $task, Carbon::parse('2026-11-09 09:00', $tz), 3600);
    logEntry($dev, $task, Carbon::parse('2026-11-08 22:00', $tz), 1800); // last Sunday: previous week
    logEntry($dev, $task, Carbon::parse('2026-11-11 11:00', $tz), null); // running: 1 h so far
    logEntry($teammate, $task, Carbon::parse('2026-11-10 09:00', $tz), 7200);

    $this->actingAs($dev)->getJson('/api/v1/me/dashboard')
        ->assertJsonPath('data.week.from', '2026-11-09')
        ->assertJsonPath('data.week.to', '2026-11-15')
        ->assertJsonPath('data.week.total_seconds', 7200)
        ->assertJsonCount(7, 'data.week.days')
        ->assertJsonPath('data.week.days.0', ['date' => '2026-11-09', 'seconds' => 3600])
        ->assertJsonPath('data.week.days.1', ['date' => '2026-11-10', 'seconds' => 0])
        ->assertJsonPath('data.week.days.2', ['date' => '2026-11-11', 'seconds' => 3600]);
});

it('lists bags at 80 % or more, most consumed first, only for who allocates hours or manages projects', function () {
    $admin = adminUser();
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    $dev = userWithRole($admin);
    ['project' => $almost, 'task' => $almostTask] = bagProject($admin, [$dev], 0, 'alm');
    ['project' => $nearlyGone] = bagProject($admin, [], 0, 'gon');
    ['project' => $healthy] = bagProject($admin, [], 0, 'hea');
    ['project' => $paused] = bagProject($admin, [], 0, 'pau');
    ['project' => $internal] = bagProject($admin, [], 0, 'int', 'internal');
    setConsumed($almost, 10, 7);       // 70 % closed + 1 h running = 80 %
    setConsumed($nearlyGone, 10, 9);   // 90 %
    setConsumed($healthy, 10, 5);      // 50 %
    setConsumed($paused, 10, 10);
    setConsumed($internal, 0, 50);
    app(ChangeProjectStatus::class)($admin, $paused->fresh(), ProjectStatus::Paused);
    logEntry($dev, $almostTask, now()->subHour(), null);

    $this->actingAs($manager)->getJson('/api/v1/me/dashboard')
        ->assertJsonCount(2, 'data.bags_running_out')
        ->assertJsonPath('data.bags_running_out.0', [
            'id' => $nearlyGone->id, 'name' => $nearlyGone->name, 'code_prefix' => 'gon', 'allocated_seconds' => 36000, 'consumed_seconds' => 32400,
        ])
        ->assertJsonPath('data.bags_running_out.1.id', $almost->id)
        ->assertJsonPath('data.bags_running_out.1.consumed_seconds', 8 * 3600);

    $this->actingAs($dev)->getJson('/api/v1/me/dashboard')->assertJsonPath('data.bags_running_out', null);
});

it('omits the blocks of modules the role does not have', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleProjects);
    $this->actingAs($dev->fresh())->getJson('/api/v1/me/dashboard')->assertOk()
        ->assertJsonPath('data.tasks', null)
        ->assertJsonPath('data.projects', null)
        ->assertJsonPath('data.week.total_seconds', 0);

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleTime);
    $this->actingAs($dev->fresh())->getJson('/api/v1/me/dashboard')->assertOk()->assertJsonPath('data.week', null);
});
