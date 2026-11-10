<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Support\Actions\PurgeReports;
use App\Domain\Support\Events\ReportChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReportFixtures as Reports;
use Tests\Support\StatusPageFixtures as Fixtures;

/**
 * Port of monitoreo's report_chat_test.go: the chat inside each report (D-049), internal notes
 * (D-051, CA-13.18) and the live events that replace its SSE (Reverb channels).
 */
beforeEach(function () {
    Storage::fake('local');
    Event::fake([ReportChanged::class]);
    $this->admin = adminUser('admin@nexosmart.com.ar');
    $this->project = Fixtures::project($this->admin);
    $this->owner = Reports::clientUser($this->project, 'dueno@playinn.com');
    Reports::signIn($this->owner);
    $this->reportId = Reports::post('playinn')->assertCreated()->json('data.report.id');
    $this->clientPath = "/api/v1/portal/projects/playinn/reports/{$this->reportId}";
    $this->teamPath = "/api/v1/monitoring/portal-admin/reports/{$this->reportId}";
});

/** What each side's list says of the report. */
function chatTeamRow(): array
{
    Reports::signIn(test()->admin);

    return test()->getJson('/api/v1/monitoring/portal-admin/reports')->assertOk()->json('data.0');
}

function chatClientRow(): array
{
    Reports::signIn(test()->owner);

    return test()->getJson('/api/v1/portal/projects/playinn/reports')->assertOk()->json('data.0');
}

function chatAttention(): int
{
    Reports::signIn(test()->admin);

    return test()->getJson('/api/v1/monitoring/portal-admin/reports/attention')->assertOk()->json('data.count');
}

/** The channels the last `$name` event went to. @return list<string> */
function lastReportEventChannels(string $name): array
{
    $last = Event::dispatched(ReportChanged::class, fn (ReportChanged $event) => $event->name === $name)->last()[0];

    return array_map(fn ($channel) => $channel->name, $last->broadcastOn());
}

// httpapi/report_chat_test.go TestReportChat
it('chats inside a report: images, read marks, internal notes and reopening (D-049, D-051)', function () {
    $companyChannel = "private-monitoring.{$this->admin->company_id}";
    $clientChannel = "private-portal.client.{$this->project->client_id}";
    expect(lastReportEventChannels(ReportChanged::REPORT))->toBe([$companyChannel, $clientChannel]);

    // Validation: something to say, at most 3 images, only PNG/JPG/WebP.
    Reports::message($this->clientPath, '  ')->assertUnprocessable()->assertJsonPath('errors.body.0', 'Escribí un mensaje o adjuntá una imagen.');
    Reports::message($this->clientPath, 'mirá', ['<html>no soy imagen</html>'])->assertUnprocessable()
        ->assertJsonPath('errors.images.0', 'Las imágenes tienen que ser PNG, JPG o WebP.');
    Reports::message($this->clientPath, str_repeat('a', 4001))->assertUnprocessable()
        ->assertJsonPath('errors.body.0', 'El mensaje puede tener hasta 4000 caracteres.');

    $sent = Reports::message($this->clientPath, 'Sigue pasando, adjunto la pantalla.', [Reports::png()])->assertCreated()
        ->assertJsonPath('message', 'Mensaje enviado.')
        ->assertJsonPath('data.author', 'dueno@playinn.com')->assertJsonPath('data.author_kind', 'client')
        ->assertJsonMissingPath('data.internal');
    expect($sent->json('data.images'))->toHaveCount(1)
        ->and($sent->json('data.images.0'))->toStartWith("api/portal/projects/playinn/reports/{$this->reportId}/attachments/");
    // Live: the team on its company channel and the client on its own, ids only.
    Event::assertDispatched(ReportChanged::class, fn (ReportChanged $event) => $event->name === 'report.message'
        && $event->broadcastAs() === 'report.message' && $event->broadcastWith() === ['report_id' => $this->reportId, 'project_slug' => 'playinn']);
    expect(lastReportEventChannels(ReportChanged::MESSAGE))->toBe([$companyChannel, $clientChannel]);

    // The team sees it as unread until it opens the report.
    expect(chatTeamRow()['unread'])->toBeTrue()->and(chatAttention())->toBe(1);
    $messages = $this->getJson($this->teamPath)->assertOk()->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.author', 'dueno@playinn.com')->json('data.messages');
    expect(chatTeamRow()['unread'])->toBeFalse();
    $this->putJson($this->teamPath, ['status' => 'in_review'])->assertOk();
    expect(chatAttention())->toBe(0);
    $this->get(Reports::url($messages[0]['images'][0]))->assertOk()->assertHeader('Content-Type', 'image/png');

    // The team answers: at most 3 images, and the client never sees who wrote. A second later than the client's
    // last look (the clock is frozen in tests, TestCase), as it always is for real.
    $this->travel(1)->seconds();
    Reports::message($this->teamPath, 'ok', array_fill(0, 4, Reports::png()))->assertUnprocessable()
        ->assertJsonPath('errors.images', ['Hasta 3 imágenes por mensaje.']);
    Reports::message($this->teamPath, 'Lo estamos viendo, gracias.', [Reports::png(), Reports::png()])->assertCreated()
        ->assertJsonPath('data.author', 'admin@nexosmart.com.ar')->assertJsonCount(2, 'data.images');
    expect(chatClientRow()['unread'])->toBeTrue();
    $thread = $this->getJson($this->clientPath)->assertOk()->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.1.author', 'Equipo nexosmart')->assertJsonPath('data.report.unread', false);
    expect($thread->getContent())->not->toContain('admin@nexosmart.com.ar')->not->toContain('"ip"')
        ->and(chatClientRow()['unread'])->toBeFalse();
    $attachmentUrl = Reports::url($thread->json('data.messages.1.images.0'));
    $this->get($attachmentUrl)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'max-age=3600, private');

    // An internal note: the team sees it, the client never does — not the text, not its image, not even
    // an "unread" mark, not its live event (CA-13.18). A client can't write one.
    Reports::signIn($this->admin);
    $note = Reports::message($this->teamPath, 'Es el timeout del gateway.', [Reports::png()], ['internal' => '1'])->assertCreated()
        ->assertJsonPath('data.internal', true);
    expect(lastReportEventChannels(ReportChanged::MESSAGE))->toBe([$companyChannel]);
    $noteImage = str_replace("api/portal-admin/reports/{$this->reportId}", "api/portal/projects/playinn/reports/{$this->reportId}", $note->json('data.images.0'));
    Reports::signIn($this->owner);
    $this->get(Reports::url($noteImage))->assertNotFound();
    expect(chatClientRow()['unread'])->toBeFalse()
        ->and($this->getJson($this->clientPath)->getContent())->not->toContain('gateway');
    Reports::signIn($this->admin);
    expect($this->getJson($this->teamPath)->getContent())->toContain('Es el timeout del gateway.');
    $this->get(Reports::url($note->json('data.images.0')))->assertOk();
    Reports::signIn($this->owner);
    Reports::message($this->clientPath, 'Sigo esperando', [], ['internal' => '1'])->assertCreated()->assertJsonMissingPath('data.internal');
    expect(lastReportEventChannels(ReportChanged::MESSAGE))->toBe([$companyChannel, $clientChannel]);

    // Another client reaches nothing, neither through its own project nor ours.
    $ajeno = Fixtures::project($this->admin, 'ajeno', 'Otro');
    Reports::signIn(Reports::clientUser($ajeno, 'otro@cliente.com'));
    foreach ([$this->clientPath, "/api/v1/portal/projects/ajeno/reports/{$this->reportId}", $attachmentUrl, "{$this->clientPath}/screenshot"] as $path) {
        $this->getJson($path)->assertNotFound();
    }
    Reports::message("/api/v1/portal/projects/ajeno/reports/{$this->reportId}", 'hola')->assertNotFound();

    // Writing on a resolved report reopens it.
    Reports::signIn($this->admin);
    $this->putJson($this->teamPath, ['status' => 'resolved'])->assertOk();
    Reports::signIn($this->owner);
    Reports::message($this->clientPath, 'Volvió a fallar.')->assertCreated();
    Reports::signIn($this->admin);
    $this->getJson($this->teamPath)->assertJsonPath('data.status', 'in_review')->assertJsonPath('data.resolved_at', null);

    // Chat images go away with the screenshots (180 days); the messages stay.
    app(PurgeReports::class)(now()->addDays(181));
    Reports::signIn($this->owner);
    $this->getJson($attachmentUrl)->assertNotFound()->assertJsonPath('message', 'Imagen no encontrada.');
    Reports::signIn($this->admin);
    $this->getJson($this->teamPath)->assertJsonCount(5, 'data.messages')->assertJsonPath('data.messages.1.images', []);
});

it('keeps the client chat throttled to 30 messages an hour (D-049)', function () {
    for ($i = 0; $i < 30; $i++) {
        Reports::message($this->clientPath, "mensaje {$i}")->assertCreated();
    }
    Reports::message($this->clientPath, 'uno más')->assertStatus(429)
        ->assertJsonPath('message', 'Mandaste muchos mensajes seguidos. Probá de nuevo en un rato.');
});

it('keeps the client out of the team queue, and the team out of the client portal (CA-13.14)', function () {
    $this->getJson('/api/v1/monitoring/portal-admin/reports')->assertForbidden();
    $this->getJson('/api/v1/monitoring/portal-admin/roadmap')->assertForbidden();
    Reports::signIn($this->admin);
    $this->getJson($this->clientPath)->assertForbidden();
    Reports::signIn(teamCollaborator($this->admin));
    $this->getJson('/api/v1/monitoring/portal-admin/reports')->assertForbidden(); // no monitoring.view
});

function teamCollaborator(User $admin): User
{
    return User::factory()->forRole(roleNamed($admin, DefaultRoles::COLLABORATOR))->create();
}
