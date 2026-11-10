<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Walks every registered /api/v1 route (monitoreo's TestRoutePermissions, D-037): a route added
 * later cannot stay open to the wrong side by mistake.
 */
it('closes every team route to client users and every portal route to the team (CA-13.14)', function () {
    $admin = provisionCompany();
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $portalUser = User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))->create(['client_id' => $client->id])->fresh();

    // Proves the portal group's middleware even before a portal module registers routes.
    Route::prefix('api/v1/portal')->middleware(['api', 'auth:sanctum', 'active', 'client'])
        ->get('_probe', fn () => ApiResponse::ok());

    $public = ['api/v1/auth/login', 'api/v1/auth/two-factor-challenge', 'api/v1/auth/forgot-password', 'api/v1/auth/reset-password',
        'api/v1/invitations/{token}', 'api/v1/invitations/{token}/accept', 'api/v1/portal/auth/request-code', 'api/v1/portal/auth/verify-code'];
    $bothSides = ['api/v1/me', 'api/v1/auth/logout', 'api/v1/broadcasting/auth'];

    $failures = [];
    $checked = ['team' => 0, 'portal' => 0];
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'api/v1/') || in_array($uri, [...$public, ...$bothSides], true)) {
            continue;
        }

        $side = str_starts_with($uri, 'api/v1/portal/') ? 'portal' : 'team';
        $method = collect($route->methods())->first(fn (string $method) => $method !== 'HEAD');
        $path = '/'.preg_replace('/\{[^}]+\}/', '1', $uri);

        Auth::forgetGuards();
        if (($status = $this->json($method, $path)->status()) !== 401) {
            $failures[] = "anonymous {$method} {$uri}: {$status}";
        }

        $intruder = $side === 'portal' ? $admin : $portalUser;
        if (($status = $this->actingAs($intruder)->json($method, $path)->status()) !== 403) {
            $failures[] = "{$side} route {$method} {$uri} for the other side: {$status}";
        }
        $checked[$side]++;
    }

    expect($failures)->toBe([])
        ->and($checked['team'])->toBeGreaterThan(20)
        ->and($checked['portal'])->toBeGreaterThan(0);

    Auth::forgetGuards();
    $this->actingAs($portalUser)->getJson('/api/v1/portal/_probe')->assertOk();
    $this->actingAs($portalUser)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.client.id', $client->id);
    Auth::forgetGuards();
    $this->actingAs($admin->fresh())->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.client', null);
});
