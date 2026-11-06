<?php

namespace App\Http\Controllers\Api\V1\Clients;

use App\Domain\Clients\Actions\ChangeClientStatus;
use App\Domain\Clients\Actions\DeleteClient;
use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\SaveClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Client::class);
        $request->validate([
            'status' => ['nullable', Rule::enum(ClientStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $clients = Client::query()
            ->withCount('projects')
            ->when($request->query('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->query('search'), fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('legal_name', 'like', "%{$search}%")
                ->orWhere('trade_name', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%")))
            ->orderBy('legal_name')
            ->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return ClientResource::collection($clients)->additional(['message' => null, 'errors' => null]);
    }

    /** RF-02.02 client file: its projects (sold vs consumed hours join in E4). */
    public function show(Client $client): JsonResponse
    {
        Gate::authorize('view', $client);

        return ApiResponse::ok(ClientResource::make($client->load(['projects' => fn ($query) => $query->orderBy('name')])));
    }

    public function store(SaveClientRequest $request, SaveClient $save): JsonResponse
    {
        Gate::authorize('create', Client::class);

        return ApiResponse::ok(ClientResource::make($save($request->user(), $request->validated())), 'Cliente creado.', 201);
    }

    public function update(SaveClientRequest $request, Client $client, SaveClient $save): JsonResponse
    {
        Gate::authorize('update', $client);

        return ApiResponse::ok(ClientResource::make($save($request->user(), $request->validated(), $client)), 'Cliente actualizado.');
    }

    public function updateStatus(Request $request, Client $client, ChangeClientStatus $changeStatus): JsonResponse
    {
        Gate::authorize('update', $client);
        $request->validate(['status' => ['required', Rule::enum(ClientStatus::class)]]);

        $client = $changeStatus($request->user(), $client, ClientStatus::from($request->string('status')->value()));

        return ApiResponse::ok(ClientResource::make($client), 'Estado actualizado.');
    }

    public function destroy(Request $request, Client $client, DeleteClient $delete): JsonResponse
    {
        Gate::authorize('delete', $client);

        $delete($request->user(), $client);

        return ApiResponse::ok(message: 'Cliente borrado.');
    }
}
