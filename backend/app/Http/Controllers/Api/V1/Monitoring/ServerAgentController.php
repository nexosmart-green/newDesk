<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Monitoring\Actions\TestServerConnection;
use App\Domain\Monitoring\Agent\Section;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Polling\PollerSignals;
use App\Domain\Monitoring\Queries\MetricSeries;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What the server's detail asks about its agent: test, refresh, live mode and metric charts.
 */
class ServerAgentController extends Controller
{
    /** "Probar conexión": always 200, the outcome is in `ok` (as monitoreo). */
    public function test(Server $server, TestServerConnection $test): JsonResponse
    {
        return ApiResponse::ok($test($server));
    }

    /** "Forzar actualización" of one section, or of every one without `section`. */
    public function refresh(Request $request, Server $server, PollerSignals $signals): JsonResponse
    {
        $request->validate(
            ['section' => ['nullable', 'string', Rule::in(Section::ALL)]],
            ['section' => 'Sección desconocida.'],
        );
        $signals->requestRefresh($server->id, $request->input('section'));

        return ApiResponse::ok(message: 'Actualización pedida.', status: 202);
    }

    /** The detail's heartbeat: metrics every 5 s until 30 s after the last beat (CA-13.06). */
    public function live(Server $server, PollerSignals $signals): JsonResponse
    {
        $signals->beat($server->id);

        return ApiResponse::ok();
    }

    public function metrics(Request $request, Server $server, MetricSeries $series): JsonResponse
    {
        $request->validate(
            ['range' => ['nullable', 'string', Rule::in(array_keys(MetricSeries::RANGES))]],
            ['range' => 'Usá 1h, 24h, 7d, 30d o 1y.'],
        );

        return ApiResponse::ok($series($server, $request->query('range') ?: '24h'));
    }
}
