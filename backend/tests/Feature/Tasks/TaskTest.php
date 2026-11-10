<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Events\CommentPosted;
use App\Domain\Tasks\Events\TaskAssigned;
use App\Domain\Tasks\Events\TaskClosed;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Support\Facades\Event;

/** @param list<User> $members */
function projectWithMembers(User $admin, array $members = [], string $prefix = 'bhn'): Project
{
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $id = test()->actingAs($admin)->postJson('/api/v1/projects', [
        'name' => 'BH News', 'code_prefix' => $prefix, 'type' => 'bag', 'client_id' => $client->id,
        'member_ids' => array_map(fn (User $member) => $member->id, $members),
    ])->json('data.id');

    return Project::query()->withoutGlobalScopes()->findOrFail($id);
}

function statusNamed(User $user, string $name): TaskStatus
{
    return TaskStatus::query()->forCompany($user->company_id)->where('name', $name)->firstOrFail();
}

it('lets a member collaborator create and assign tasks with a correlative code (RF-04.01, P-04)', function () {
    Event::fake([TaskAssigned::class]);
    $admin = adminUser();
    $dev = userWithRole($admin);
    $designer = userWithRole($admin);
    $project = projectWithMembers($admin, [$dev, $designer]);

    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Header', 'assignee_ids' => [$dev->id, $designer->id]])
        ->assertCreated()->assertJsonPath('data.code', 'bhn-1')->assertJsonPath('data.status.name', 'Pendiente');
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Footer'])
        ->assertCreated()->assertJsonPath('data.code', 'bhn-2');

    Event::assertDispatched(TaskAssigned::class, fn ($event) => count($event->assignees) === 1 && $event->assignees[0]->is($designer));
    expect($project->fresh()->prefixIsLocked())->toBeTrue();
});

it('assigns only active project members', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $outsider = userWithRole($admin);
    $project = projectWithMembers($admin, [$dev]);

    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'X', 'assignee_ids' => [$outsider->id]])
        ->assertUnprocessable()->assertJsonPath('data.code', 'not_a_project_member');
    expect($project->fresh()->task_sequence)->toBe(0);
});

it('hides tasks from non-members with 404, by id and by code (CA-03.03)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $outsider = userWithRole($admin);
    $project = projectWithMembers($admin, [$dev]);
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Secreta']);

    $this->actingAs($outsider)->getJson('/api/v1/tasks/bhn-1')->assertNotFound();
    $this->actingAs($outsider)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'X'])->assertNotFound();
    $this->actingAs($dev)->getJson('/api/v1/tasks/BHN-1')->assertOk()->assertJsonPath('data.title', 'Secreta');
});

it('rejects new tasks in finished projects (RF-03.08)', function () {
    $admin = adminUser();
    $project = projectWithMembers($admin);
    $this->actingAs($admin)->putJson("/api/v1/projects/{$project->id}/status", ['status' => 'finished']);

    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'X'])
        ->assertUnprocessable()->assertJsonPath('data.code', 'project_closed');
});

it('completes the task and raises TaskClosed when moved to a closed status (CA-04.03)', function () {
    Event::fake([TaskClosed::class]);
    $admin = adminUser();
    $dev = userWithRole($admin);
    $project = projectWithMembers($admin, [$dev]);
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'X']);

    $this->actingAs($dev)->putJson('/api/v1/tasks/bhn-1/status', ['status_id' => statusNamed($dev, 'Completada')->id])
        ->assertOk()->assertJsonPath('data.completed_at', fn ($value) => $value !== null);
    Event::assertDispatched(TaskClosed::class);

    $this->actingAs($dev)->putJson('/api/v1/tasks/bhn-1/status', ['status_id' => statusNamed($dev, 'En curso')->id])
        ->assertOk()->assertJsonPath('data.completed_at', null);
});

it('filters the list: closed hidden by default, overdue, assignee and code search (RF-04.05)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $project = projectWithMembers($admin, [$dev]);
    $create = fn (array $data) => $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", $data);
    $create(['title' => 'Vencida', 'due_on' => today()->subDays(2)->toDateString(), 'assignee_ids' => [$dev->id]]);
    $create(['title' => 'A tiempo', 'due_on' => today()->addDays(2)->toDateString()]);
    $create(['title' => 'Hecha']);
    $this->actingAs($dev)->putJson('/api/v1/tasks/bhn-3/status', ['status_id' => statusNamed($dev, 'Completada')->id]);

    $list = fn (string $query = '') => $this->actingAs($dev)->getJson("/api/v1/projects/{$project->id}/tasks{$query}");
    $list()->assertJsonCount(2, 'data')->assertJsonPath('data.0.is_overdue', true);
    $list('?include_closed=1')->assertJsonCount(3, 'data');
    $list('?overdue=1')->assertJsonCount(1, 'data');
    $list("?assignee_id={$dev->id}")->assertJsonCount(1, 'data');
    $list('?search=bhn-2')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'A tiempo');
});

it('shows "Mis tareas" across projects, only mine and open (RF-04.07)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $first = projectWithMembers($admin, [$dev], 'one');
    $second = projectWithMembers($admin, [$dev], 'two');
    $this->actingAs($dev)->postJson("/api/v1/projects/{$first->id}/tasks", ['title' => 'Mía 1', 'assignee_ids' => [$dev->id]]);
    $this->actingAs($dev)->postJson("/api/v1/projects/{$second->id}/tasks", ['title' => 'Mía 2', 'assignee_ids' => [$dev->id]]);
    $this->actingAs($dev)->postJson("/api/v1/projects/{$second->id}/tasks", ['title' => 'De nadie']);

    $this->actingAs($dev)->getJson('/api/v1/me/tasks')->assertOk()->assertJsonCount(2, 'data');
});

it('reassigns only with tasks.assign', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $designer = userWithRole($admin);
    $project = projectWithMembers($admin, [$dev, $designer]);
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'X']);

    $this->actingAs($dev)->putJson('/api/v1/tasks/bhn-1/assignees', ['assignee_ids' => [$designer->id]])
        ->assertOk()->assertJsonPath('data.assignees.0.id', $designer->id);

    roleNamed($admin, DefaultRoles::COLLABORATOR)->syncPermissions([]);
    $this->actingAs($dev->fresh())->putJson('/api/v1/tasks/bhn-1/assignees', ['assignee_ids' => [$dev->id]])->assertForbidden();
});

it('posts comments mentioning members only (RF-04.06)', function () {
    Event::fake([CommentPosted::class]);
    $admin = adminUser();
    $dev = userWithRole($admin);
    $designer = userWithRole($admin);
    $outsider = userWithRole($admin);
    $project = projectWithMembers($admin, [$dev, $designer]);
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'X']);

    $this->actingAs($dev)->postJson('/api/v1/tasks/bhn-1/comments', ['body' => '@Diseño mirá esto', 'mentioned_user_ids' => [$designer->id]])
        ->assertCreated()->assertJsonPath('data.mentions.0.id', $designer->id);
    $this->actingAs($dev)->postJson('/api/v1/tasks/bhn-1/comments', ['body' => 'x', 'mentioned_user_ids' => [$outsider->id]])
        ->assertUnprocessable();

    Event::assertDispatched(CommentPosted::class, fn ($event) => $event->mentioned[0]->is($designer));
    $this->actingAs($designer)->getJson('/api/v1/tasks/bhn-1/comments')->assertOk()->assertJsonCount(1, 'data');
});

it('lets company.manage configure statuses keeping one open and one closed (RF-04.03)', function () {
    $admin = adminUser();
    $done = statusNamed($admin, 'Completada');

    $this->actingAs($admin)->postJson('/api/v1/task-statuses', ['name' => 'QA', 'color' => '#123456', 'kind' => 'open'])->assertCreated();
    $this->actingAs($admin)->deleteJson("/api/v1/task-statuses/{$done->id}")
        ->assertUnprocessable()->assertJsonPath('data.code', 'status_set_incomplete');

    $ids = $this->actingAs($admin)->getJson('/api/v1/task-statuses')->json('data.*.id');
    $this->actingAs($admin)->putJson('/api/v1/task-statuses/order', ['ids' => array_reverse($ids)])
        ->assertOk()->assertJsonPath('data.0.name', 'QA');
    $this->actingAs(userWithRole($admin))->postJson('/api/v1/task-statuses', ['name' => 'Y', 'color' => '#123456', 'kind' => 'open'])->assertForbidden();
});
