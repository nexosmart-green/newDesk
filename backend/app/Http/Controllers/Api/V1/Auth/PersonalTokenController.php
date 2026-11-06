<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\CreatePersonalToken;
use App\Domain\Identity\Actions\RevokePersonalToken;
use App\Domain\Shared\Text;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * /api/v1/me/tokens (RF-12.02, minimal; monitoreo's /api/tokens): each user's own MCP tokens. The list never
 * carries the token; creating one answers it once.
 */
class PersonalTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->orderByDesc('created_at')->orderByDesc('id')->get();

        return ApiResponse::ok($tokens->map(fn (PersonalAccessToken $token) => self::payload($token))->all());
    }

    public function store(Request $request, CreatePersonalToken $create): JsonResponse
    {
        $input = Validator::make(
            ['name' => Text::clean((string) $request->input('name')), 'expires_in_days' => $request->input('expires_in_days')],
            ['name' => ['required', 'string', 'max:80'], 'expires_in_days' => ['nullable', 'integer', Rule::in(CreatePersonalToken::EXPIRY_DAYS)]],
            ['name' => 'Poné un nombre (hasta 80 caracteres), por ejemplo "Claude de Iña".', 'expires_in_days' => 'Elegí 30, 90 o 365 días, o sin vencimiento.'],
        )->validate();
        $created = $create($request->user(), $input['name'], isset($input['expires_in_days']) ? (int) $input['expires_in_days'] : null);

        return ApiResponse::ok([...self::payload($created->accessToken), 'token' => $created->plainTextToken],
            'Token creado. Copialo ahora: no se vuelve a mostrar.', 201);
    }

    public function destroy(Request $request, int $token, RevokePersonalToken $revoke): JsonResponse
    {
        $revoke($request->user(), $token);

        return ApiResponse::ok(message: 'Token revocado.');
    }

    /** @return array<string, mixed> */
    private static function payload(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'created_at' => $token->created_at?->toIso8601ZuluString(),
            'last_used_at' => $token->last_used_at?->toIso8601ZuluString(),
            'expires_at' => $token->expires_at?->toIso8601ZuluString(),
        ];
    }
}
