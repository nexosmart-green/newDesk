<?php

namespace App\Domain\Projects\Support;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Queries\ActiveCompanyPeople;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * Shared guards for who/what can be attached to a project.
 */
final class EnsureProjectAssignable
{
    public function __construct(private readonly ActiveCompanyPeople $activePeople) {}

    /** CA-02.01: inactive clients are not offered for new projects. */
    public function client(?int $clientId, int $companyId): ?Client
    {
        if ($clientId === null) {
            return null;
        }

        $client = Client::query()->forCompany($companyId)->find($clientId);
        if ($client === null || $client->status !== ClientStatus::Active) {
            throw new BusinessRuleViolation('Ese cliente está inactivo o ya no existe. Elegí otro o reactivalo en Clientes.', 'client_inactive');
        }

        return $client;
    }

    /** RF-01.10: inactive people disappear from assignment. */
    public function member(int $userId, int $companyId): User
    {
        return $this->activePeople->oneOrFail($companyId, $userId);
    }
}
