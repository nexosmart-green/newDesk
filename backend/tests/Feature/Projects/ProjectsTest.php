<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityLog;

beforeEach(function () {
    $this->admin = provisionCompany();
    $this->client = Client::factory()->create(['company_id' => $this->admin->company_id]);
});

function teamUser(User $admin, string $role = DefaultRoles::COLLABORATOR): User
{
    return User::factory()->forRole(roleNamed($admin, $role))->create();
}

it('creates a project and makes the leader a member (RF-03.01, RF-03.03)', function () {
    $leader = teamUser($this->admin, DefaultRoles::DEVELOPER);

    $id = $this->actingAs($this->admin)->postJson('/api/v1/projects', [
        'client_id' => $this->client->id,
        'name' => 'Playinn',
        'code_prefix' => 'pin',
        'leader_user_id' => $leader->id,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.leader.id', $leader->id)
        ->assertJsonPath('data.members.0.id', $leader->id)
        ->json('data.id');

    expect(ActivityLog::query()->where('event', 'project.created')->where('project_id', $id)->exists())->toBeTrue();
});

it('refuses two projects with the same prefix (CA-03.01)', function () {
    Project::factory()->create(['client_id' => $this->client->id, 'code_prefix' => 'bhn']);

    $this->actingAs($this->admin)->postJson('/api/v1/projects', [
        'client_id' => $this->client->id, 'name' => 'Otro', 'code_prefix' => 'bhn',
    ])->assertUnprocessable()->assertJsonPath('errors.code_prefix.0', 'Ya hay un proyecto con ese código.');
});

it('does not take new projects for an inactive client (CA-02.01)', function () {
    $inactive = Client::factory()->inactive()->create(['company_id' => $this->admin->company_id]);

    $this->actingAs($this->admin)->postJson('/api/v1/projects', [
        'client_id' => $inactive->id, 'name' => 'Nuevo', 'code_prefix' => 'new',
    ])->assertUnprocessable()->assertJsonPath('data.code', 'client_inactive');
});

it('answers 404 to a collaborator who is not a member (CA-03.03)', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);
    $outsider = teamUser($this->admin);

    $this->actingAs($outsider)->getJson("/api/v1/projects/{$project->id}")
        ->assertNotFound()
        ->assertJsonPath('message', 'No encontrado.');

    $project->members()->attach($outsider->id);
    $this->actingAs($outsider)->getJson("/api/v1/projects/{$project->id}")->assertOk();
});

it('lists only the projects a collaborator is member of (RF-03.03)', function () {
    $mine = Project::factory()->create(['client_id' => $this->client->id]);
    Project::factory()->create(['client_id' => $this->client->id]);
    $member = teamUser($this->admin);
    $mine->members()->attach($member->id);

    $this->actingAs($member)->getJson('/api/v1/projects')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);

    $this->actingAs($this->admin)->getJson('/api/v1/projects')->assertJsonCount(2, 'data');
});

it('syncs members and keeps the leader (RF-03.03)', function () {
    $leader = teamUser($this->admin, DefaultRoles::DEVELOPER);
    $secondary = teamUser($this->admin, DefaultRoles::DEVELOPER);
    $project = Project::factory()->create(['client_id' => $this->client->id, 'leader_user_id' => $leader->id]);

    $this->actingAs($this->admin)->putJson("/api/v1/projects/{$project->id}/members", ['user_ids' => [$secondary->id]])
        ->assertOk()
        ->assertJsonCount(2, 'data.members');
});

it('does not accept client users as members or leaders', function () {
    $portalUser = User::factory()->forRole(roleNamed($this->admin, DefaultRoles::CLIENT))->create(['client_id' => $this->client->id]);
    $project = Project::factory()->create(['client_id' => $this->client->id]);

    $this->actingAs($this->admin)->putJson("/api/v1/projects/{$project->id}/members", ['user_ids' => [$portalUser->id]])
        ->assertUnprocessable();
});

it('only lets projects.manage create projects', function () {
    $this->actingAs(teamUser($this->admin))->postJson('/api/v1/projects', [
        'client_id' => $this->client->id, 'name' => 'X', 'code_prefix' => 'xx',
    ])->assertForbidden();
});
