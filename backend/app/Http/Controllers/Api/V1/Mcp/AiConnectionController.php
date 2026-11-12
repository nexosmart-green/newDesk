<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Mcp\Actions\IssueMcpToken;
use App\Domain\Mcp\Actions\RevokeMcpToken;
use App\Domain\Mcp\Queries\CompanyMcpTokens;
use App\Domain\Mcp\Support\McpConnectionSnippet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mcp\IssueMcpTokenRequest;
use App\Http\Resources\McpTokenResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Mi perfil → Conexiones de IA" (RF-12.02/04) and the owner's view of everyone's tokens (RF-12.05).
 */
class AiConnectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([
            'endpoint' => McpConnectionSnippet::endpoint(),
            'tokens' => McpTokenResource::collection($request->user()->tokens()->latest('id')->get()),
        ]);
    }

    public function store(IssueMcpTokenRequest $request, IssueMcpToken $issue): JsonResponse
    {
        $token = $issue($request->user(), $request->tokenName(), $request->expiresInDays());

        return ApiResponse::ok([
            'token' => McpTokenResource::make($token->accessToken),
            // Shown ONCE: only the hash is stored.
            'plain_text_token' => $token->plainTextToken,
            'claude_code_command' => McpConnectionSnippet::claudeCodeCommand($token->plainTextToken),
            'project_mcp_json' => McpConnectionSnippet::projectMcpJson(),
        ], 'Copiá el token ahora: no se vuelve a mostrar.', 201);
    }

    public function destroy(Request $request, PersonalAccessToken $token, RevokeMcpToken $revoke): JsonResponse
    {
        $revoke($request->user(), $token);

        return ApiResponse::ok(message: 'Token revocado.');
    }

    /** RF-12.05: every token in the company, for `mcp.manage_tokens`. */
    public function all(Request $request, CompanyMcpTokens $companyTokens): AnonymousResourceCollection
    {
        Gate::authorize(Permission::McpManageTokens->value);

        return McpTokenResource::collection($companyTokens($request->user()->company_id));
    }
}
