<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Clients\Actions\DeleteClientUser;
use App\Domain\Clients\Actions\SaveClientUser;
use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Queries\ProjectAdmin;
use App\Http\Controllers\Controller;
use App\Http\Resources\MonitoringUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * monitoreo /api/portal-admin/clients (RF-13.07): the clients with their portal users and projects.
 * The client itself is created, renamed, (de)activated and deleted in Clientes (RF-02).
 */
class PortalClientController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok(Client::query()->withCount(['users', 'projects'])->orderBy('legal_name')->get()->map(self::payload(...))->all());
    }

    public function show(Client $client, ProjectAdmin $admin): JsonResponse
    {
        return ApiResponse::ok([
            'client' => self::payload($client->loadCount(['users', 'projects'])),
            'users' => MonitoringUserResource::collection($client->users()->with('client')->orderBy('email')->get())->resolve(),
            'projects' => $admin->rows(Project::query()->with('client')->where('client_id', $client->id)->orderBy('name')->orderBy('id')->get()),
        ]);
    }

    public function storeUser(Request $request, Client $client, SaveClientUser $save): JsonResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $email = $request->validate(
            ['email' => ['required', 'string', 'max:254', 'email:rfc', 'regex:/@[^@]*\./', Rule::unique('users', 'email')]],
            ['email.unique' => 'Ese mail ya está dado de alta.', 'email' => 'Ingresá un mail válido.'],
        )['email'];
        $user = $save->create($client, $email, $request->user());

        return ApiResponse::ok(MonitoringUserResource::make($user->load('client'))->resolve(), 'Usuario creado.', 201);
    }

    public function updateUser(Request $request, Client $client, User $clientUser, SaveClientUser $save): JsonResponse
    {
        $user = $this->ensureUserOf($client, $clientUser);
        $data = $request->validate(['active' => ['sometimes', 'boolean']]);
        if (array_key_exists('active', $data)) {
            $save->setActive($user, (bool) $data['active'], $request->user());
        }

        return ApiResponse::ok(MonitoringUserResource::make($user->load('client'))->resolve(), 'Usuario actualizado.');
    }

    public function destroyUser(Request $request, Client $client, User $clientUser, DeleteClientUser $delete): JsonResponse
    {
        $user = $this->ensureUserOf($client, $clientUser);
        $delete($user, $request->user());

        return ApiResponse::ok(message: 'Usuario eliminado.');
    }

    /** monitoreo's store.Client. Needs the users and projects counts. @return array<string, mixed> */
    private static function payload(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->displayName(),
            'active' => $client->isActive(),
            'created_at' => $client->created_at?->toIso8601ZuluString(),
            'users' => $client->users_count,
            'projects' => $client->projects_count,
        ];
    }

    /** The {user} of another client (or a team user) does not exist here. */
    private function ensureUserOf(Client $client, User $user): User
    {
        if ($user->client_id !== $client->id) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        return $user;
    }
}
