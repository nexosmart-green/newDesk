<?php

use App\Domain\Assistant\Support\AssistantEngineFailed;
use App\Domain\Assistant\Support\AssistantQuestion;
use App\Infrastructure\ClaudeCode\ClaudeCodeEngine;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function claudeQuestion(?string $session = null): AssistantQuestion
{
    return new AssistantQuestion('¿qué bolsas se agotan?', 'Sos el asistente.', 'claude-opus-5-5', 2.0, 'nd_secret-token', $session);
}

beforeEach(function () {
    config(['newdesk.assistant.workdir' => storage_path('framework/testing/assistant-workdir')]);
});

it('runs claude -p isolated: only the newDesk MCP, no built-in tools nor local settings, question by stdin', function () {
    Process::fake(['*' => Process::result(json_encode([
        'type' => 'result', 'subtype' => 'success', 'is_error' => false, 'result' => ' Hay 2 bolsas. ',
        'session_id' => 'abc', 'total_cost_usd' => 0.31, 'duration_ms' => 4200,
    ]))]);

    $answer = app(ClaudeCodeEngine::class)->answer(claudeQuestion('prev-session'));

    expect($answer->text)->toBe('Hay 2 bolsas.')->and($answer->sessionId)->toBe('abc')->and($answer->costUsd)->toBe(0.31);
    Process::assertRan(function (PendingProcess $process) {
        $command = $process->command;

        return $command[0] === 'claude' && $command[1] === '-p'
            && array_slice($command, array_search('--tools', $command, true), 2) === ['--tools', '']
            && array_slice($command, array_search('--allowedTools', $command, true), 2) === ['--allowedTools', 'mcp__newdesk__*']
            && array_slice($command, array_search('--permission-mode', $command, true), 2) === ['--permission-mode', 'dontAsk']
            && array_slice($command, array_search('--setting-sources', $command, true), 2) === ['--setting-sources', '']
            && array_slice($command, array_search('--model', $command, true), 2) === ['--model', 'claude-opus-5-5']
            && array_slice($command, array_search('--max-budget-usd', $command, true), 2) === ['--max-budget-usd', '2.00']
            && array_slice($command, array_search('--resume', $command, true), 2) === ['--resume', 'prev-session']
            && in_array('--strict-mcp-config', $command, true)
            // the question and the token never go on the command line
            && ! str_contains(implode(' ', $command), '¿qué bolsas')
            && ! str_contains(implode(' ', $command), 'nd_secret-token')
            && $process->input === '¿qué bolsas se agotan?'
            && $process->environment === ['NEWDESK_TOKEN' => 'nd_secret-token']
            && $process->path === config('newdesk.assistant.workdir');
    });

    $mcpConfig = File::get(config('newdesk.assistant.workdir').DIRECTORY_SEPARATOR.'mcp.json');
    expect($mcpConfig)->toContain('${NEWDESK_TOKEN}')->toContain(config('newdesk.assistant.mcp_url'))->not->toContain('nd_secret-token');
});

it('turns every failure into a message for the person, without the token', function (array|string $output, string $message) {
    Process::fake(['*' => Process::result(is_array($output) ? json_encode($output) : $output, '', 1)]);

    try {
        app(ClaudeCodeEngine::class)->answer(claudeQuestion());
        $this->fail('Expected AssistantEngineFailed');
    } catch (AssistantEngineFailed $e) {
        expect($e->getMessage())->toBe($message)->not->toContain('nd_secret-token');
    }
})->with([
    'not json (not logged in, crashed)' => ['Invalid API key', 'No pude responder: el asistente no está disponible en este momento. Avisale a un administrador.'],
    'over the turn budget' => [['type' => 'result', 'subtype' => 'error_max_budget_usd', 'is_error' => true, 'total_cost_usd' => 2.1], 'El pedido superó el gasto permitido por consulta. Probá con algo más acotado.'],
    'too many steps' => [['type' => 'result', 'subtype' => 'error_max_turns', 'is_error' => true], 'El pedido necesitó demasiados pasos. Probá dividirlo en partes.'],
]);

it('gives up on a turn that takes too long', function () {
    Process::fake(fn () => throw new ProcessTimedOutException(
        new Symfony\Component\Process\Exception\ProcessTimedOutException(new Symfony\Component\Process\Process(['claude']), 1),
        new FakeProcessResult,
    ));

    expect(fn () => app(ClaudeCodeEngine::class)->answer(claudeQuestion()))
        ->toThrow(AssistantEngineFailed::class, 'Tardé demasiado en responder. Probá con un pedido más concreto.');
});
