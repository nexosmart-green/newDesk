<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Events\ProjectClosed;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Facades\Event;

function createProject(User $actor, array $overrides = []): int
{
    $client = Client::factory()->create(['company_id' => $actor->company_id]);

    return test()->actingAs($actor)->postJson('/api/v1/projects', [
        'name' => 'BH News', 'code_prefix' => 'bhn', 'type' => 'bag', 'client_id' => $client->id, ...$overrides,
    ])->assertCreated()->json('data.id');
}

it('creates a project with the leader as member and logs it (RF-03.01/03/06)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);

    $id = createProject($admin, ['code_prefix' => 'BHN', 'leader_user_id' => $dev->id]);

    $this->actingAs($admin)->getJson("/api/v1/projects/{$id}")
        ->assertOk()
        ->assertJsonPath('data.code_prefix', 'bhn')
        ->assertJsonPath('data.leader.id', $dev->id)
        ->assertJsonPath('data.members.0.id', $dev->id);
    $this->actingAs($admin)->getJson("/api/v1/projects/{$id}/activity")
        ->assertOk()->assertJsonPath('data.0.event', 'project.created')->assertJsonPath('data.0.source', 'web');
});

it('allows an internal project without client (RF-03.02)', function () {
    $this->actingAs(adminUser())->postJson('/api/v1/projects', ['name' => 'NexoSmart', 'code_prefix' => 'nxs', 'type' => 'internal'])
        ->assertCreated()->assertJsonPath('data.client', null)->assertJsonPath('data.type', 'internal');
});

it('validates prefix format and project type', function () {
    $this->actingAs(adminUser())->postJson('/api/v1/projects', ['name' => 'X', 'code_prefix' => '1-a', 'type' => 'monthly'])
        ->assertUnprocessable()->assertJsonValidationErrors(['code_prefix', 'type']);
});

it('enforces unique prefixes and freezes them once tasks exist (CA-03.01)', function () {
    $admin = adminUser();
    $id = createProject($admin);

    $this->actingAs($admin)->postJson('/api/v1/projects', ['name' => 'Dup', 'code_prefix' => 'BHN', 'type' => 'internal'])
        ->assertUnprocessable()->assertJsonValidationErrors('code_prefix');

    $this->actingAs($admin)->putJson("/api/v1/projects/{$id}", ['code_prefix' => 'bhx'])->assertOk();
    Project::query()->whereKey($id)->update(['task_sequence' => 3]);

    $this->actingAs($admin)->putJson("/api/v1/projects/{$id}", ['code_prefix' => 'bhy'])
        ->assertUnprocessable()->assertJsonPath('data.code', 'prefix_locked');
});

it('answers 404 to a collaborator who is not a member (CA-03.03)', function () {
    $admin = adminUser();
    $member = userWithRole($admin);
    $outsider = userWithRole($admin);
    $id = createProject($admin, ['member_ids' => [$member->id]]);

    $this->actingAs($outsider)->getJson("/api/v1/projects/{$id}")->assertNotFound();
    $this->actingAs($outsider)->getJson('/api/v1/projects')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($member)->getJson("/api/v1/projects/{$id}")->assertOk()->assertJsonPath('data.can.update', false);
    $this->actingAs($member)->getJson('/api/v1/projects')->assertJsonCount(1, 'data');
    $this->actingAs($member)->putJson("/api/v1/projects/{$id}", ['name' => 'Mío'])->assertForbidden();
});

it('hides projects of other companies even from their admins', function () {
    $id = createProject(adminUser('a@nexo.com'));

    $this->actingAs(adminUser('b@other.com'))->getJson("/api/v1/projects/{$id}")->assertNotFound();
});

it('raises ProjectClosed when finishing and only projects.reopen reopens (RF-03.08, CA-03.08)', function () {
    Event::fake([ProjectClosed::class]);
    $admin = adminUser();
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    $id = createProject($manager);

    $this->actingAs($manager)->putJson("/api/v1/projects/{$id}/status", ['status' => 'finished'])->assertOk();
    Event::assertDispatched(ProjectClosed::class, fn ($event) => $event->project->id === $id);

    $this->actingAs($manager)->putJson("/api/v1/projects/{$id}/status", ['status' => 'active'])
        ->assertForbidden()->assertJsonPath('data.code', 'reopen_forbidden');
    $this->actingAs($admin)->putJson("/api/v1/projects/{$id}/status", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
});

it('manages members: only active people, never the leader, all logged', function () {
    $admin = adminUser();
    $leader = userWithRole($admin);
    $dev = userWithRole($admin);
    $gone = User::factory()->inactive()->forRole(roleNamed($admin, DefaultRoles::COLLABORATOR))->create();
    $id = createProject($admin, ['leader_user_id' => $leader->id]);

    $this->actingAs($admin)->postJson("/api/v1/projects/{$id}/members", ['user_id' => $dev->id])->assertCreated();
    $this->actingAs($admin)->postJson("/api/v1/projects/{$id}/members", ['user_id' => $gone->id])
        ->assertUnprocessable()->assertJsonPath('data.code', 'member_inactive');
    $this->actingAs($admin)->deleteJson("/api/v1/projects/{$id}/members/{$leader->id}")
        ->assertUnprocessable()->assertJsonPath('data.code', 'member_is_leader');
    $this->actingAs($admin)->deleteJson("/api/v1/projects/{$id}/members/{$dev->id}")->assertOk();

    $events = $this->actingAs($admin)->getJson("/api/v1/projects/{$id}/activity")->json('data.*.event');
    expect($events)->toContain('project.member_added', 'project.member_removed');
    $this->actingAs($dev)->postJson("/api/v1/projects/{$id}/members", ['user_id' => $dev->id])->assertNotFound();
});

it('filters the list by client and status (RF-03.07)', function () {
    $admin = adminUser();
    $id = createProject($admin);
    createProject($admin, ['code_prefix' => 'otr', 'name' => 'Otro']);
    $clientId = Project::query()->find($id)->client_id;

    $this->actingAs($admin)->getJson("/api/v1/projects?client_id={$clientId}")->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson('/api/v1/projects?status=finished')->assertJsonCount(0, 'data');
});
