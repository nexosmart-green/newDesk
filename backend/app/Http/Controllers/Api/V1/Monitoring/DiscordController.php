<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Monitoring\Discord\DiscordError;
use App\Domain\Monitoring\Discord\Notifier;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * monitoreo's Discord endpoints (RF-13.09, D-059): the pickers' lists and the "Probar" button. They
 * work whenever there is a bot, also while notices are off (CA-13.20): the lists only read Discord,
 * and "Probar" is one message someone asked for, never an automatic notice. Without a bot, 503.
 */
class DiscordController extends Controller
{
    /** The channels and threads of the allowed categories, with where the bot can post. */
    public function channels(DiscordGateway $discord): JsonResponse
    {
        return self::list($discord, fn () => $discord->channels());
    }

    /** The people of the server, for a user's Discord id. */
    public function members(DiscordGateway $discord): JsonResponse
    {
        return self::list($discord, fn () => $discord->members());
    }

    public function test(Project $project, DiscordGateway $discord, Notifier $notifier): JsonResponse
    {
        if (! $discord->configured()) {
            return ApiResponse::error(DiscordError::notConfigured()->getMessage(), 503);
        }
        try {
            $sent = $notifier->test($project);
        } catch (DiscordError $e) {
            return ApiResponse::error($e->getMessage(), 502);
        }
        if (! $sent) {
            throw new BusinessRuleViolation('El proyecto no tiene canal de Discord.', 'discord_no_channel');
        }

        return ApiResponse::ok(message: 'Mensaje de prueba enviado.');
    }

    /** @param  callable(): list<array<string, mixed>>  $list */
    private static function list(DiscordGateway $discord, callable $list): JsonResponse
    {
        if (! $discord->configured()) {
            return ApiResponse::error(DiscordError::notConfigured()->getMessage(), 503);
        }
        try {
            return ApiResponse::ok($list());
        } catch (DiscordError $e) {
            return ApiResponse::error($e->getMessage(), 502);
        }
    }
}
