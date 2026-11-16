<?php

use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Assistant\Support\AssistantQuestion;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Shared\Activity\ActivitySource;

require_once __DIR__.'/AssistantHelpers.php';
require_once __DIR__.'/../Tasks/ParityHelpers.php';

it('turns "cargale 20 h a bhn" into a proposal that changes nothing until the same person confirms (CA-13.02)', function () {
    $admin = adminUser();
    enableAssistant($admin);
    ['project' => $project] = bagProject($admin, [], 10 * 3600);
    $toolReply = null;
    fakeAssistant(function (AssistantQuestion $question) use (&$toolReply) {
        $toolReply = mcpResult(mcpCall($question->token, 'allocate_hours', ['project' => 'bhn', 'minutes' => 1200, 'reason' => 'Presupuesto #123']));

        return 'Te dejé la propuesta para confirmar.';
    });

    $answerId = askAssistant($admin, 'cargale 20 h a bhn por el presupuesto 123')->assertStatus(202)->json('data.id');
    $answer = runAssistantTurn($answerId);

    expect($toolReply['json'])->toMatchArray(['status' => 'awaiting_confirmation', 'summary' => 'Cargar 20h 00m a bhn «BHN» — motivo: Presupuesto #123'])
        ->and($project->fresh()->allocated_seconds)->toBe(10 * 3600)
        ->and($answer->status->value)->toBe('done');

    $proposal = $this->actingAs($admin)->getJson("/api/v1/assistant/messages/{$answerId}")->assertOk()->json('data.proposals.0');
    expect($proposal)->toMatchArray(['status' => 'pending', 'summary' => 'Cargar 20h 00m a bhn «BHN» — motivo: Presupuesto #123']);

    $this->actingAs($admin)->postJson("/api/v1/assistant/proposals/{$proposal['id']}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
    expect($project->fresh()->allocated_seconds)->toBe(30 * 3600)
        ->and(ActivityLog::query()->where('event', 'hours.allocated')->latest('id')->first()->only('source', 'token_name'))
        ->toBe(['source' => ActivitySource::Assistant, 'token_name' => 'web']);

    // Confirmed once: a double click or a second channel sees it resolved.
    $this->actingAs($admin)->postJson("/api/v1/assistant/proposals/{$proposal['id']}/confirm")
        ->assertUnprocessable()->assertJsonPath('message', 'Esa propuesta ya fue resuelta.');
    expect($project->fresh()->allocated_seconds)->toBe(30 * 3600);
});

it('leaves no change when the person cancels, and nobody else can resolve it (CA-13.02, CA-13.08)', function () {
    $admin = adminUser();
    enableAssistant($admin);
    ['project' => $project] = bagProject($admin, [], 3600);
    fakeAssistant(function (AssistantQuestion $question) {
        mcpCall($question->token, 'change_project_status', ['project' => 'bhn', 'status' => 'paused']);

        return 'Propuesta lista.';
    });
    runAssistantTurn(askAssistant($admin, 'pausá bhn')->json('data.id'));
    $proposal = AssistantProposal::query()->sole();

    $other = userWithRole($admin, DefaultRoles::MANAGER);
    grantPermission($admin, DefaultRoles::MANAGER, Permission::AssistantUse);
    $this->actingAs($other)->postJson("/api/v1/assistant/proposals/{$proposal->id}/confirm")->assertNotFound();

    $this->actingAs($admin)->postJson("/api/v1/assistant/proposals/{$proposal->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->actingAs($admin)->postJson("/api/v1/assistant/proposals/{$proposal->id}/confirm")->assertUnprocessable();
    expect($project->fresh()->status->value)->toBe('active');
});

it('does not even propose what the role cannot do (CA-13.03)', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $dev = userWithRole($admin);
    grantPermission($admin, DefaultRoles::COLLABORATOR, Permission::AssistantUse);
    bagProject($admin, [$dev], 3600);
    $toolReply = null;
    fakeAssistant(function (AssistantQuestion $question) use (&$toolReply) {
        $toolReply = mcpResult(mcpCall($question->token, 'allocate_hours', ['project' => 'bhn', 'minutes' => 600, 'reason' => 'Me cargo horas']));

        return 'Tu rol no puede cargar horas.';
    });

    runAssistantTurn(askAssistant($dev, 'cargale 10 h a bhn')->assertStatus(202)->json('data.id'));

    expect($toolReply)->toMatchArray(['error' => true, 'text' => 'Tu rol no tiene permiso para cargar horas.'])
        ->and(AssistantProposal::query()->count())->toBe(0);
});

it('prepares the proposal again on confirmation: a role demoted in between cannot run it', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    grantPermission($admin, DefaultRoles::MANAGER, Permission::AssistantUse);
    ['project' => $project] = bagProject($admin, [$manager], 3600);
    fakeAssistant(function (AssistantQuestion $question) {
        mcpCall($question->token, 'allocate_hours', ['project' => 'bhn', 'minutes' => 600, 'reason' => 'Presupuesto #5']);

        return 'Listo para confirmar.';
    });
    runAssistantTurn(askAssistant($manager, 'cargale 10 h a bhn')->json('data.id'));
    $proposal = AssistantProposal::query()->sole();

    revokeModule($admin, DefaultRoles::MANAGER, Permission::HoursAllocate);

    $this->actingAs($manager->fresh())->postJson("/api/v1/assistant/proposals/{$proposal->id}/confirm")
        ->assertUnprocessable()->assertJsonPath('message', 'Tu rol no tiene permiso para cargar horas.')->assertJsonPath('data.status', 'failed');
    expect($project->fresh()->allocated_seconds)->toBe(3600);
});

it('expires proposals after a day', function () {
    $admin = adminUser();
    enableAssistant($admin);
    ['project' => $project] = bagProject($admin, [], 3600);
    fakeAssistant(function (AssistantQuestion $question) {
        mcpCall($question->token, 'allocate_hours', ['project' => 'bhn', 'minutes' => 60, 'reason' => 'Presupuesto #6']);

        return 'Propuesta lista.';
    });
    runAssistantTurn(askAssistant($admin, 'cargale 1 h a bhn')->json('data.id'));
    $proposal = AssistantProposal::query()->sole();

    $this->travel(AssistantProposal::TTL_HOURS + 1)->hours();

    $this->actingAs($admin)->postJson("/api/v1/assistant/proposals/{$proposal->id}/confirm")
        ->assertUnprocessable()->assertJsonPath('message', 'La propuesta venció: pedíselo de nuevo al asistente.');
    expect($proposal->fresh()->status->value)->toBe('expired')->and($project->fresh()->allocated_seconds)->toBe(3600);
});
