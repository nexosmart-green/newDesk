<?php

namespace App\Http\Controllers\Api\V1\Clients;

use App\Domain\Clients\Actions\DeleteClient;
use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Clients\Models\Client;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Client::class);

        return ClientResource::collection(
            Client::query()->withCount('projects')->orderBy('name')->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(ClientRequest $request, SaveClient $save): JsonResponse
    {
        Gate::authorize('create', Client::class);

        return ClientResource::make($save($request->validated(), $request->user()))->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        return ClientResource::make($client->loadCount('projects'));
    }

    public function update(ClientRequest $request, Client $client, SaveClient $save): ClientResource
    {
        Gate::authorize('update', $client);

        return ClientResource::make($save($request->validated(), $request->user(), $client));
    }

    public function destroy(Request $request, Client $client, DeleteClient $delete): JsonResponse
    {
        Gate::authorize('delete', $client);
        $delete($client, $request->user());

        return ApiResponse::ok(message: 'Cliente eliminado');
    }
}
