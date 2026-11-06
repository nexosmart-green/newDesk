<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\AllocateHours;
use Illuminate\Testing\TestResponse;

/**
 * A bag project with `$hours` loaded and one task assigned to every member.
 *
 * @param  list<User>  $members
 * @return array{project: Project, task: Task}
 */
function bagProject(User $admin, array $members, int $seconds, string $prefix = 'bhn', string $type = 'bag'): array
{
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $project = app(CreateProject::class)($admin, [
        'name' => strtoupper($prefix), 'code_prefix' => $prefix, 'type' => $type, 'client_id' => $client->id,
    ], array_map(fn (User $member) => $member->id, $members));

    if ($seconds > 0) {
        app(AllocateHours::class)($admin, $project, $seconds, 'Bolsa inicial');
    }

    $task = app(CreateTask::class)($admin, $project, ['title' => 'Trabajo'], array_map(fn (User $member) => $member->id, $members));

    return ['project' => $project->fresh(), 'task' => $task];
}

function startTimer(User $user, Task $task, ?string $previousComment = null): TestResponse
{
    return test()->actingAs($user)->postJson("/api/v1/tasks/{$task->code}/timer", array_filter(['previous_comment' => $previousComment]));
}

function stopTimer(User $user, ?string $comment = 'Maquetado del header'): TestResponse
{
    return test()->actingAs($user)->postJson('/api/v1/me/timer/stop', ['comment' => $comment]);
}
