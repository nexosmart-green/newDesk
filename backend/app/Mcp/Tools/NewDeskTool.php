<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
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
    private const NOT_FOUND = 'No existe o no tenés acceso. Usá list_my_tasks o list_projects para obtener códigos válidos.';

    abstract protected function run(Request $request, User $user): Response;

    /** The module this tool belongs to (same switch as the web routes), or null if always available. */
    protected function module(): ?Permission
    {
        return null;
    }

    public function handle(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $request->setArguments(self::normalized($request->all()));

        try {
            if ($this->module() !== null) {
                $this->ensureModule($user, $this->module());
            }

            return $this->run($request, $user);
        } catch (BusinessRuleViolation $e) {
            return Response::error($e->getMessage());
        } catch (AuthorizationException $e) {
            return Response::error($e->status() === 404
                ? self::NOT_FOUND
                : (PolicyDenialMessage::of($e) ?? 'Tu rol actual no permite esta acción. Pedíselo a un administrador; no reintentes.'));
        } catch (ModelNotFoundException) {
            return Response::error(self::NOT_FOUND);
        }
    }

    /** The same switch as the web's `module:` middleware; also for a second module a single call may need. */
    protected function ensureModule(User $user, Permission $module): void
    {
        if (! $user->hasPermission($module)) {
            throw new BusinessRuleViolation("Tu rol no tiene acceso a: {$module->label()}. Pedile a un administrador que habilite el módulo para tu rol; no reintentes.", 'module_disabled', 403);
        }
    }

    /**
     * Arguments as the web receives a form after TrimStrings + ConvertEmptyStringsToNull, so the same input
     * is stored the same way through both channels.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    private static function normalized(array $arguments): array
    {
        return array_map(fn (mixed $value) => match (true) {
            is_array($value) => self::normalized($value),
            is_string($value) => trim($value) === '' ? null : trim($value),
            default => $value,
        }, $arguments);
    }

    /** @param  array<string, mixed>|list<mixed>  $data */
    protected function json(array $data): Response
    {
        return Response::json($data);
    }
}
