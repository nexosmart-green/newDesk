<?php

namespace App\Http\Middleware;

use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Support\AssistantToken;
use App\Domain\Assistant\Support\AssistantTurn;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\ActivityContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * RF-12.03/05/06/07 for every MCP request (after auth:sanctum):
 *  - only personal tokens (never the web session cookie), of an active user;
 *  - team users only (a client user lives in the portal, CA-13.14);
 *  - `mcp.use`, plus the server's own permissions (e.g. `monitoring.view` for /mcp/monitoreo), evaluated
 *    against the CURRENT role — promotions/demotions apply without a new token;
 *  - activity is recorded with source = mcp and the token name;
 *  - 60 requests/minute per token, shared by every MCP server.
 */
class AuthorizeMcpToken
{
    public const PER_MINUTE = 60;

    /** routes/ai.php: NewDeskServer, the one whose write tools leave proposals (RF-14.02). */
    private const ASSISTANT_SERVER_PATH = 'mcp';

    public function __construct(private readonly ActivityContext $context, private readonly AssistantTurn $turn) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $this->turn->clear();
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken || ! $user->isActive()) {
            return $this->deny('El token no sirve (revocado, vencido o usuario dado de baja). Generá uno nuevo en Mi perfil → Conexiones de IA.', 401);
        }

        if ($user->isClient()) {
            return $this->deny('Este token no es de un usuario del equipo.', 403);
        }

        // The server's own permissions (e.g. `monitoring.view` for /mcp/monitoreo) bind every token, assistant's too.
        foreach ($permissions as $permission) {
            if (! $user->hasPermission(Permission::from($permission))) {
                return $this->deny('Tu rol no permite usar este servidor MCP.', 403);
            }
        }

        $key = "mcp-token:{$token->id}";
        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return $this->deny('Demasiadas solicitudes: el máximo es '.self::PER_MINUTE.' por minuto. Esperá '.RateLimiter::availableIn($key).' segundos antes del próximo pedido y agrupá las consultas.', 429);
        }
        RateLimiter::hit($key, 60);

        if (AssistantToken::is($token)) {
            return $this->asAssistantTurn($request, $next, $user, $token);
        }

        if (! $user->hasPermission(Permission::McpUse)) {
            return $this->deny('Tu rol no tiene habilitado el uso de MCP. Pedile a un administrador que lo habilite; no reintentes.', 403);
        }

        $this->context->useMcp($token->name);

        return $next($request);
    }

    /**
     * RF-14: the ephemeral token of an assistant turn. It needs `assistant.use` (not `mcp.use`), belongs to one
     * answer in progress, and while it is used every write tool leaves a proposal instead of running.
     */
    private function asAssistantTurn(Request $request, Closure $next, User $user, PersonalAccessToken $token): Response
    {
        if (! $user->hasPermission(Permission::AssistantUse)) {
            return $this->deny('Tu rol no tiene habilitado el asistente de IA.', 403);
        }
        // Only newDesk's own server turns writes into proposals; any other MCP server is off limits for a turn.
        if (! $request->is(self::ASSISTANT_SERVER_PATH)) {
            return $this->deny('El asistente solo usa el servidor MCP de newDesk.', 403);
        }

        $message = AssistantMessage::query()->forCompany($user->company_id)->with('conversation')
            ->where('access_token_id', $token->id)->first();
        if ($message === null || ! $message->status->isInProgress() || $message->conversation->user_id !== $user->id) {
            return $this->deny('El turno del asistente ya terminó.', 401);
        }

        $this->turn->begin($message);
        $this->context->useAssistant($message->conversation->channel->value);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['error' => $message], $status);
    }
}
