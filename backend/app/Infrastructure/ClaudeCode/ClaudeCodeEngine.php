<?php

namespace App\Infrastructure\ClaudeCode;

use App\Domain\Assistant\Contracts\AssistantEngine;
use App\Domain\Assistant\Support\AssistantAnswer;
use App\Domain\Assistant\Support\AssistantEngineFailed;
use App\Domain\Assistant\Support\AssistantQuestion;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * RF-13 engine: Claude Code headless (`claude -p`), isolated so it can ONLY use the newDesk MCP:
 *  - `--tools ""` turns off every built-in tool (shell, files, web) and `--setting-sources ""` ignores the
 *    machine's settings, hooks and plugins; `--strict-mcp-config` loads only our MCP; `--permission-mode dontAsk`
 *    denies whatever is not in `--allowedTools`;
 *  - it runs in an empty working directory of its own (no CLAUDE.md, no project files);
 *  - the asker's ephemeral token goes in the environment (`NEWDESK_TOKEN`, expanded by the MCP config): never
 *    on the command line, on disk or in logs. The question goes through stdin, never as an argument.
 * Verified against Claude Code 2.1.325 (`claude --help` and real runs, 2026-11-16).
 */
final class ClaudeCodeEngine implements AssistantEngine
{
    private const TOKEN_ENV = 'NEWDESK_TOKEN';

    private const UNAVAILABLE = 'No pude responder: el asistente no está disponible en este momento. Avisale a un administrador.';

    public function __construct(private readonly Config $config) {}

    public function answer(AssistantQuestion $question): AssistantAnswer
    {
        $workdir = $this->workdir();

        try {
            $result = Process::path($workdir)
                ->timeout($this->config->get('newdesk.assistant.timeout_seconds'))
                ->env([self::TOKEN_ENV => $question->token])
                ->input($question->text)
                ->run($this->command($question, $workdir.DIRECTORY_SEPARATOR.'mcp.json'));
        } catch (ProcessTimedOutException) {
            // It may have spent up to its budget before being cut: count it all, so the monthly cap can't be dodged.
            throw new AssistantEngineFailed('Tardé demasiado en responder. Probá con un pedido más concreto.', $question->budgetUsd);
        }

        // Before any work (no JSON, or an error that cost nothing) a missing `--resume` session is the usual cause.
        $output = json_decode($result->output(), true);
        if (! is_array($output) || ! isset($output['type'])) {
            throw new AssistantEngineFailed(self::UNAVAILABLE, mayRetryFresh: $question->sessionId !== null);
        }

        $cost = (float) ($output['total_cost_usd'] ?? 0);
        $sessionId = $output['session_id'] ?? null;
        if (($output['is_error'] ?? false) || ($output['subtype'] ?? '') !== 'success' || ! is_string($output['result'] ?? null)) {
            throw new AssistantEngineFailed($this->failureMessage($output), $cost, $sessionId, $question->sessionId !== null && $cost === 0.0);
        }

        return new AssistantAnswer(trim($output['result']), $sessionId, $cost, (int) ($output['duration_ms'] ?? 0));
    }

    /** @return list<string> */
    private function command(AssistantQuestion $question, string $mcpConfig): array
    {
        return [
            $this->config->get('newdesk.assistant.binary'),
            '-p',
            '--output-format', 'json',
            '--model', $question->model,
            '--mcp-config', $mcpConfig,
            '--strict-mcp-config',
            '--tools', '',
            '--allowedTools', 'mcp__newdesk__*',
            '--permission-mode', 'dontAsk',
            '--setting-sources', '',
            '--append-system-prompt', $question->systemPrompt,
            '--max-budget-usd', number_format($question->budgetUsd, 2, '.', ''),
            ...($question->sessionId !== null ? ['--resume', $question->sessionId] : []),
        ];
    }

    /** An empty directory of its own with the MCP config: the URL and a placeholder, never the token. */
    private function workdir(): string
    {
        $workdir = $this->config->get('newdesk.assistant.workdir');
        File::ensureDirectoryExists($workdir);
        File::put($workdir.DIRECTORY_SEPARATOR.'mcp.json', json_encode(['mcpServers' => ['newdesk' => [
            'type' => 'http',
            'url' => $this->config->get('newdesk.assistant.mcp_url'),
            'headers' => ['Authorization' => 'Bearer ${'.self::TOKEN_ENV.'}'],
        ]]], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $workdir;
    }

    /** @param  array<string, mixed>  $output */
    private function failureMessage(array $output): string
    {
        return match ($output['subtype'] ?? null) {
            'error_max_budget_usd' => 'El pedido superó el gasto permitido por consulta. Probá con algo más acotado.',
            'error_max_turns' => 'El pedido necesitó demasiados pasos. Probá dividirlo en partes.',
            default => self::UNAVAILABLE,
        };
    }
}
