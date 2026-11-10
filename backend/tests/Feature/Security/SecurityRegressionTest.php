<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Support\SafeRequestUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

require_once __DIR__.'/../TimeTracking/TimeTrackingHelpers.php';

beforeEach(fn () => $this->withHeaders(['Referer' => 'http://localhost:5173', 'Origin' => 'http://localhost:5173']));

it('locks the pending 2FA login after 5 wrong codes, whatever the client sends', function () {
    adminUser('ada@example.com');
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertJsonPath('data.two_factor_required', true);

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000', 'email' => "random{$attempt}@x.com"])->assertUnprocessable();
    }
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000', 'email' => 'other@x.com'])->assertUnauthorized();

    // The pending login is gone: even the right code needs the password again.
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => currentTotp()])->assertStatus(401);
});

it('does not let a delegated users.manage holder escalate to Admin', function () {
    $admin = adminUser();
    $hr = Role::query()->create(['company_id' => $admin->company_id, 'name' => 'RRHH']);
    // RRHH = a collaborator who also manages users: can handle collaborators, never Admins.
    $collaboratorPermissions = array_map(
        fn (string $value) => Permission::from($value),
        roleNamed($admin, DefaultRoles::COLLABORATOR)->permissionValues(),
    );
    $hr->syncPermissions([...$collaboratorPermissions, Permission::UsersManage]);
    $hrUser = User::factory()->forRole($hr)->create();
    $collaborator = userWithRole($admin);
    $adminRole = roleNamed($admin, DefaultRoles::ADMIN);

    $this->actingAs($hrUser)->putJson("/api/v1/users/{$hrUser->id}/role", ['role_id' => $adminRole->id])->assertForbidden();
    $this->actingAs($hrUser)->putJson("/api/v1/users/{$collaborator->id}/role", ['role_id' => $adminRole->id])
        ->assertForbidden()->assertJsonPath('data.code', 'role_above_actor');
    $this->actingAs($hrUser)->postJson('/api/v1/users', ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@y.com', 'role_id' => $adminRole->id])->assertForbidden();
    $this->actingAs($hrUser)->postJson("/api/v1/people/{$admin->id}/deactivate")->assertForbidden();

    // Within their own power it still works.
    $this->actingAs($hrUser)->putJson("/api/v1/users/{$collaborator->id}/role", ['role_id' => $hr->id])->assertOk();
});

it('never writes path tokens (invitation, iCal) nor query strings to the errors log', function () {
    $url = fn (string $path) => SafeRequestUrl::of(Request::create("https://desk.example.com{$path}"));

    expect($url('/api/v1/invitations/secret-token-123/accept?x=1'))->toBe('https://desk.example.com/api/v1/invitations/[redacted]/accept')
        ->and($url('/calendar/abcDEF123.ics'))->toBe('https://desk.example.com/calendar/[redacted].ics')
        ->and($url('/api/v1/projects/5'))->toBe('https://desk.example.com/api/v1/projects/5');
});

it('revokes MCP tokens when the password is reset', function () {
    $user = User::factory()->create();
    $user->createToken('laptop', ['mcp']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => Password::broker()->createToken($user), 'email' => $user->email,
        'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123',
    ])->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('answers 404 before validating for non-members (CA-03.03)', function () {
    $admin = adminUser();
    $outsider = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [], 3600);

    $this->actingAs($outsider)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => ['not', 'a', 'string']])->assertNotFound();
    $this->actingAs($outsider)->getJson("/api/v1/projects/{$project->id}/tasks?per_page=9999")->assertNotFound();
    $this->actingAs(userWithRole($admin, DefaultRoles::MANAGER))->putJson("/api/v1/projects/{$project->id}", ['name' => 123])->assertUnprocessable();
});
