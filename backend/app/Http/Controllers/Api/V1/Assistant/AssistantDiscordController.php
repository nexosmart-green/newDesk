<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Domain\Assistant\Discord\DiscordLinkCodes;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** RF-14.08: "Conectar Discord" — a one-time code to type as /newdesk vincular codigo:… in the company's server. */
class AssistantDiscordController extends Controller
{
    public function __invoke(Request $request, DiscordLinkCodes $codes): JsonResponse
    {
        $code = $codes->issue($request->user()->id);

        return ApiResponse::ok([
            'code' => $code,
            'command' => "/newdesk vincular codigo:{$code}",
            'expires_in_minutes' => DiscordLinkCodes::TTL_MINUTES,
        ]);
    }
}
