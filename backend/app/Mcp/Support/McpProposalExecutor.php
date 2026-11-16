<?php

namespace App\Mcp\Support;

use App\Domain\Assistant\Contracts\ProposalExecutor;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Assistant\Support\ProposalOutcome;
use App\Domain\Identity\Models\User;
use App\Mcp\Servers\NewDeskServer;
use App\Mcp\Tools\NewDeskWriteTool;
use Illuminate\Contracts\Container\Container;

/**
 * Runs a confirmed assistant proposal through the write tool that proposed it (same prepare + action).
 */
final class McpProposalExecutor implements ProposalExecutor
{
    public function __construct(private readonly Container $container) {}

    public function execute(AssistantProposal $proposal, User $user): ProposalOutcome
    {
        $tool = $this->writeTool($proposal->tool);
        if ($tool === null) {
            return new ProposalOutcome(false, null, 'Esta acción ya no está disponible. Pedísela de nuevo al asistente.');
        }

        $response = $tool->confirm($proposal->arguments, $user);
        $text = (string) $response->content();

        return $response->isError()
            ? new ProposalOutcome(false, null, $text)
            : new ProposalOutcome(true, json_decode($text, true) ?? ['text' => $text], null);
    }

    private function writeTool(string $name): ?NewDeskWriteTool
    {
        foreach (NewDeskServer::TOOLS as $class) {
            if (is_subclass_of($class, NewDeskWriteTool::class)) {
                $tool = $this->container->make($class);
                if ($tool->name() === $name) {
                    return $tool;
                }
            }
        }

        return null;
    }
}
