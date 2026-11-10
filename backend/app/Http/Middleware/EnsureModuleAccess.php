<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level module switch: `->middleware('module:time')`. Evaluated against the current role on
 * every request, like every other permission (RF-12.03).
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $permission = Permission::from("module.{$module}");
        $user = $request->user();

        if ($user instanceof User && ! $user->hasPermission($permission)) {
            return ApiResponse::error("Tu rol no tiene acceso a: {$permission->label()}.", 403, data: ['code' => 'module_disabled', 'module' => $module]);
        }

        return $next($request);
    }
}
