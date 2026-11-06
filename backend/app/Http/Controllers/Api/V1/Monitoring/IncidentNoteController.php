<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\StatusPage\Actions\AddIncidentNote;
use App\Domain\StatusPage\Actions\DeleteIncidentNote;
use App\Domain\StatusPage\Models\IncidentNote;
use App\Domain\StatusPage\Models\Period;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** monitoreo POST /api/portal-admin/incidents/{id}/notes and DELETE /api/portal-admin/incident-notes/{id} (D-042). */
class IncidentNoteController extends Controller
{
    public function store(Request $request, Period $period, AddIncidentNote $add): JsonResponse
    {
        $body = $request->validate(['body' => ['required', 'string']], ['body' => 'Escribí la nota (hasta 2000 caracteres).'])['body'];

        return ApiResponse::ok($add($period, $body, $request->user())->toPayload(forTeam: true), 'Nota agregada.', 201);
    }

    public function destroy(Request $request, IncidentNote $note, DeleteIncidentNote $delete): JsonResponse
    {
        $delete($note, $request->user());

        return ApiResponse::ok(message: 'Nota eliminada.');
    }
}
