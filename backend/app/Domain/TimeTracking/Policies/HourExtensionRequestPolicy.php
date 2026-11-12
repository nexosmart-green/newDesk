<?php

namespace App\Domain\TimeTracking\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use Illuminate\Auth\Access\Response;

/**
 * RF-05.08: whoever loads hours (`hours.allocate`) sees, approves and rejects extension requests.
 * Requests of other companies never reach here (company scope → 404).
 */
class HourExtensionRequestPolicy
{
    private const DENIAL = 'Tu rol no tiene permiso para cargar horas.';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::HoursAllocate);
    }

    public function approve(User $user, HourExtensionRequest $request): Response
    {
        return $this->resolve($user, $request);
    }

    public function reject(User $user, HourExtensionRequest $request): Response
    {
        return $this->resolve($user, $request);
    }

    private function resolve(User $user, HourExtensionRequest $request): Response
    {
        if ($user->company_id !== $request->company_id) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission(Permission::HoursAllocate) ? Response::allow() : Response::deny(self::DENIAL, 'cannot_allocate');
    }
}
