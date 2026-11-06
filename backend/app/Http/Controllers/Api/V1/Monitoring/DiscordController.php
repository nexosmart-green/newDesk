<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Discord is Phase 7 (RF-13.09). Until the bot exists, monitoreo's answer without a bot: 503 to the
 * channel and member lists and to a project's test message.
 */
class DiscordController extends Controller
{
    public function unavailable(): JsonResponse
    {
        return ApiResponse::error('El bot de Discord no está configurado.', 503);
    }
}
