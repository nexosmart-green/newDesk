<?php

use App\Domain\Assistant\Models\AssistantFile;
use App\Domain\Assistant\Support\AssistantQuestion;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use Illuminate\Support\Carbon;

require_once __DIR__.'/AssistantHelpers.php';
require_once __DIR__.'/../Tasks/ParityHelpers.php';

it('hands over Excel and PDF reports built from the same queries, attached to the answer (RF-13.05)', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 10 * 3600);
    logEntry($dev, $task, Carbon::parse('2026-11-10 13:00', 'UTC'), 5400);
    $replies = [];
    fakeAssistant(function (AssistantQuestion $question) use (&$replies) {
        foreach (['xlsx', 'pdf'] as $format) {
            $replies[] = mcpResult(mcpCall($question->token, 'export_report', ['kind' => 'hours_by_person', 'format' => $format, 'from' => '2026-11-01', 'to' => '2026-11-15']));
        }

        return 'Te dejé el reporte en Excel y PDF.';
    });

    $answerId = askAssistant($admin, 'pasame las horas por persona en excel y pdf')->json('data.id');
    runAssistantTurn($answerId);

    expect($replies[0]['json'])->toMatchArray(['rows' => 1])->and($replies[1]['error'])->toBeFalse();
    $files = $this->actingAs($admin)->getJson("/api/v1/assistant/messages/{$answerId}")->assertOk()->json('data.files');
    expect(array_column($files, 'format'))->toBe(['xlsx', 'pdf']);

    $xlsx = $this->actingAs($admin)->get($files[0]['url'])->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect(substr($xlsx->streamedContent(), 0, 2))->toBe('PK');
    $pdf = $this->actingAs($admin)->get($files[1]['url'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($pdf->streamedContent(), 0, 4))->toBe('%PDF');
});

it('applies the same permissions as the web, only inside an assistant turn, and only the owner downloads', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $dev = userWithRole($admin);
    grantPermission($admin, DefaultRoles::COLLABORATOR, Permission::AssistantUse);
    $replies = [];
    fakeAssistant(function (AssistantQuestion $question) use (&$replies) {
        $replies[] = mcpResult(mcpCall($question->token, 'export_report', ['kind' => 'hours_by_client', 'format' => 'xlsx']));
        $replies[] = mcpResult(mcpCall($question->token, 'export_report', ['kind' => 'overdue_tasks', 'format' => 'pdf']));

        return 'ok';
    });

    runAssistantTurn(askAssistant($dev->fresh(), 'horas por cliente')->json('data.id'));

    // A Colaborador has neither the reports module nor projects.manage; the overdue tasks they can see, yes.
    expect($replies[0])->toMatchArray(['error' => true])->and($replies[0]['text'])->toStartWith('Tu rol no tiene acceso a: ')
        ->and($replies[1]['error'])->toBeFalse()
        ->and(mcpResult(mcpCall(mcpToken($admin), 'export_report', ['kind' => 'overdue_tasks', 'format' => 'pdf']))['text'])
        ->toBe('Los reportes en archivo se piden desde el Asistente de newDesk (web o Discord).');

    $file = AssistantFile::query()->sole();
    $this->actingAs($admin)->get("/api/v1/assistant/files/{$file->uuid}")->assertNotFound();
    $this->actingAs($dev->fresh())->get("/api/v1/assistant/files/{$file->uuid}")->assertOk();

    $this->travel(AssistantFile::TTL_DAYS + 1)->days();
    $this->actingAs($dev->fresh())->get("/api/v1/assistant/files/{$file->uuid}")->assertNotFound();
});
