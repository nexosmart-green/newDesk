<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Shared\Activity\ActivitySource;

require_once __DIR__.'/McpTestHelpers.php';

function bagConsumption(Project $project, int $allocatedHours, int $consumedHours): void
{
    Project::query()->withoutGlobalScopes()->whereKey($project->id)
        ->update(['allocated_seconds' => $allocatedHours * 3600, 'consumed_seconds' => $consumedHours * 3600]);
}

it('finds the bags running out with the same definition as the web list (RF-03.07)', function () {
    $admin = adminUser();
    ['project' => $gone] = bagProject($admin, [], 0, 'gon');
    ['project' => $healthy] = bagProject($admin, [], 0, 'hea');
    bagConsumption($gone, 10, 9);
    bagConsumption($healthy, 10, 5);

    $web = $this->actingAs($admin)->getJson('/api/v1/projects?running_out=1')->assertOk()->json('data.*.code_prefix');
    $mcp = mcpResult(mcpCall(mcpToken($admin), 'search_projects', ['running_out' => true]))['json'];

    expect($web)->toBe(['gon'])
        ->and(array_column($mcp['projects'], 'prefix'))->toBe($web)
        ->and($mcp['projects'][0])->toMatchArray(['client' => $gone->client->displayName()])
        ->and($mcp['projects'][0]['hours'])->toMatchArray(['percent_consumed' => 90, 'available' => '1h 00m']);

    // list_projects stays "where I am a member"; search_projects is everything the role can see.
    expect(mcpResult(mcpCall(mcpToken($admin), 'list_projects'))['json'])->toBe([])
        ->and(mcpResult(mcpCall(mcpToken($admin), 'search_projects', ['limit' => 1]))['json'])->toMatchArray(['total' => 2, 'has_more' => true]);
});

it('creates and edits projects with people by e-mail, same rules and activity as the web (RF-13.07)', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    ['project' => $existing] = bagProject($admin, [], 0, 'bhn');
    $token = mcpToken($admin);

    $created = mcpResult(mcpCall($token, 'create_project', [
        'name' => 'Web Acme', 'code_prefix' => 'WEB', 'type' => 'bag', 'client_id' => $existing->client_id, 'leader' => $ana->email,
    ]));
    expect($created['error'])->toBeFalse()->and($created['json']['created'])->toMatchArray(['prefix' => 'web', 'leader' => $ana->fullName()]);
    $project = Project::query()->where('code_prefix', 'web')->sole();
    expect($project->hasMember($ana))->toBeTrue();

    mcpCall($token, 'update_project', ['project' => 'web', 'name' => 'Web Acme 2026']);
    expect($project->fresh()->name)->toBe('Web Acme 2026')
        ->and(ActivityLog::query()->where('project_id', $project->id)->pluck('source')->unique()->all())->toBe([ActivitySource::Mcp]);

    // The prefix is unique per company, with the web's message.
    $web = $this->actingAs($admin)->postJson('/api/v1/projects', ['name' => 'Dup', 'code_prefix' => 'bhn', 'type' => 'bag'])
        ->assertUnprocessable()->json('errors.code_prefix.0');
    expect(mcpResult(mcpCall($token, 'create_project', ['name' => 'Dup', 'code_prefix' => 'bhn', 'type' => 'bag']))['text'])->toContain($web);
});

it('changes status and members with the same rules as the web: reopening needs projects.reopen, the leader stays', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [], 0, 'bhn');
    $manager = mcpToken(userWithRole($admin, DefaultRoles::MANAGER));

    expect(mcpResult(mcpCall($manager, 'add_project_member', ['project' => 'bhn', 'person' => $ana->email]))['error'])->toBeFalse()
        ->and($project->fresh()->hasMember($ana))->toBeTrue();
    expect(mcpResult(mcpCall($manager, 'remove_project_member', ['project' => 'bhn', 'person' => $ana->email]))['json'])
        ->toMatchArray(['removed' => $ana->fullName()])
        ->and($project->fresh()->hasMember($ana))->toBeFalse();

    mcpCall($manager, 'change_project_status', ['project' => 'bhn', 'status' => 'finished']);
    $web = $this->actingAs(userWithRole($admin, DefaultRoles::MANAGER))->putJson("/api/v1/projects/{$project->id}/status", ['status' => 'active'])
        ->assertForbidden()->json('message');
    expect(mcpResult(mcpCall($manager, 'change_project_status', ['project' => 'bhn', 'status' => 'active'])))
        ->toMatchArray(['error' => true, 'text' => $web])
        ->and($project->fresh()->status->value)->toBe('finished');

    $project->forceFill(['leader_user_id' => $ana->id])->save();
    $project->members()->attach($ana->id, ['added_by' => $admin->id, 'added_at' => now()]);
    expect(mcpResult(mcpCall($manager, 'remove_project_member', ['project' => 'bhn', 'person' => $ana->email]))['text'])
        ->toBe('Es el responsable del proyecto: asigná otro responsable antes de quitarlo.');
});

it('does not let a Colaborador create, edit or manage members', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 0, 'bhn');
    $token = mcpToken($dev);

    foreach ([
        ['create_project', ['name' => 'X', 'code_prefix' => 'xx', 'type' => 'bag']],
        ['update_project', ['project' => 'bhn', 'name' => 'Otro']],
        ['change_project_status', ['project' => 'bhn', 'status' => 'paused']],
        ['add_project_member', ['project' => 'bhn', 'person' => $admin->email]],
    ] as [$tool, $arguments]) {
        expect(mcpResult(mcpCall($token, $tool, $arguments)))->toMatchArray(['error' => true]);
    }
    expect($project->fresh()->only('name', 'status'))->toBe(['name' => 'BHN', 'status' => $project->status])
        ->and(Project::query()->count())->toBe(1);
});
