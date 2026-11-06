<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Mcp\Actions\IssueMcpToken;
use App\Domain\Mcp\Actions\RevokeMcpToken;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Mi perfil → Conexiones de IA" (RF-12.02/04) and the owner's view of everyone's tokens (RF-12.05).
 */
class AiConnectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([
            'endpoint' => $this->endpoint(),
            'tokens' => $request->user()->tokens()->latest('id')->get()->map(fn (PersonalAccessToken $token) => $this->present($token)),
        ]);
    }

    public function store(Request $request, IssueMcpToken $issue): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'expires_in_days' => ['nullable', 'integer', Rule::in(IssueMcpToken::EXPIRY_OPTIONS)],
        ]);

        $token = $issue($request->user(), $request->string('name')->value(), $request->filled('expires_in_days') ? $request->integer('expires_in_days') : null);
        $endpoint = $this->endpoint();

        return ApiResponse::ok([
            'token' => $this->present($token->accessToken),
            // Shown ONCE: only the hash is stored.
            'plain_text_token' => $token->plainTextToken,
            'claude_code_command' => "claude mcp add --transport http newdesk {$endpoint} --header \"Authorization: Bearer {$token->plainTextToken}\"",
            'project_mcp_json' => [
                'mcpServers' => [
                    'newdesk' => [
                        'type' => 'http',
                        'url' => $endpoint,
                        'headers' => ['Authorization' => 'Bearer ${NEWDESK_TOKEN}'],
                    ],
                ],
            ],
        ], 'Copiá el token ahora: no se vuelve a mostrar.', 201);
    }

    public function destroy(Request $request, PersonalAccessToken $token, RevokeMcpToken $revoke): JsonResponse
    {
        $revoke($request->user(), $token);

        return ApiResponse::ok(message: 'Token revocado.');
    }

    /** RF-12.05: every token in the company, for `mcp.manage_tokens`. */
    public function all(Request $request): JsonResponse
    {
        Gate::authorize(Permission::McpManageTokens->value);

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', User::query()->where('company_id', $request->user()->company_id)->select('id'))
            ->with('tokenable')->latest('id')->get();

        return ApiResponse::ok($tokens->map(fn (PersonalAccessToken $token) => [
            ...$this->present($token),
            'owner' => ['id' => $token->tokenable->id, 'full_name' => $token->tokenable->fullName()],
        ]));
    }

    /** @return array<string, mixed> */
    private function present(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }

    private function endpoint(): string
    {
        return rtrim((string) config('app.url'), '/').'/mcp';
    }
}
