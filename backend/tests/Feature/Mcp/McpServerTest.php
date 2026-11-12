<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\TimeTracking\Models\TimeEntry;
use Laravel\Sanctum\PersonalAccessToken;

require_once __DIR__.'/McpTestHelpers.php';

it('issues an nd_ token shown once, stored hashed, with the ready-to-copy command (CA-12.02, RF-12.04)', function () {
    $dev = userWithRole(adminUser());

    $response = $this->actingAs($dev)->postJson('/api/v1/me/ai-tokens', ['name' => 'Notebook', 'expires_in_days' => 90])->assertCreated();
    $plain = $response->json('data.plain_text_token');

    expect($plain)->toMatch('/^\d+\|nd_[A-Za-z0-9]{40}/')
        ->and($response->json('data.claude_code_command'))->toContain('claude mcp add --transport http newdesk')->toContain($plain)
        ->and($response->json('data.project_mcp_json.mcpServers.newdesk.headers.Authorization'))->toBe('Bearer ${NEWDESK_TOKEN}')
        ->and(PersonalAccessToken::query()->first()->token)->not->toContain(substr($plain, 3, 20));

    $this->actingAs($dev)->getJson('/api/v1/me/ai-tokens')->assertJsonMissing(['plain_text_token' => $plain])->assertJsonPath('data.tokens.0.name', 'Notebook');
});

it('answers 401 without a token and lists every R1 tool in one page', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();

    $tools = test()->withHeaders(['Authorization' => 'Bearer '.mcpToken(userWithRole(adminUser()))])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object) []])
        ->json('result.tools.*.name');

    expect($tools)->toContain('whoami', 'start_timer', 'stop_timer', 'allocate_hours', 'project_hours_report', 'update_checklist')->toHaveCount(20);
});

it('answers whoami with role, permissions and timer; revoked token → 401 (CA-12.02)', function () {
    $dev = userWithRole(adminUser());
    $token = mcpToken($dev);

    $result = mcpResult(mcpCall($token, 'whoami')->assertOk());
    expect($result['json']['role'])->toBe(DefaultRoles::COLLABORATOR)->and($result['json']['permissions'])->toContain('mcp.use');

    $id = PersonalAccessToken::query()->value('id');
    $this->actingAs($dev)->deleteJson("/api/v1/ai-tokens/{$id}")->assertOk();
    mcpCall($token, 'whoami')->assertUnauthorized();
});

it('evaluates permissions per request: promoted without a new token, allocate_hours starts working (CA-12.03)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 0);
    $token = mcpToken($dev);

    expect(mcpResult(mcpCall($token, 'allocate_hours', ['project' => 'bhn', 'minutes' => 300, 'reason' => 'Presupuesto #9']))['error'])->toBeTrue();

    $dev->update(['role_id' => roleNamed($admin, DefaultRoles::MANAGER)->id]);

    $result = mcpResult(mcpCall($token, 'allocate_hours', ['project' => 'bhn', 'minutes' => 300, 'reason' => 'Presupuesto #9']));
    expect($result['error'])->toBeFalse()->and($project->fresh()->allocated_seconds)->toBe(18000);
});

it('blocks MCP for roles without mcp.use, inactive users and REST calls with the token', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $token = mcpToken($dev);

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/me')->assertForbidden();

    roleNamed($admin, DefaultRoles::COLLABORATOR)->syncPermissions([Permission::TimeTrack]);
    mcpCall($token, 'whoami')->assertForbidden();

    $dev->forceFill(['status' => 'inactive'])->save();
    mcpCall($token, 'whoami')->assertUnauthorized();
});

it('stop_timer without a valid comment errors and the timer keeps running (CA-12.x)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $token = mcpToken($dev);
    mcpCall($token, 'start_timer', ['code' => $task->code]);

    $result = mcpResult(mcpCall($token, 'stop_timer', ['comment' => 'corto']));
    expect($result['error'])->toBeTrue()->and($result['text'])->toContain('mínimo 10')
        ->and(TimeEntry::query()->running()->count())->toBe(1);

    expect(mcpResult(mcpCall($token, 'stop_timer', ['comment' => 'Implementé el login']))['error'])->toBeFalse();
    expect(TimeEntry::query()->first()->channel->value)->toBe('mcp');
});

it('QA-01 vía MCP: same reason as the web when there are no hours (CA-12.y)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 0);

    $result = mcpResult(mcpCall(mcpToken($dev), 'start_timer', ['code' => $task->code]));

    expect($result['error'])->toBeTrue()->and($result['text'])->toBe('Sin horas disponibles en BHN: pedí una ampliación.');
});

it('QA-02 vía MCP: timers started by MCP are cut exactly when the bag runs out (CA-12.z)', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    $beto = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$ana, $beto], 1800);
    mcpCall(mcpToken($ana, 'a'), 'start_timer', ['code' => $task->code]);
    mcpCall(mcpToken($beto, 'b'), 'start_timer', ['code' => $task->code]);

    $this->travel(17)->minutes();
    $this->artisan('timers:cut');

    expect($project->fresh()->consumed_seconds)->toBe(1800)->and(TimeEntry::query()->running()->count())->toBe(0);
});

it('QA-03 vía MCP: repeated start_timer keeps a single timer, and switching demands the comment', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $first] = bagProject($admin, [$dev], 3600, 'one');
    ['task' => $second] = bagProject($admin, [$dev], 3600, 'two');
    $token = mcpToken($dev);

    mcpCall($token, 'start_timer', ['code' => $first->code]);
    mcpCall($token, 'start_timer', ['code' => $first->code]);
    expect(TimeEntry::query()->count())->toBe(1);

    expect(mcpResult(mcpCall($token, 'start_timer', ['code' => $second->code]))['error'])->toBeTrue();
    expect(mcpResult(mcpCall($token, 'start_timer', ['code' => $second->code, 'comment' => 'Cerré el formulario']))['error'])->toBeFalse();
    expect(TimeEntry::query()->running()->first()->task_id)->toBe($second->id);
});

it('creates, assigns, comments and moves tasks with the same rules, logged with source mcp (RF-12.06)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $designer = userWithRole($admin);
    bagProject($admin, [$dev, $designer], 3600);
    $token = mcpToken($dev, 'claude-code');

    $created = mcpResult(mcpCall($token, 'create_task', ['project' => 'bhn', 'title' => 'Footer', 'assignees' => [$designer->email]]));
    expect($created['json']['created']['code'])->toBe('bhn-2');

    expect(mcpResult(mcpCall($token, 'comment_task', ['code' => 'bhn-2', 'body' => 'Revisalo', 'mentions' => [$designer->email]]))['json']['mentioned'])->toHaveCount(1);
    expect(mcpResult(mcpCall($token, 'update_task_status', ['code' => 'bhn-2', 'status' => 'en curso']))['json']['updated']['status'])->toBe('En curso');
    expect(mcpResult(mcpCall($token, 'get_task', ['code' => 'BHN-2']))['json']['comments'])->toHaveCount(1);

    $log = ActivityLog::query()->where('event', 'task.created')->latest('id')->first();
    expect($log->source->value)->toBe('mcp')->and($log->token_name)->toBe('claude-code');
});

it('hides projects and tasks where the person is not a member', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    bagProject($admin, [], 3600);

    $token = mcpToken($dev);
    expect(mcpResult(mcpCall($token, 'get_task', ['code' => 'bhn-1']))['error'])->toBeTrue();
    expect(mcpResult(mcpCall($token, 'list_projects'))['json'])->toBe([]);
});

it('reports project hours only to projects.manage', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev, $admin], 3600);
    startTimer($dev, $task);
    $this->travel(30)->minutes();
    stopTimer($dev);

    expect(mcpResult(mcpCall(mcpToken($dev), 'project_hours_report', ['project' => 'bhn']))['error'])->toBeTrue();
    $report = mcpResult(mcpCall(mcpToken($admin), 'project_hours_report', ['project' => 'bhn']))['json'];
    expect($report['by_person'][0]['seconds'])->toBe(1800)->and($report['by_task'][0]['code'])->toBe('bhn-1');
});

it('rate limits each token at 60 requests per minute (RF-12.07)', function () {
    $token = mcpToken(userWithRole(adminUser()));

    foreach (range(1, 60) as $call) {
        mcpCall($token, 'get_active_timer');
    }

    mcpCall($token, 'get_active_timer')->assertTooManyRequests();
});
