<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * Shared guards for who/what can be attached to a project.
 */
final class EnsureProjectAssignable
{
    /** CA-02.01: inactive clients are not offered for new projects. */
    public function client(?int $clientId, int $companyId): ?Client
    {
        if ($clientId === null) {
            return null;
        }

        $client = Client::query()->forCompany($companyId)->find($clientId);
        if ($client === null || $client->status !== ClientStatus::Active) {
            throw new BusinessRuleViolation('El cliente no existe o está inactivo.', 'client_inactive');
        }

        return $client;
    }

    /** RF-01.10: inactive people disappear from assignment. */
    public function member(int $userId, int $companyId): User
    {
        $user = User::query()->where('company_id', $companyId)->find($userId);
        if ($user === null || ! $user->isActive()) {
            throw new BusinessRuleViolation('La persona no existe o está dada de baja.', 'member_inactive');
        }

        return $user;
    }
}
