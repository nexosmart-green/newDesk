<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Shared\Activity\ActivityLog;
use Illuminate\Support\Facades\Http;

/** P-26: national holidays (AR) from ArgentinaDatos, reviewed by hand afterwards. */
const FERIADOS_URL = 'https://api.argentinadatos.com/v1/feriados/*';

function feriados(int $year): array
{
    return [
        ['fecha' => "{$year}-01-01", 'tipo' => 'inamovible', 'nombre' => 'Año nuevo'],
        ['fecha' => "{$year}-03-23", 'tipo' => 'puente', 'nombre' => 'Puente turístico no laborable'],
        ['fecha' => "{$year}-06-15", 'tipo' => 'trasladable', 'nombre' => 'Paso a la Inmortalidad del General Güemes'],
        ['fecha' => "{$year}-07-09", 'tipo' => 'inamovible', 'nombre' => 'Día de la Independencia'],
        // External data: never a date of another year, an impossible one, or a nameless row.
        ['fecha' => '1999-07-09', 'tipo' => 'inamovible', 'nombre' => 'Otro año'],
        ['fecha' => "{$year}-02-31", 'tipo' => 'inamovible', 'nombre' => 'No existe'],
        ['fecha' => "{$year}-08-17", 'tipo' => 'trasladable', 'nombre' => ''],
        'basura',
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();
    $this->year = now()->year;
    $this->admin = adminUser();
});

it('imports the year\'s holidays, not the optional days off, and never duplicates or overwrites a day', function () {
    Http::fake([FERIADOS_URL => Http::response(feriados($this->year))]);
    // Typed by hand before: that day stays as the Admin left it.
    $this->actingAs($this->admin)->postJson('/api/v1/holidays', ['date' => "{$this->year}-07-09", 'name' => 'Independencia', 'scope' => 'national'])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/holidays/import', ['year' => $this->year])->assertOk()
        ->assertJsonPath('message', "Se agregaron 2 feriados nacionales de {$this->year}.")
        ->assertJsonPath('data', ['added' => 2, 'already' => 1]);

    $holidays = Holiday::query()->orderBy('date')->get(['date', 'name', 'scope']);
    expect($holidays->map(fn ($h) => [$h->date->toDateString(), $h->name, $h->scope])->all())->toBe([
        ["{$this->year}-01-01", 'Año nuevo', 'national'],
        ["{$this->year}-06-15", 'Paso a la Inmortalidad del General Güemes', 'national'],
        ["{$this->year}-07-09", 'Independencia', 'national'],
    ]);
    expect(ActivityLog::query()->where('event', 'holiday.imported')->where('actor_id', $this->admin->id)->count())->toBe(2);

    $this->actingAs($this->admin)->postJson('/api/v1/holidays/import', ['year' => $this->year])->assertOk()
        ->assertJsonPath('message', "Los feriados nacionales de {$this->year} ya estaban cargados.");
    expect(Holiday::query()->count())->toBe(3);
    Http::assertSent(fn ($request) => $request->url() === "https://api.argentinadatos.com/v1/feriados/{$this->year}");
});

it('says so when the source does not answer, and only admins of the company import', function () {
    Http::fake([FERIADOS_URL => Http::response('caído', 500)]);
    $this->actingAs($this->admin)->postJson('/api/v1/holidays/import', ['year' => $this->year])->assertStatus(503)
        ->assertJsonPath('message', 'No se pudo traer la lista de feriados nacionales. Probá más tarde o cargalos a mano.');
    $this->actingAs($this->admin)->postJson('/api/v1/holidays/import', ['year' => $this->year + 5])->assertUnprocessable()
        ->assertJsonPath('errors.year.0', 'Se pueden importar los feriados del año pasado, este o el que viene.');

    $developer = userWithRole($this->admin, DefaultRoles::DEVELOPER);
    $this->actingAs($developer)->postJson('/api/v1/holidays/import', ['year' => $this->year])->assertForbidden();
    expect(Holiday::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('imports next year for every company from the scheduler, each in its own company', function () {
    $other = adminUser('otra@empresa.com');
    Http::fake([FERIADOS_URL => Http::response(feriados($this->year + 1))]);

    $this->artisan('holidays:import')->assertSuccessful();

    foreach ([$this->admin->company_id, $other->company_id] as $companyId) {
        expect(Holiday::query()->forCompany($companyId)->whereYear('date', $this->year + 1)->count())->toBe(3);
    }
    $this->artisan('holidays:import')->assertSuccessful();
    expect(Holiday::query()->withoutGlobalScopes()->count())->toBe(6);
});

it('words a single holiday and a year not published yet', function () {
    Http::fake([
        "https://api.argentinadatos.com/v1/feriados/{$this->year}" => Http::response([['fecha' => "{$this->year}-05-01", 'tipo' => 'inamovible', 'nombre' => 'Día del Trabajador']]),
        FERIADOS_URL => Http::response([]),
    ]);
    $this->actingAs($this->admin)->postJson('/api/v1/holidays/import', ['year' => $this->year])->assertJsonPath('message', "Se agregó 1 feriado nacional de {$this->year}.");
    $this->actingAs($this->admin)->postJson('/api/v1/holidays/import', ['year' => $this->year + 1])->assertJsonPath('message', 'Todavía no hay feriados nacionales publicados para '.($this->year + 1).'.');
});
