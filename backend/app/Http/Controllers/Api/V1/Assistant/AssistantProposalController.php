<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Domain\Assistant\Actions\CancelAssistantProposal;
use App\Domain\Assistant\Actions\ConfirmAssistantProposal;
use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssistantProposalResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** RF-14.02: only the person who asked confirms or cancels what the assistant proposed. */
class AssistantProposalController extends Controller
{
    public function confirm(Request $request, AssistantProposal $proposal, ConfirmAssistantProposal $confirm): JsonResponse
    {
        Gate::authorize('resolve', $proposal);

        $proposal = $confirm($request->user(), $proposal, AssistantChannel::Web);

        return $proposal->status === ProposalStatus::Confirmed
            ? ApiResponse::ok(AssistantProposalResource::make($proposal), 'Listo, quedó hecho.')
            : ApiResponse::error((string) $proposal->error, 422, data: AssistantProposalResource::make($proposal));
    }

    public function cancel(AssistantProposal $proposal, CancelAssistantProposal $cancel): JsonResponse
    {
        Gate::authorize('resolve', $proposal);

        return ApiResponse::ok(AssistantProposalResource::make($cancel($proposal, AssistantChannel::Web)), 'Cancelada: no se cambió nada.');
    }
}
