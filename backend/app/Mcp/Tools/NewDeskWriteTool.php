<?php

namespace App\Mcp\Tools;

use App\Domain\Assistant\Support\AssistantTurn;
use App\Domain\Identity\Models\User;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Support\ValidationMessages;

/**
 * Base of every tool that changes data. Writing is split in two steps so a write can be shown to a person
 * before it happens (RF-13.02): `prepare()` validates, resolves and authorizes WITHOUT writing and describes
 * the change; the returned PreparedWrite runs the domain action. Read tools extend NewDeskTool with #[IsReadOnly].
 *
 * Called from an assistant turn, the write does NOT run: it stays as a proposal for the person who asked.
 */
abstract class NewDeskWriteTool extends NewDeskTool
{
    abstract public function prepare(Request $request, User $user): PreparedWrite;

    final protected function run(Request $request, User $user): Response
    {
        return $this->prepare($request, $user)->execute();
    }

    final protected function perform(Request $request, User $user, ?AssistantTurn $turn): Response
    {
        $write = $this->prepare($request, $user);
        if ($turn === null || ! $turn->active()) {
            return $write->execute();
        }

        $proposal = $turn->propose($this->name(), $request->all(), $write->summary);

        return $this->json([
            'proposal_id' => $proposal->id,
            'status' => 'awaiting_confirmation',
            'summary' => $write->summary,
            'note' => "Propuesta #{$proposal->id}: NO está hecha. La persona la ve con los botones Confirmar / Cancelar; no la repitas.",
        ]);
    }

    /**
     * A person confirmed a proposal (RF-13.02): prepare it again with their role AT THIS MOMENT and run it,
     * with the same module switch and error messages as any MCP call.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function confirm(array $arguments, User $user): Response
    {
        try {
            return $this->call(new Request($arguments), $user, null);
        } catch (ValidationException $e) {
            return Response::error(ValidationMessages::from($e));
        }
    }
}
