<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Support\PolicyDenialMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base of every newDesk MCP tool (Template Method). Tools are thin adapters (RF-12): they validate input,
 * authorize with the SAME policies as the web and call the SAME domain actions. Domain errors become clear
 * messages for the model (RF-12.07), identical to what the web shows.
 */
abstract class NewDeskTool extends Tool
{
    abstract protected function run(Request $request, User $user): Response;

    public function handle(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        try {
            return $this->run($request, $user);
        } catch (BusinessRuleViolation $e) {
            return Response::error($e->getMessage());
        } catch (AuthorizationException $e) {
            return Response::error($e->status() === 404
                ? 'No encontrado.'
                : (PolicyDenialMessage::of($e) ?? 'No tenés permiso para esta acción con tu rol actual.'));
        } catch (ModelNotFoundException) {
            return Response::error('No encontrado.');
        }
    }

    /** @param  array<string, mixed>|list<mixed>  $data */
    protected function json(array $data): Response
    {
        return Response::json($data);
    }
}
