<?php

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\CreateTask;

require_once __DIR__.'/McpTestHelpers.php';

it('lists the overdue tasks of the whole team, by person, like "Todas las tareas" (RF-13.07)', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    $beto = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$ana, $beto], 3600);
    $late = app(CreateTask::class)($admin, $project, ['title' => 'Atrasada', 'due_on' => now()->subDays(3)->toDateString()], [$ana->id]);
    app(CreateTask::class)($admin, $project, ['title' => 'A tiempo', 'due_on' => now()->addDays(3)->toDateString()], [$beto->id]);
    $token = mcpToken($admin);

    $overdue = mcpResult(mcpCall($token, 'list_tasks', ['overdue' => true]))['json'];
    expect($overdue['total'])->toBe(1)->and($overdue['tasks'][0])->toMatchArray(['code' => $late->code, 'overdue' => true, 'assignees' => [$ana->fullName()]]);

    $web = $this->actingAs($admin)->getJson('/api/v1/tasks?overdue=1')->assertOk()->json('data.*.code');
    expect($web)->toBe([$late->code])
        ->and(array_column(mcpResult(mcpCall($token, 'list_tasks', ['assignee' => $beto->email]))['json']['tasks'], 'title'))->toBe(['A tiempo', 'Trabajo'])
        ->and(mcpResult(mcpCall($token, 'list_tasks', ['limit' => 1]))['json'])->toMatchArray(['total' => 3, 'has_more' => true]);

    // A Colaborador sees only the projects where they are a member.
    $outsider = userWithRole($admin);
    expect(mcpResult(mcpCall(mcpToken($outsider), 'list_tasks'))['json']['total'])->toBe(0);
});

it('lists the people directory without rates or personal data, filtered by area', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    $gone = User::factory()->inactive()->forRole(roleNamed($admin, 'Colaborador'))->create();
    $area = $this->actingAs($admin)->postJson('/api/v1/catalogs/areas', ['name' => 'Desarrollo'])->json('data.id');
    $this->actingAs($admin)->putJson("/api/v1/people/{$ana->id}", ['area_id' => $area])->assertOk();
    $token = mcpToken(userWithRole($admin));

    $people = mcpResult(mcpCall($token, 'list_people'))['json'];
    expect(array_column($people['people'], 'id'))->toContain($admin->id, $ana->id)->not->toContain($gone->id)
        ->and(array_keys($people['people'][0]))->toBe(['id', 'name', 'email', 'status', 'job_title', 'area']);

    expect(array_column(mcpResult(mcpCall($token, 'list_people', ['area' => 'desarrollo']))['json']['people'], 'id'))->toBe([$ana->id])
        ->and(mcpResult(mcpCall($token, 'list_people', ['area' => 'Ventas']))['text'])->toStartWith('No hay un área «Ventas». Usá una de estas: ')->toContain('Desarrollo');
});

it('shows the team shifts to whoever manages shifts, with the same 31-day limit and denial as the web', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    $template = $this->actingAs($admin)->postJson('/api/v1/shift-templates', [
        'name' => 'Completo', 'starts_at' => '09:00', 'ends_at' => '18:00', 'color' => '#309798', 'mode' => 'remote',
    ])->json('data.id');
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$ana->id], 'shift_template_id' => $template, 'from' => '2026-11-16', 'to' => '2026-11-17'])->assertOk();

    $days = mcpResult(mcpCall(mcpToken($admin), 'team_shifts', ['from' => '2026-11-16', 'to' => '2026-11-18']))['json']['days'];
    expect($days)->toHaveCount(3)
        ->and($days[0]['shifts'])->toBe([['person' => $ana->fullName(), 'from' => '09:00', 'to' => '18:00', 'mode' => 'remote', 'shift' => 'Completo']])
        ->and($days[2]['shifts'])->toBe([]);

    expect(mcpResult(mcpCall(mcpToken($admin), 'team_shifts', ['from' => '2026-11-01', 'to' => '2026-12-02']))['text'])->toContain('Elegí un rango de hasta 31 días')
        ->and(mcpResult(mcpCall(mcpToken($ana), 'team_shifts', ['from' => '2026-11-16', 'to' => '2026-11-18']))['text'])
        ->toBe('El calendario de equipo es para quien gestiona turnos o tiene gente a cargo.');
});
