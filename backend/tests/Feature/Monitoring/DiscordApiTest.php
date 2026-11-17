<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDiscord;
use Tests\Support\ReportFixtures as Reports;

/**
 * Port of monitoreo's httpapi/discord_test.go (D-059, D-060): the pickers' lists, a user's Discord
 * id, a new project's channel, the "Probar" button and a client's report in the project's channel.
 * monitoreo's 400 is newDesk's 422 (ADR-0002 §10).
 */
const ADMIN_API = '/api/v1/monitoring';

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['newdesk.monitoring.notify' => true, 'newdesk.frontend_url' => 'https://panel.test']);
    $this->admin = adminUser('admin@nexosmart.com.ar');
    $this->orlando = User::factory()->forRole(roleNamed($this->admin, DefaultRoles::DEVELOPER))->create(['email' => 'orlando@nexosmart.com.ar']);
    $this->as = function (User $user) {
        Auth::forgetGuards();
        $this->actingAs($user);
    };
    ($this->as)($this->admin);
    $this->clientId = Client::query()->create(['company_id' => $this->admin->company_id, 'legal_name' => 'Playinn'])->id;
    // Proyectos (RF-03) creates the projects; monitoring's form sets their channel (ADR-0002 §12).
    $this->newProject = fn (string $name, string $code, ?int $leaderId = null) => app(CreateProject::class)($this->admin,
        ['client_id' => $this->clientId, 'name' => $name, 'code_prefix' => $code, 'type' => 'bag', 'leader_user_id' => $leaderId]);
});

// TestProjectDiscordChannelThroughTheBot
it('lists channels and members, creates a project\'s channel, tests it and posts the client\'s reports (D-059)', function () {
    // Without a bot token the pickers say so.
    $this->getJson(ADMIN_API.'/discord/channels')->assertStatus(503)->assertJsonPath('message', 'El bot de Discord no está configurado.');

    $discord = FakeDiscord::install([FakeDiscord::CATEGORY]);
    ($this->as)($this->orlando);
    $channels = $this->getJson(ADMIN_API.'/discord/channels')->assertOk()->json('data');
    expect(array_column($channels, 'name'))->toBe(['playinn', 'reaquila']) // never a channel outside the allowed groups
        ->and($channels[0]['can_send'])->toBeTrue()->and($channels[1]['can_send'])->toBeFalse()
        ->and($channels[0]['category'])->toBe('Equipo BBG1');
    $this->getJson(ADMIN_API.'/discord/members')->assertForbidden(); // for admins

    ($this->as)($this->admin);
    $this->getJson(ADMIN_API.'/discord/members')->assertOk()
        ->assertJsonPath('data.1', ['id' => '1077787097219473408', 'username' => 'orlando85.', 'display_name' => 'Orlando']);
    $this->putJson(ADMIN_API."/users/{$this->orlando->id}", ['discord_user_id' => 'abc'])->assertUnprocessable()
        ->assertJsonPath('errors.discord_user_id.0', 'Pegá el ID numérico de Discord (17 a 20 dígitos).');
    $this->putJson(ADMIN_API."/users/{$this->orlando->id}", ['discord_user_id' => '1077787097219473408'])->assertOk()
        ->assertJsonPath('message', 'Usuario actualizado.')->assertJsonPath('data.discord_user_id', '1077787097219473408');
    $this->putJson(ADMIN_API."/users/{$this->orlando->id}", [])->assertOk()->assertJsonPath('data.discord_user_id', '1077787097219473408'); // absent keeps it
    expect(ActivityLog::query()->where('event', 'user.discord_id_changed')->count())->toBe(1);

    // The project's form: channel validation, then the bot creates one that greets its lead.
    $playinn = ($this->newProject)('Playinn', 'playinn', $this->orlando->id);
    $project = ADMIN_API."/portal-admin/projects/{$playinn->id}";
    $edit = fn (array $extra) => $this->putJson($project, ['slug' => 'playinn', 'active' => true, ...$extra]);
    foreach ([
        ['discord_channel_id' => 'abc'],
        ['discord_channel_id' => '1000000000000000009'], // exists, but outside the allowed groups
        ['discord_channel_id' => FakeDiscord::PLAYINN, 'create_discord_channel' => true],
    ] as $bad) {
        $edit($bad)->assertUnprocessable();
    }
    $edit(['create_discord_channel' => true])->assertOk()->assertJsonPath('message', 'Proyecto actualizado.')
        ->assertJsonPath('data.discord_channel_id', FakeDiscord::CREATED)->assertJsonPath('data.developers.0.role', 'lead');
    expect($discord->created)->toHaveCount(1)->and($discord->created[0])->toMatchArray(['name' => 'monitoreo-playinn', 'parent_id' => FakeDiscord::CATEGORY]);
    $welcome = $discord->take();
    expect($welcome)->toHaveCount(1)->and($welcome[0]['channel'])->toBe(FakeDiscord::CREATED)
        ->and($welcome[0]['content'])->toContain('<@1077787097219473408>')
        ->and($welcome[0]['allowed_mentions'])->toBe(['parse' => [], 'users' => ['1077787097219473408']]);

    // A channel the bot cannot create: the rest of the form is saved anyway.
    $staging = ($this->newProject)('Playinn staging', 'playinnstg');
    config(['newdesk.monitoring.discord.guild_id' => '1111111111111111111']);
    $this->putJson(ADMIN_API."/portal-admin/projects/{$staging->id}", ['slug' => $staging->slug, 'create_discord_channel' => true])->assertOk()
        ->assertJsonPath('message', 'Proyecto actualizado, pero no se pudo crear el canal de Discord: Discord respondió 404: ese canal no existe.')
        ->assertJsonPath('data.discord_channel_id', null);
    config(['newdesk.monitoring.discord.guild_id' => FakeDiscord::GUILD]);

    // Editing the channel: absent keeps it, null clears it, a listed id sets it.
    $edit([])->assertOk()->assertJsonPath('data.discord_channel_id', FakeDiscord::CREATED);
    $edit(['discord_channel_id' => null])->assertOk()->assertJsonPath('data.discord_channel_id', null);
    $edit(['discord_channel_id' => FakeDiscord::PLAYINN])->assertOk()->assertJsonPath('data.discord_channel_id', FakeDiscord::PLAYINN);
    $edit(['discord_channel_id' => 'general'])->assertUnprocessable()->assertJsonPath('errors.discord_channel_id.0', 'Elegí un canal de la lista.');

    // "Probar", then a channel Discord no longer has.
    $this->postJson("{$project}/discord-test")->assertOk()->assertJsonPath('message', 'Mensaje de prueba enviado.');
    expect(array_column($discord->take(), 'channel'))->toBe([FakeDiscord::PLAYINN]);
    $discord->missing = [FakeDiscord::PLAYINN];
    expect($this->postJson("{$project}/discord-test")->assertStatus(502)->json('message'))->toContain('404');
    $discord->missing = [];

    // A client's report reaches the project's channel and pings its lead.
    $playinn = Project::query()->where('slug', 'playinn')->sole();
    Reports::signIn(Reports::clientUser($playinn, 'dueno@playinn.com'));
    Reports::post('playinn', ['description' => 'No carga la página de pagos desde hoy a la mañana.', 'urgency' => 'blocking'])->assertCreated();
    $posts = $discord->take();
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe(FakeDiscord::PLAYINN)
        ->and($posts[0]['content'])->toStartWith('🚨 **Playinn** · **URGENTE** · Reporte nuevo de dueno\@playinn.com · No puedo trabajar')
        ->toContain('> No carga la página de pagos desde hoy a la mañana.', '<@1077787097219473408>', '<https://panel.test/monitoreo/reportes/');

    ($this->as)($this->admin);
    $edit(['discord_channel_id' => null])->assertOk();
    $this->postJson("{$project}/discord-test")->assertUnprocessable()->assertJsonPath('message', 'El proyecto no tiene canal de Discord.');
});

it('creates no channel while newDesk runs next to monitoreo, but still reads Discord and sends "Probar" (CA-13.20)', function () {
    config(['newdesk.monitoring.notify' => false]);
    $discord = FakeDiscord::install([FakeDiscord::CATEGORY]);
    $playinn = ($this->newProject)('Playinn', 'playinn');
    $form = fn (array $extra) => $this->putJson(ADMIN_API."/portal-admin/projects/{$playinn->id}", ['slug' => $playinn->slug, ...$extra]);

    $form(['create_discord_channel' => true])->assertUnprocessable()
        ->assertJsonPath('errors.discord_channel_id.0', 'Los avisos a Discord están apagados mientras convive con monitoreo: elegí un canal existente.');
    expect($discord->created)->toBe([]);

    // Picking an existing channel, and the explicit test message, still work.
    $id = $form(['discord_channel_id' => FakeDiscord::PLAYINN])->assertOk()->json('data.id');
    $this->getJson(ADMIN_API.'/discord/channels')->assertOk();
    $this->postJson(ADMIN_API."/portal-admin/projects/{$id}/discord-test")->assertOk();
    expect($discord->take())->toHaveCount(1);
});

it('sets the Discord id of team users only', function () {
    $client = Reports::clientUser(Project::factory()->create(['company_id' => $this->admin->company_id, 'client_id' => $this->clientId]), 'dueno@playinn.com');
    $other = adminUser('otra@empresa.com');

    $this->putJson(ADMIN_API."/users/{$client->id}", ['discord_user_id' => '1077787097219473408'])->assertNotFound();
    $this->putJson(ADMIN_API."/users/{$other->id}", ['discord_user_id' => '1077787097219473408'])->assertNotFound();
    $this->putJson(ADMIN_API."/users/{$this->orlando->id}", ['discord_user_id' => ''])->assertOk()->assertJsonPath('data.discord_user_id', null);
    ($this->as)($this->orlando);
    $this->putJson(ADMIN_API."/users/{$this->orlando->id}", ['discord_user_id' => null])->assertForbidden();
});
