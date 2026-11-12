<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Tasks\Actions\CreateTask;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../Tasks/ParityHelpers.php';

const HISTORY_TZ = 'America/Argentina/Buenos_Aires';

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-11 15:00:00', 'UTC')));

it('lists my entries of a free range, newest first, with the total of the whole filter', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $teammate = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev, $teammate], 100 * 3600);

    logEntry($dev, $task, Carbon::parse('2026-11-10 12:00', HISTORY_TZ), 3600, 'Maquetado del header');
    logEntry($dev, $task, Carbon::parse('2026-11-09 23:30', HISTORY_TZ), 5400, 'Ajustes nocturnos'); // 02:30 UTC of the 10th, local day 9
    logEntry($dev, $task, Carbon::parse('2026-11-11 08:30', HISTORY_TZ), null, null, 'timer');       // running: 3.5 h so far
    logEntry($dev, $task, Carbon::parse('2026-11-04 23:59', HISTORY_TZ), 600);                       // the day before the range
    logEntry($teammate, $task, Carbon::parse('2026-11-10 10:00', HISTORY_TZ), 7200);                // someone else

    $this->actingAs($dev)->getJson('/api/v1/me/time-entries?from=2026-11-05&to=2026-11-11')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.total_seconds', 3600 + 5400 + 12600)
        ->assertJsonPath('data.0.is_running', true)
        ->assertJsonPath('data.0.seconds', 12600)
        ->assertJsonPath('data.0.source', 'timer')
        ->assertJsonPath('data.1.comment', 'Maquetado del header')
        ->assertJsonPath('data.1.task', ['code' => $task->code, 'title' => 'Trabajo'])
        ->assertJsonPath('data.1.project', ['id' => $project->id, 'name' => $project->name])
        ->assertJsonPath('data.1.user.id', $dev->id)
        ->assertJsonPath('data.2.started_at', '2026-11-10T02:30:00+00:00')
        ->assertJsonStructure(['data' => [['id', 'user' => ['id', 'full_name'], 'task' => ['code', 'title'], 'project' => ['id', 'name'], 'started_at', 'ended_at', 'seconds', 'is_running', 'comment', 'comment_pending', 'source']]]);
});

it('filters by project and searches comment, task code and title; totals follow the filter, pages hold 50', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev], 100 * 3600);
    ['project' => $web, 'task' => $webTask] = bagProject($admin, [$dev], 100 * 3600, 'web');
    $landing = app(CreateTask::class)($admin, $web, ['title' => 'Landing de verano'], [$dev->id]);

    logEntry($dev, $task, Carbon::parse('2026-11-10 09:00', HISTORY_TZ), 3600, 'Reunión con el cliente');
    logEntry($dev, $webTask, Carbon::parse('2026-11-10 11:00', HISTORY_TZ), 1800, 'Deploy a producción');
    logEntry($dev, $landing, Carbon::parse('2026-11-10 13:00', HISTORY_TZ), 900, 'Ajustes');

    $query = fn (string $filters) => $this->actingAs($dev)->getJson("/api/v1/me/time-entries?from=2026-11-01&to=2026-11-30&{$filters}")->assertOk();

    $query("project_id={$web->id}")->assertJsonPath('meta.total', 2)->assertJsonPath('meta.total_seconds', 2700);
    $query('search=cliente')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.comment', 'Reunión con el cliente');
    $query('search=verano')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.task.code', $landing->code);
    $query("search={$webTask->code}")->assertJsonPath('meta.total_seconds', 1800);

    foreach (range(1, 50) as $n) {
        logEntry($dev, $task, Carbon::parse('2026-11-02 08:00', HISTORY_TZ)->addMinutes($n * 10), 60, "Bloque {$n}");
    }
    $query('page=2')->assertJsonPath('meta.per_page', 50)->assertJsonPath('meta.total', 53)->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total_seconds', 3600 + 1800 + 900 + 50 * 60);
});

it('shows someone else entries only with time.manage', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $teammate = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev, $teammate], 100 * 3600);
    logEntry($teammate, $task, Carbon::parse('2026-11-10 10:00', HISTORY_TZ), 7200);
    $foreigner = adminUser('otra@example.com');

    $this->actingAs($dev)->getJson("/api/v1/me/time-entries?from=2026-11-01&to=2026-11-30&user_id={$teammate->id}")
        ->assertForbidden()->assertJsonPath('message', 'Solo quien gestiona horas puede ver los registros de otras personas.');
    $this->actingAs($dev)->get("/api/v1/me/time-entries/export?from=2026-11-01&to=2026-11-30&user_id={$teammate->id}", ['Accept' => 'application/json'])
        ->assertForbidden();
    $this->actingAs($dev)->getJson("/api/v1/me/time-entries?from=2026-11-01&to=2026-11-30&user_id={$dev->id}")->assertOk();

    $this->actingAs($admin)->getJson("/api/v1/me/time-entries?from=2026-11-01&to=2026-11-30&user_id={$teammate->id}")
        ->assertOk()->assertJsonPath('meta.total_seconds', 7200)->assertJsonPath('data.0.user.id', $teammate->id);
    $this->actingAs($admin)->getJson("/api/v1/me/time-entries?from=2026-11-01&to=2026-11-30&user_id={$foreigner->id}")
        ->assertUnprocessable()->assertJsonPath('errors.user_id.0', 'Esa persona ya no está en la empresa. Elegí otra.');
});

it('validates the range: both dates, in order, up to 366 days', function () {
    $admin = adminUser();

    $this->actingAs($admin)->getJson('/api/v1/me/time-entries?from=2025-11-11&to=2026-11-11')->assertOk(); // 366 days
    $this->actingAs($admin)->getJson('/api/v1/me/time-entries?from=2025-11-10&to=2026-11-11')
        ->assertUnprocessable()->assertJsonPath('errors.to.0', 'Elegí un rango de hasta 366 días (pediste 367). Si necesitás más, consultalo por partes: un período a continuación del otro.');
    $this->actingAs($admin)->getJson('/api/v1/me/time-entries/export?from=2020-01-01&to=2026-11-11')->assertUnprocessable();
    $this->actingAs($admin)->getJson('/api/v1/me/time-entries?from=2026-11-11&to=2026-11-01')
        ->assertUnprocessable()->assertJsonPath('errors.to.0', 'La fecha hasta no puede ser anterior a la fecha desde.');
    $this->actingAs($admin)->getJson('/api/v1/me/time-entries')
        ->assertUnprocessable()->assertJsonPath('errors.from.0', 'Elegí la fecha desde.');
});

it('exports the same filter as a CSV for Excel (BOM, semicolons, decimal comma, local times)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev], 100 * 3600);
    logEntry($dev, $task, Carbon::parse('2026-11-10 09:00', HISTORY_TZ), 5400, 'Maquetado; header "nuevo"');
    logEntry($dev, $task, Carbon::parse('2026-11-09 18:00', HISTORY_TZ), 900, '=HYPERLINK("http://malo")');
    logEntry($dev, $task, Carbon::parse('2026-11-11 11:00', HISTORY_TZ), null, null, 'timer'); // running: 1 h so far

    $response = $this->actingAs($dev)->get('/api/v1/me/time-entries/export?from=2026-11-01&to=2026-11-30')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('horas-2026-11-01-a-2026-11-30.csv');

    $csv = $response->streamedContent();
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();
    $lines = explode("\n", trim(substr($csv, 3)));
    $name = $dev->fullName();

    $rows = array_map(fn (string $line) => str_getcsv($line, ';', '"', ''), $lines);

    expect($lines)->toHaveCount(4)
        ->and($lines[0])->toBe('Fecha;Inicio;Fin;Duración;Horas;Persona;Proyecto;Tarea;Título;Comentario;Origen')
        ->and($rows[1])->toBe(['11/11/2026', '11:00', '', '1:00', '1,00', $name, $project->name, $task->code, 'Trabajo', '', 'Cronómetro'])
        ->and($rows[2])->toBe(['10/11/2026', '09:00', '10:30', '1:30', '1,50', $name, $project->name, $task->code, 'Trabajo', 'Maquetado; header "nuevo"', 'Manual'])
        ->and($rows[3])->toBe(['09/11/2026', '18:00', '18:15', '0:15', '0,25', $name, $project->name, $task->code, 'Trabajo', "'=HYPERLINK(\"http://malo\")", 'Manual']);
});

it('keeps the history behind the time module', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleTime);

    $this->actingAs($dev->fresh())->getJson('/api/v1/me/time-entries?from=2026-11-01&to=2026-11-30')
        ->assertForbidden()->assertJsonPath('data.code', 'module_disabled');
    $this->actingAs($dev->fresh())->getJson('/api/v1/me/time-entries/export?from=2026-11-01&to=2026-11-30')->assertForbidden();
});
