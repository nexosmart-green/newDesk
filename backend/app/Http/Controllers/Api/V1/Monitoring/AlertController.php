<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Monitoring\Models\Alert;
use App\Domain\Monitoring\Queries\ListPage;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * monitoreo GET /api/alerts (RF-13.04): open and resolved alerts, the latest seen first, paged in
 * SQL with monitoreo's rule (D-032).
 */
class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'state' => ['nullable', Rule::in([Alert::FIRING, Alert::RESOLVED])],
            'server_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'state' => 'Usá firing o resolved.',
            'server_id' => 'Servidor inválido.',
            'page' => 'Página inválida.',
        ]);

        $query = Alert::query()
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query->where('state', $state))
            ->when($filters['server_id'] ?? null, fn ($query, $serverId) => $query->where('server_id', $serverId));
        $page = new ListPage((int) ($filters['page'] ?? 1), $query->count());
        $alerts = $query->with('server:id,name')
            ->orderByDesc('last_seen_at')->orderByDesc('id')
            ->limit($page->perPage)->offset($page->offset())
            ->get();

        return ApiResponse::page($alerts->map(fn (Alert $alert) => $alert->toPayload())->all(), $page->meta());
    }
}
