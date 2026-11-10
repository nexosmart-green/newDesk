<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\Period;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\StatusPageFixtures as Fixtures;

const STATUS_ADMIN = '/api/v1/monitoring/portal-admin';

const STATUS_PORTAL = '/api/v1/portal';

beforeEach(fn () => Storage::fake('local'));

/** A 1×1 PNG. */
function tinyPng(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
}

/** Signs in as someone else (the Sanctum guard keeps the previous user otherwise). */
function signIn(User $user): User
{
    Auth::forgetGuards();
    test()->actingAs($user);

    return $user;
}

function teamDeveloper(User $admin): User
{
    return User::factory()->forRole(roleNamed($admin, DefaultRoles::DEVELOPER))->create();
}

/** Clientes (RF-02) creates the client; monitoring reads it and manages its portal users (ADR-0002 §12). */
function newClient(string $name): int
{
    return Client::query()->create(['company_id' => auth()->user()->company_id, 'legal_name' => $name])->id;
}

/**
 * Proyectos (RF-03) creates the project (and its slug); the monitoring form then sets whatever else was
 * sent. Answers with the project as monitoring's admin shows it.
 */
function newProject(int $clientId, array $input): TestResponse
{
    $project = app(CreateProject::class)(auth()->user(),
        ['client_id' => $clientId, 'name' => $input['name'], 'code_prefix' => $input['code_prefix'], 'type' => 'bag']);
    $path = STATUS_ADMIN."/projects/{$project->id}";
    $extra = Arr::except($input, ['name', 'code_prefix']);

    return $extra === [] ? test()->getJson($path) : test()->putJson($path, ['slug' => $project->slug, ...$extra]);
}

// httpapi/portal_test.go TestClientPortalFlow
it('sets up a client portal and shows the client only its parts (RF-13.07, CA-13.13)', function () {
    $admin = signIn(adminUser('admin@nexosmart.com.ar'));
    Http::fake(['https://playinn.test/*' => Http::response('hola')]);
    // A server named and addressed like something a client must never see.
    $server = Fixtures::server($admin, 'srv-secreto', ['ip' => '203.0.113.9']);
    Fixtures::snapshot($server, 'supervisor', Fixtures::fixture('clientes', 'supervisor'));

    $clientId = newClient('Playinn');
    $this->postJson(STATUS_ADMIN."/clients/{$clientId}/users", ['email' => 'Dueno@Playinn.com'])
        ->assertCreated()->assertJsonPath('message', 'Usuario creado.')
        ->assertJsonPath('data.email', 'dueno@playinn.com')->assertJsonPath('data.client.name', 'Playinn');
    $projectId = newProject($clientId, ['name' => 'Playinn Café', 'code_prefix' => 'playinn'])->assertOk()
        ->assertJsonPath('data.slug', 'playinn-cafe')->assertJsonPath('data.code_prefix', 'playinn')->assertJsonPath('data.active', true)
        ->json('data.id');
    $projectPath = STATUS_ADMIN."/projects/{$projectId}";

    // Name and code belong to Proyectos (RF-03): this form ignores them.
    $this->putJson($projectPath, ['name' => 'Otro', 'slug' => 'playinn', 'code_prefix' => 'otro', 'primary_color' => '#E11D48',
        'domain' => 'Playinn.com', 'domain_expires_manual' => '2027-03-01', 'active' => true])
        ->assertOk()->assertJsonPath('message', 'Proyecto actualizado.')
        ->assertJsonPath('data.primary_color', '#e11d48')->assertJsonPath('data.domain', 'playinn.com')
        ->assertJsonPath('data.domain_expires_manual', '2027-03-01')
        ->assertJsonPath('data.name', 'Playinn Café')->assertJsonPath('data.code_prefix', 'playinn');

    $this->putJson("{$projectPath}/logo", ['content_type' => 'image/svg+xml', 'data_base64' => base64_encode('<svg/>')])
        ->assertUnprocessable()->assertJsonPath('errors.logo.0', 'El logo tiene que ser PNG, JPG o WebP.');
    $this->putJson("{$projectPath}/logo", ['content_type' => 'image/png', 'data_base64' => base64_encode('<html>')])->assertUnprocessable();
    $this->putJson("{$projectPath}/logo", ['content_type' => 'image/png', 'data_base64' => base64_encode(base64_decode(tinyPng()).str_repeat("\0", 262144))])
        ->assertUnprocessable()->assertJsonPath('errors.logo.0', 'El logo no puede pasar de 256 KB.');
    $this->putJson("{$projectPath}/logo", ['content_type' => 'image/png', 'data_base64' => tinyPng()])
        ->assertOk()->assertJsonPath('message', 'Logo actualizado.')->assertJsonPath('data.has_logo', true);

    $this->postJson("{$projectPath}/components", ['kind' => 'http', 'label' => 'Sitio', 'url' => 'ftp://x'])
        ->assertUnprocessable()->assertJsonPath('errors.url.0', 'Ingresá una URL completa, por ejemplo https://api.playinn.com/health.');
    $this->postJson("{$projectPath}/components", ['kind' => 'http', 'label' => 'Sitio', 'url' => 'https://playinn.test/', 'parts' => ['api']])
        ->assertUnprocessable()->assertJsonPath('errors.parts.0', 'Elegí entre Web, App y CMS.');
    // Without parts a web URL goes to the part its host names; the workers serve the app and the CMS.
    $front = $this->postJson("{$projectPath}/components", ['kind' => 'http', 'label' => 'Sitio web', 'url' => 'https://playinn.test/'])
        ->assertCreated()->assertJsonPath('message', 'Componente creado.')->assertJsonPath('data.parts', ['web'])->json('data');
    $workers = $this->postJson("{$projectPath}/components", ['kind' => 'supervisor', 'label' => 'Workers', 'server_id' => $server->id,
        'target' => 'playinn-queue', 'position' => 1, 'parts' => ['cms', 'app', 'app']])
        ->assertCreated()->assertJsonPath('data.parts', ['app', 'cms'])->assertJsonPath('data.server_name', 'srv-secreto')->json('data');
    $this->putJson(STATUS_ADMIN."/components/{$workers['id']}", ['kind' => 'supervisor', 'label' => 'Workers', 'server_id' => $server->id,
        'target' => 'playinn-queue', 'position' => 1])->assertOk()->assertJsonPath('data.parts', ['app', 'cms']);
    foreach ([$front, $workers] as $component) {
        $this->postJson(STATUS_ADMIN."/components/{$component['id']}/check")->assertOk()->assertJsonPath('data.status', 'operational');
    }
    expect($this->getJson(STATUS_ADMIN.'/targets')->assertOk()->getContent())->toContain('"playinn-queue"');

    // Another client's project, to try to reach it from the first one.
    newProject(newClient('Otro'), ['name' => 'Ajeno', 'slug' => 'ajeno', 'code_prefix' => 'ajeno'])->assertOk();

    // The team lists every project across clients, with its owner and logo (D-048).
    $rows = $this->getJson(STATUS_ADMIN.'/projects')->assertOk()->json('data');
    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['name' => 'Ajeno', 'client_name' => 'Otro', 'logo_url' => null])
        ->and($rows[1])->toMatchArray(['name' => 'Playinn Café', 'client_name' => 'Playinn', 'components' => 2, 'status' => 'operational'])
        ->and($rows[1]['logo_url'])->toStartWith("api/portal-admin/projects/{$projectId}/logo?v=");

    // The client sees its project.
    $owner = signIn(User::query()->where('email', 'dueno@playinn.com')->sole());
    $cards = $this->getJson(STATUS_PORTAL.'/projects')->assertOk();
    expect($cards->json('data'))->toHaveCount(1)
        ->and($cards->json('data.0'))->toMatchArray(['slug' => 'playinn', 'status' => 'operational', 'incident' => false])
        ->and($cards->json('data.0.logo_url'))->toStartWith('api/portal/projects/playinn/logo?v=');
    $detail = $this->getJson(STATUS_PORTAL.'/projects/playinn')->assertOk()
        ->assertJsonPath('data.phrase', 'Todo funciona normalmente')->assertJsonPath('data.client_name', 'Playinn')
        ->assertJsonPath('data.components.1.label', 'App')->assertJsonPath('data.domain.name', 'playinn.com')
        ->assertJsonCount(3, 'data.components')->assertJsonCount(90, 'data.uptime.days');
    expect($detail->json('data.domain.expires_at'))->toBe('2027-03-01T12:00:00Z');
    foreach ([$cards, $detail] as $response) {
        foreach (['srv-secreto', '203.0.113.9', 'playinn-queue', '127.0.0.1', 'supervisor', 'Workers'] as $secret) {
            expect($response->getContent())->not->toContain($secret);
        }
    }
    $this->get(STATUS_PORTAL.'/projects/playinn/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->getJson(STATUS_PORTAL.'/projects/ajeno')->assertNotFound();
    $this->getJson(STATUS_PORTAL.'/projects/playinn/response-times?range=1y')->assertUnprocessable()->assertJsonPath('errors.range.0', 'Usá 24h, 7d o 30d.');

    // The team's preview is the same page.
    signIn($admin);
    $this->getJson("{$projectPath}/preview")->assertOk()->assertJsonPath('data.phrase', $detail->json('data.phrase'));
    $this->get("{$projectPath}/logo")->assertOk()->assertHeader('Content-Type', 'image/png');
    // Client users never show up among the team's users.
    expect($this->getJson('/api/v1/monitoring/users')->assertOk()->getContent())->not->toContain('dueno@playinn.com');

    // Deactivating the client (in Clientes, RF-02) signs its users out.
    $this->putJson("/api/v1/clients/{$clientId}/status", ['status' => 'inactive'])->assertOk();
    $this->getJson(STATUS_ADMIN."/clients/{$clientId}")->assertOk()->assertJsonPath('data.client.active', false);
    signIn($owner->fresh());
    $this->getJson(STATUS_PORTAL.'/projects')->assertUnauthorized();
});

it('lists clients with their counts and a client with its users and projects', function () {
    signIn(adminUser());
    $clientId = newClient('Playinn');
    $this->postJson(STATUS_ADMIN."/clients/{$clientId}/users", ['email' => 'uno@playinn.com'])->assertCreated();
    newProject($clientId, ['name' => 'Playinn', 'code_prefix' => 'playinn'])->assertOk();
    // An internal project (no client) has no status page: monitoring never lists it.
    $internal = app(CreateProject::class)(auth()->user(), ['name' => 'Interno', 'code_prefix' => 'interno', 'type' => 'internal']);

    $this->getJson(STATUS_ADMIN.'/projects')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(STATUS_ADMIN."/projects/{$internal->id}")->assertNotFound();
    $this->getJson(STATUS_ADMIN."/projects/{$internal->id}/preview")->assertNotFound();
    $this->getJson(STATUS_ADMIN.'/clients')->assertOk()->assertJsonPath('data.0', fn (array $row) => $row['name'] === 'Playinn' && $row['users'] === 1 && $row['projects'] === 1);
    $this->getJson(STATUS_ADMIN."/clients/{$clientId}")->assertOk()
        ->assertJsonPath('data.client.users', 1)->assertJsonPath('data.users.0.email', 'uno@playinn.com')
        ->assertJsonPath('data.users.0.is_admin', false)->assertJsonPath('data.projects.0.slug', 'playinn')
        ->assertJsonPath('data.projects.0.vulns', ['critical' => 0, 'high' => 0, 'moderate' => 0, 'low' => 0]);
});

it('stores client users lowercased, refuses repeated addresses and manages them per client', function () {
    $admin = signIn(adminUser());
    $clientId = newClient('Playinn');
    $otherId = newClient('Otro');

    $userId = $this->postJson(STATUS_ADMIN."/clients/{$clientId}/users", ['email' => '  Juan.Perez@Playinn.COM '])->assertCreated()->json('data.id');
    $user = User::query()->findOrFail($userId);
    expect($user->email)->toBe('juan.perez@playinn.com') // the portal login matches it byte for byte (D-076)
        ->and([$user->first_name, $user->last_name])->toBe(['Juan', 'Perez'])
        ->and($user->client_id)->toBe($clientId)
        ->and($user->role->name)->toBe(DefaultRoles::CLIENT)
        ->and($user->getAuthPassword())->toBeNull();

    $this->postJson(STATUS_ADMIN."/clients/{$clientId}/users", ['email' => 'JUAN.PEREZ@playinn.com'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Ese mail ya está dado de alta.');
    $this->postJson(STATUS_ADMIN."/clients/{$clientId}/users", ['email' => 'nope'])->assertUnprocessable()->assertJsonPath('errors.email.0', 'Ingresá un mail válido.');
    $this->putJson(STATUS_ADMIN."/clients/{$otherId}/users/{$userId}", ['active' => false])->assertNotFound();
    $this->putJson(STATUS_ADMIN."/clients/{$clientId}/users/{$admin->id}", ['active' => false])->assertNotFound();
    $this->putJson(STATUS_ADMIN."/clients/{$clientId}/users/{$userId}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
    $this->deleteJson(STATUS_ADMIN."/clients/{$clientId}/users/{$userId}")->assertOk()->assertJsonPath('message', 'Usuario eliminado.');
    expect(User::query()->find($userId))->toBeNull();
});

// ADR-0002 §12: the client is deleted in Clientes (RF-02), and its portal users go with it.
it('deletes only a client without projects, with its portal users (CA-02.01)', function () {
    signIn(adminUser());
    $withProjects = newClient('Playinn');
    newProject($withProjects, ['name' => 'Playinn', 'code_prefix' => 'playinn'])->assertOk();
    $empty = newClient('Vacío');
    $this->postJson(STATUS_ADMIN."/clients/{$empty}/users", ['email' => 'uno@vacio.com'])->assertCreated();

    $this->deleteJson("/api/v1/clients/{$withProjects}")->assertUnprocessable()->assertJsonPath('data.code', 'client_has_projects');
    $this->deleteJson("/api/v1/clients/{$empty}")->assertOk();
    expect(Client::query()->find($empty))->toBeNull()->and(User::query()->where('email', 'uno@vacio.com')->exists())->toBeFalse();
    $this->getJson(STATUS_ADMIN.'/clients')->assertOk()->assertJsonCount(1, 'data');
});

it('derives the slug like monitoreo and reserves "login" for the portal', function () {
    signIn(adminUser());
    $clientId = newClient('Playinn');

    // Every project created in Proyectos gets one (CreateProject), already off the reserved ones.
    newProject($clientId, ['name' => 'Login', 'code_prefix' => 'login'])->assertOk()->assertJsonPath('data.slug', 'login-2');
    newProject($clientId, ['name' => 'Login', 'code_prefix' => 'login3'])->assertOk()->assertJsonPath('data.slug', 'login-3');
    newProject($clientId, ['name' => 'Algo', 'slug' => 'login', 'code_prefix' => 'algo'])
        ->assertUnprocessable()->assertJsonPath('errors.slug.0', 'Ese identificador ya está en uso.');
    $id = newProject($clientId, ['name' => 'Café Nueva Córdoba', 'code_prefix' => 'cafe'])->assertOk()
        ->assertJsonPath('data.slug', 'cafe-nueva-cordoba')->json('data.id');
    $this->putJson(STATUS_ADMIN."/projects/{$id}", ['slug' => 'login'])
        ->assertUnprocessable()->assertJsonPath('errors.slug.0', 'Ese identificador ya está en uso.');
    $this->putJson(STATUS_ADMIN."/projects/{$id}", ['slug' => 'login-2'])
        ->assertUnprocessable()->assertJsonPath('errors.slug.0', 'Ese identificador ya está en uso.');
});

// httpapi/destination_token_test.go (D-093 H3)
it('keeps the errors token write-only and asks for it again when the URL moves', function () {
    signIn(adminUser());
    $projectId = newProject(newClient('Playinn'), ['name' => 'Playinn', 'code_prefix' => 'playinn'])->json('data.id');
    $path = STATUS_ADMIN."/projects/{$projectId}";
    $form = fn (?string $url, ?string $token = null) => ['name' => 'Playinn', 'slug' => 'playinn', 'code_prefix' => 'playinn',
        'primary_color' => null, 'domain' => null, 'domain_expires_manual' => null, 'active' => true, 'errors_url' => $url,
        ...($token === null ? [] : ['errors_token' => $token])];
    $token = 'token-del-proyecto';

    $this->putJson($path, $form(null, $token))->assertUnprocessable()->assertJsonPath('errors.errors_token.0', 'Cargá también la URL.');
    $this->putJson($path, $form('http://api.playinn.com/api/errors', $token))->assertUnprocessable()
        ->assertJsonPath('errors.errors_url.0', 'Usá la URL https completa del /api/errors del proyecto.');
    $saved = $this->putJson($path, $form('https://api.playinn.com/api/errors', $token))->assertOk()->assertJsonPath('data.has_errors_token', true);
    $this->putJson($path, $form('https://api.playinn.com/api/errors'))->assertOk()->assertJsonPath('data.has_errors_token', true);
    $this->putJson($path, $form('https://otro.example.com/api/errors'))->assertUnprocessable()
        ->assertJsonPath('errors.errors_token.0', 'Cambiaste la URL: volvé a cargar el token.');
    $this->putJson($path, $form('https://otro.example.com/api/errors', $token))->assertOk();
    expect(Project::query()->findOrFail($projectId)->errors_token)->toBe($token);

    // Never in a response, the database or the audit trail.
    foreach ([$saved, $this->getJson($path), $this->getJson(STATUS_ADMIN.'/projects')] as $response) {
        expect($response->getContent())->not->toContain($token)->not->toContain('"errors_token"');
    }
    expect(DB::table('projects')->where('id', $projectId)->value('errors_token'))->not->toContain($token)
        ->and(ActivityLog::query()->get()->toJson())->not->toContain($token);

    // Clearing the URL clears the token.
    $this->putJson($path, $form(''))->assertOk()->assertJsonPath('data.errors_url', null)->assertJsonPath('data.has_errors_token', false);
});

// httpapi/vulnerabilities_test.go (D-053)
it('assigns developers and shows each project its vulnerabilities', function () {
    $admin = signIn(adminUser('admin@nexosmart.com.ar'));
    $dev = User::factory()->forRole(roleNamed($admin, DefaultRoles::DEVELOPER))->create(['email' => 'dev@nexosmart.com.ar']);
    $server = Fixtures::server($admin, 'Servidor 5');
    Fixtures::snapshot($server, 'projects', Fixtures::fixture('reaquila', 'projects'));
    Fixtures::snapshot($server, 'vulnerabilities', Fixtures::fixture('reaquila', 'vulnerabilities'));
    $clientId = newClient('Varios');
    $this->postJson(STATUS_ADMIN."/clients/{$clientId}/users", ['email' => 'dueno@varios.com'])->assertCreated();
    $create = function (string $name, string $slug, string ...$folders) use ($clientId, $server) {
        $path = STATUS_ADMIN.'/projects/'.newProject($clientId, ['name' => $name, 'slug' => $slug, 'code_prefix' => $slug])->json('data.id');
        if ($folders !== []) {
            $this->putJson("{$path}/folders", ['folders' => array_map(fn (string $folder) => ['server_id' => $server->id, 'path' => $folder], $folders)])->assertOk();
        }

        return $path;
    };
    $tfp = $create('TuFarmaPoint', 'tfp', '/var/www/api2025.tufarmapoint.com/tfp-api', '/var/www/api2025.tufarmapoint.com/tfp_api');
    $olimpo = $create('Club Olimpo', 'olimpo', '/var/www/clubolimpo.com.ar');
    $create('Libre', 'libre');
    $setDevelopers = function (string $path, int $lead, int ...$secondary) {
        $project = $this->getJson($path)->json('data');

        return $this->putJson($path, ['name' => $project['name'], 'slug' => $project['slug'], 'code_prefix' => $project['code_prefix'],
            'developers' => ['lead_user_id' => $lead, 'secondary_user_ids' => [...$secondary, $lead]]]);
    };

    $setDevelopers($tfp, $admin->id)->assertOk();
    $setDevelopers($olimpo, $dev->id, $admin->id)->assertOk()
        ->assertJsonPath('data.developers', [
            ['user_id' => $dev->id, 'email' => 'dev@nexosmart.com.ar', 'role' => 'lead'],
            ['user_id' => $admin->id, 'email' => 'admin@nexosmart.com.ar', 'role' => 'secondary'],
        ]);
    $olimpoProject = Project::query()->where('slug', 'olimpo')->sole();
    expect($olimpoProject->leader_user_id)->toBe($dev->id)
        ->and($olimpoProject->members()->pluck('users.id')->sort()->values()->all())->toBe(collect([$admin->id, $dev->id])->sort()->values()->all());
    $clientUser = User::query()->where('email', 'dueno@varios.com')->sole();
    $setDevelopers($tfp, $clientUser->id)->assertUnprocessable()->assertJsonPath('errors.developers.0', 'Elegí usuarios activos del equipo.');

    $counts = collect($this->getJson(STATUS_ADMIN.'/projects')->assertOk()->json('data'))->pluck('vulns', 'slug');
    expect($counts['tfp'])->toBe(['critical' => 1, 'high' => 1, 'moderate' => 1, 'low' => 0])
        ->and($counts['olimpo'])->toBe(['critical' => 0, 'high' => 1, 'moderate' => 0, 'low' => 1])
        ->and($counts['libre'])->toBe(['critical' => 0, 'high' => 0, 'moderate' => 0, 'low' => 0]);

    signIn($dev);
    $this->putJson($tfp, ['name' => 'x', 'slug' => 'tfp', 'code_prefix' => 'tfp', 'developers' => ['lead_user_id' => $dev->id]])->assertForbidden();
    $detail = $this->getJson("{$olimpo}/vulnerabilities")->assertOk()->json('data');
    expect($detail['dirs'])->toHaveCount(2)
        ->and($detail['servers'])->toHaveCount(1)->and($detail['servers'][0]['has_scan'])->toBeTrue()
        ->and($detail['dirs'][1]['path'])->toBe('/var/www/clubolimpo.com.ar/olimpo_app')
        ->and($detail['dirs'][1]['error'] ?? '')->not->toBe(''); // a failed audit is shown, not hidden as clean
});

// httpapi/portal_history_test.go TestIncidentsLastUpdateAndResponseTimes
it('links folders, suggests from them and shows incidents with notes (D-042, D-044)', function () {
    $admin = signIn(adminUser('admin@nexosmart.com.ar'));
    $server = Fixtures::server($admin, 'clientes', ['ip' => '51.255.36.83']);
    foreach (['projects', 'sites', 'supervisor'] as $section) {
        Fixtures::snapshot($server, $section, Fixtures::fixture('clientes', $section));
    }
    $clientId = newClient('Tennis Open League');
    $this->postJson(STATUS_ADMIN."/clients/{$clientId}/users", ['email' => 'dueno@tol.com'])->assertCreated();
    $projectId = newProject($clientId, ['name' => 'TOL', 'slug' => 'tol', 'code_prefix' => 'tol'])->json('data.id');
    $projectPath = STATUS_ADMIN."/projects/{$projectId}";
    $otherPath = STATUS_ADMIN.'/projects/'.newProject(newClient('Medplace'), ['name' => 'Medplace App', 'code_prefix' => 'medplace'])->json('data.id');

    $root = '/var/www/clientes.nexosmart.com.ar';
    [$front, $back, $medplace] = ["{$root}/tol_frontend_web", "{$root}/tennisopenleague_backend", "{$root}/medplace-backend"];
    $folder = fn (string $path) => ['server_id' => $server->id, 'path' => $path];
    $this->putJson("{$otherPath}/folders", ['folders' => [$folder($medplace)]])->assertOk();
    // A folder belongs to one client project; folders outside the inventory are typos.
    $bad = $this->putJson("{$projectPath}/folders", ['folders' => [$folder($front), $folder($medplace), $folder('/var/www/no-existe')]])
        ->assertUnprocessable();
    expect($bad->json('errors.folders'))->toBe([
        "{$medplace} ya es de Medplace (Medplace App).",
        '/var/www/no-existe no está en el inventario de ese servidor.',
    ]);
    $saved = $this->putJson("{$projectPath}/folders", ['folders' => [$folder($front), $folder($back)]])
        ->assertOk()->assertJsonPath('message', 'Carpetas guardadas.')->assertJsonCount(2, 'data.folders');
    $frontView = collect($saved->json('data.folders'))->firstWhere('path', $front);
    expect($frontView)->toMatchArray(['name' => 'tol_frontend_web', 'last_update_at' => '2026-10-03T18:20:11Z', 'types' => ['nextjs']]);
    $linked = collect($this->getJson(STATUS_ADMIN.'/folders')->assertOk()->json('data'))->firstWhere('path', $front)['linked'];
    expect($linked)->toBe(['project_id' => $projectId, 'project_name' => 'TOL', 'client_id' => $clientId, 'client_name' => 'Tennis Open League'])
        ->and(collect($this->getJson('/api/v1/monitoring/inventory/projects')->json('data'))->firstWhere('path', $front)['linked']['client_name'])
        ->toBe('Tennis Open League');
    // The backend's Supervisor programs come as suggestions.
    expect($this->getJson("{$projectPath}/suggestions")->assertOk()->getContent())->toContain('"target":"tennisopenleague.laravel-queue"');

    $component = Fixtures::saved(Project::query()->findOrFail($projectId), ['url' => 'https://tol.example', 'created_at' => now()->subHour()]);
    Period::query()->create(['company_id' => $admin->company_id, 'component_id' => $component->id, 'status' => 'down',
        'started_at' => now()->subMinutes(50), 'ended_at' => now()->subMinutes(40)]);
    foreach ([0, 1, 2] as $i) {
        DB::table('status_checks')->insert(['company_id' => $admin->company_id, 'component_id' => $component->id,
            'checked_at' => now()->subMinutes(10 + $i), 'ok' => true, 'latency_ms' => 100 + $i * 50]);
    }

    $incidentId = $this->getJson("{$projectPath}/incidents")->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
    $note = $this->postJson(STATUS_ADMIN."/incidents/{$incidentId}/notes", ['body' => "Resuelto: se reinició\u{0007} el servicio."])
        ->assertCreated()->assertJsonPath('message', 'Nota agregada.')->assertJsonPath('data.author', 'admin@nexosmart.com.ar')
        ->assertJsonPath('data.body', 'Resuelto: se reinició el servicio.');
    $this->postJson(STATUS_ADMIN."/incidents/{$incidentId}/notes", ['body' => str_repeat('a', 2001)])
        ->assertUnprocessable()->assertJsonPath('errors.body.0', 'Escribí la nota (hasta 2000 caracteres).');
    $degraded = Period::query()->create(['company_id' => $admin->company_id, 'component_id' => $component->id, 'status' => 'degraded', 'started_at' => now()]);
    $this->postJson(STATUS_ADMIN."/incidents/{$degraded->id}/notes", ['body' => 'x'])->assertNotFound();

    signIn(User::query()->where('email', 'dueno@tol.com')->sole());
    $status = $this->getJson(STATUS_PORTAL.'/projects/tol')->assertOk()
        ->assertJsonPath('data.incidents.0.status', 'resolved')->assertJsonCount(1, 'data.incidents.0.notes')
        ->assertJsonPath('data.last_update.at', '2026-10-03T18:20:11Z');
    expect($status->json('data.incidents.0.title'))->toStartWith('Web no respondió')
        ->and($status->json('data.incidents.0.notes.0'))->not->toHaveKey('author')
        ->and($status->getContent())->not->toContain('admin@nexosmart.com.ar')->not->toContain('tol_frontend_web');
    $this->getJson(STATUS_PORTAL.'/projects/tol/response-times?range=24h')->assertOk()
        ->assertJsonPath('data.resolution', '5m')->assertJsonPath('data.series.0.label', 'Web')
        ->assertJson(fn ($json) => $json->has('data.series.0.points.0')->etc());

    signIn($admin);
    $this->deleteJson(STATUS_ADMIN."/incident-notes/{$note->json('data.id')}")->assertOk()->assertJsonPath('message', 'Nota eliminada.');
    $this->deleteJson(STATUS_ADMIN."/incident-notes/{$note->json('data.id')}")->assertNotFound();
});

// httpapi/component_owner_test.go (D-089)
it('tells same-named pm2 apps apart by their user and keeps the one picked', function () {
    $admin = signIn(adminUser());
    $server = Fixtures::server($admin, 'clientes');
    Fixtures::snapshot($server, 'pm2', ['daemons' => [
        ['user' => 'eve', 'processes' => [['name' => 'api-b', 'status' => 'online']]],
        ['user' => 'clientb', 'processes' => [['name' => 'api-b', 'status' => 'online'], ['name' => 'api-b', 'status' => 'online']]],
    ]]);

    $this->getJson(STATUS_ADMIN.'/targets')->assertOk()
        ->assertJsonPath('data.0.pm2', [['name' => 'api-b', 'owner' => 'clientb'], ['name' => 'api-b', 'owner' => 'eve']]);
    $path = STATUS_ADMIN.'/projects/'.newProject(newClient('Cliente B'), ['name' => 'B', 'code_prefix' => 'bb'])->json('data.id').'/components';
    $component = $this->postJson($path, ['kind' => 'pm2', 'label' => 'API', 'server_id' => $server->id, 'target' => 'api-b', 'target_owner' => 'clientb'])
        ->assertCreated()->assertJsonPath('data.target_owner', 'clientb')->json('data');
    $this->postJson(STATUS_ADMIN."/components/{$component['id']}/check")->assertOk()->assertJsonPath('data.detail', '2 de 2 instancias online');
    // Other kinds have no owner.
    $this->postJson($path, ['kind' => 'timer', 'label' => 'Backups', 'server_id' => $server->id, 'target' => 'backup.timer', 'target_owner' => 'root'])
        ->assertCreated()->assertJsonPath('data.target_owner', null)->assertJsonPath('data.parts', []);
    $this->postJson($path, ['kind' => 'pm2', 'label' => 'API'])->assertUnprocessable()
        ->assertJsonPath('errors.server_id.0', 'Elegí el servidor.')->assertJsonPath('errors.target.0', 'Elegí qué chequear.');
    $this->postJson($path, ['kind' => 'nope', 'label' => 'x'])->assertUnprocessable()->assertJsonPath('errors.kind.0', 'Tipo inválido.');
    $this->postJson(STATUS_ADMIN."/components/{$component['id']}/pagespeed")->assertUnprocessable()
        ->assertJsonPath('message', 'PageSpeed mide solo los componentes web.');
});

it('starts the state over and closes the open period when a component is reconfigured', function () {
    $admin = signIn(adminUser());
    $component = Fixtures::saved(Fixtures::project($admin), ['url' => 'https://old.example/', 'status' => 'down', 'status_since' => now(), 'bad_streak' => 12]);
    $open = Period::query()->create(['company_id' => $admin->company_id, 'component_id' => $component->id, 'status' => 'down', 'started_at' => now()]);

    $this->putJson(STATUS_ADMIN."/components/{$component->id}", ['kind' => 'http', 'label' => 'Sitio web', 'url' => 'https://new.example/'])
        ->assertOk()->assertJsonPath('message', 'Componente actualizado.')
        ->assertJsonPath('data.status', 'unknown')->assertJsonPath('data.parts', ['web']);
    expect($open->fresh()->ended_at)->not->toBeNull()->and($component->fresh()->bad_streak)->toBe(0);
    $this->deleteJson(STATUS_ADMIN."/components/{$component->id}")->assertOk()->assertJsonPath('message', 'Componente eliminado.');
    expect(Component::query()->count())->toBe(0)->and(Period::query()->count())->toBe(0);
});

it('lets monitoring.view read and only monitoring.manage change (ADR-0002 §10)', function () {
    $admin = signIn(adminUser());
    $clientId = newClient('Playinn');
    $projectId = newProject($clientId, ['name' => 'Playinn', 'code_prefix' => 'playinn'])->json('data.id');

    signIn(teamDeveloper($admin));
    foreach (['/clients', '/projects', "/projects/{$projectId}", "/projects/{$projectId}/preview", "/projects/{$projectId}/incidents",
        "/projects/{$projectId}/suggestions", "/projects/{$projectId}/vulnerabilities", "/projects/{$projectId}/response-times", '/folders', '/targets'] as $read) {
        $this->getJson(STATUS_ADMIN.$read)->assertOk();
    }
    $this->putJson(STATUS_ADMIN."/projects/{$projectId}", ['slug' => 'otro'])->assertForbidden();
    $this->postJson(STATUS_ADMIN."/clients/{$clientId}/users", ['email' => 'x@y.com'])->assertForbidden();
    $this->getJson('/api/v1/monitoring/users')->assertForbidden();
});

it('lists the team users for the developer picker, paged like monitoreo', function () {
    $admin = signIn(adminUser('admin@nexosmart.com.ar'));
    $dev = User::factory()->forRole(roleNamed($admin, DefaultRoles::DEVELOPER))->create(['email' => 'dev@nexosmart.com.ar', 'discord_user_id' => '1403351571303104612']);
    $this->postJson(STATUS_ADMIN.'/clients/'.newClient('Playinn').'/users', ['email' => 'dueno@playinn.com'])->assertCreated();

    $this->getJson('/api/v1/monitoring/users?page=1')->assertOk()
        ->assertJsonPath('meta', ['page' => 1, 'per_page' => 20, 'total' => 2])
        ->assertJsonPath('data.0', fn (array $user) => $user['email'] === 'admin@nexosmart.com.ar' && $user['is_admin'] && $user['active'] && $user['client'] === null)
        ->assertJsonPath('data.1.id', $dev->id)->assertJsonPath('data.1.is_admin', false)->assertJsonPath('data.1.discord_user_id', '1403351571303104612');
    $this->getJson('/api/v1/monitoring/users?page=0')->assertUnprocessable()->assertJsonPath('errors.page.0', 'Página inválida.');
});

it('answers 503 to Discord while there is no bot (Phase 7)', function () {
    signIn(adminUser());
    $clientId = newClient('Playinn');
    $projectId = newProject($clientId, ['name' => 'Playinn', 'code_prefix' => 'playinn'])->json('data.id');

    $this->getJson('/api/v1/monitoring/discord/channels')->assertStatus(503)->assertJsonPath('message', 'El bot de Discord no está configurado.');
    $this->getJson('/api/v1/monitoring/discord/members')->assertStatus(503);
    $this->postJson(STATUS_ADMIN."/projects/{$projectId}/discord-test")->assertStatus(503);
    newProject($clientId, ['name' => 'Otro', 'code_prefix' => 'otro', 'create_discord_channel' => true])
        ->assertUnprocessable()->assertJsonPath('errors.discord_channel_id.0', 'El bot de Discord no está configurado.');
    newProject($clientId, ['name' => 'Otro', 'code_prefix' => 'otro2', 'discord_channel_id' => '1427806155307745290'])
        ->assertUnprocessable()->assertJsonPath('errors.discord_channel_id.0', 'Elegí un canal de la lista.');
});

it('queues a PageSpeed measurement of a web component', function () {
    $admin = signIn(adminUser());
    Http::fake(['*' => Http::response(['lighthouseResult' => ['categories' => ['performance' => ['score' => 0.5]]]])]);
    $component = Fixtures::saved(Fixtures::project($admin), ['url' => 'https://playinn.com/']);

    $this->postJson(STATUS_ADMIN."/components/{$component->id}/pagespeed")->assertStatus(202)
        ->assertJsonPath('message', 'Midiendo: el resultado aparece en un par de minutos.');
    expect(DB::table('status_pagespeed_runs')->where('component_id', $component->id)->pluck('score')->all())->toBe([50, 50]); // sync queue in tests
});

// ADR-0002 §12 (asked by the user): a project is closed in Proyectos, never deleted from monitoring.
it('has no way to delete a project from monitoring', function () {
    signIn(adminUser());
    $projectId = newProject(newClient('Playinn'), ['name' => 'Playinn', 'code_prefix' => 'playinn'])->json('data.id');

    $this->deleteJson(STATUS_ADMIN."/projects/{$projectId}")->assertStatus(405);
    expect(Project::query()->find($projectId))->not->toBeNull();
});

it('shows a client only its projects and the incident notice once the server is down 10 minutes (D-051, CA-13.13)', function () {
    $admin = signIn(adminUser());
    $project = Fixtures::project($admin);
    $server = Fixtures::server($admin);
    Fixtures::saved($project, ['kind' => 'supervisor', 'url' => null, 'server_id' => $server->id, 'target' => 'queue', 'status' => 'operational', 'phrase' => 'Funciona normalmente']);
    $owner = User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))->create(['client_id' => $project->client_id]);
    DB::table('monitoring_alerts')->insert(['company_id' => $admin->company_id, 'server_id' => $server->id, 'rule' => 'server_down', 'target' => '',
        'severity' => 'critical', 'state' => 'firing', 'message' => 'x', 'value' => '', 'first_seen_at' => now()->subMinutes(5), 'last_seen_at' => now()]);

    signIn($owner);
    $this->getJson(STATUS_PORTAL.'/projects')->assertOk()->assertJsonPath('data.0.incident', false);
    $this->travel(6)->minutes();
    $this->getJson(STATUS_PORTAL.'/projects')->assertOk()->assertJsonPath('data.0.incident', true);
    $project->forceFill(['portal_active' => false])->save();
    $this->getJson(STATUS_PORTAL.'/projects')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(STATUS_PORTAL.'/projects/playinn')->assertNotFound();
});
