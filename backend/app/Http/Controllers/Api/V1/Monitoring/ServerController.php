<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Monitoring\Actions\DeleteServer;
use App\Domain\Monitoring\Actions\SaveServer;
use App\Domain\Monitoring\Agent\Section;
use App\Domain\Monitoring\Models\Server;
use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\ServerRequest;
use App\Http\Resources\MonitoringServerResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * monitoreo /api/servers (ADR-0002 §10): same shapes under /api/v1/monitoring. Access is
 * declared per route (monitoring.view to read, monitoring.manage to change).
 */
class ServerController extends Controller
{
    /** Not paginated: monitoreo returns every server at once. */
    public function index(): AnonymousResourceCollection
    {
        return MonitoringServerResource::collection(Server::query()->with('metricsSnapshot')->orderBy('name')->get());
    }

    public function store(ServerRequest $request, SaveServer $save): JsonResponse
    {
        $server = $save($request->validated(), $request->user());

        return $this->summary($request, $server, 'Servidor creado.', 201);
    }

    /** The server and the last answer of every section (null until the first one). */
    public function show(Request $request, Server $server): JsonResponse
    {
        $snapshots = $server->snapshots()->get()->keyBy('section');
        $server->setRelation('metricsSnapshot', $snapshots->get(Section::METRICS));

        $sections = [];
        foreach (Section::ALL as $section) {
            $sections[$section] = $snapshots->get($section)?->toSection();
        }

        return ApiResponse::ok([
            'server' => MonitoringServerResource::make($server)->resolve($request),
            'sections' => $sections,
        ]);
    }

    public function update(ServerRequest $request, Server $server, SaveServer $save): JsonResponse
    {
        return $this->summary($request, $save($request->validated(), $request->user(), $server), 'Servidor actualizado.');
    }

    public function destroy(Request $request, Server $server, DeleteServer $delete): JsonResponse
    {
        $delete($server, $request->user());

        return ApiResponse::ok(message: 'Servidor eliminado.');
    }

    private function summary(Request $request, Server $server, string $message, int $status = 200): JsonResponse
    {
        $server->load('metricsSnapshot');

        return ApiResponse::ok(MonitoringServerResource::make($server)->resolve($request), $message, $status);
    }
}
