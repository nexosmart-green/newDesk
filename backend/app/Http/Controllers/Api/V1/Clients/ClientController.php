<?php

namespace App\Http\Controllers\Api\V1\Clients;

use App\Domain\Clients\Actions\ChangeClientStatus;
use App\Domain\Clients\Actions\DeleteClient;
use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Queries\ClientList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ChangeClientStatusRequest;
use App\Http\Requests\Clients\ClientListRequest;
use App\Http\Requests\Clients\SaveClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index(ClientListRequest $request, ClientList $clientList): AnonymousResourceCollection
    {
        return ClientResource::collection($clientList($request->filters(), $request->perPage()));
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

    public function updateStatus(ChangeClientStatusRequest $request, Client $client, ChangeClientStatus $changeStatus): JsonResponse
    {
        $client = $changeStatus($request->user(), $client, $request->status());

        return ApiResponse::ok(ClientResource::make($client), 'Estado actualizado.');
    }

    public function destroy(Request $request, Client $client, DeleteClient $delete): JsonResponse
    {
        Gate::authorize('delete', $client);

        $delete($request->user(), $client);

        return ApiResponse::ok(message: 'Cliente borrado.');
    }
}
