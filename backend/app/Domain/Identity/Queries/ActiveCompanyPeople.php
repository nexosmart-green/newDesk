<?php

namespace App\Domain\Identity\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Database\Eloquent\Collection;

/**
 * RF-01.10: people chosen in a form (shifts, project members…) must be active and of the same company.
 * The single place that checks it, so every screen answers with the same message.
 */
final class ActiveCompanyPeople
{
    public const INACTIVE_MESSAGE = 'Una de las personas elegidas está dada de baja. Recargá la página y elegí de nuevo.';

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, User> ordered by name
     *
     * @throws BusinessRuleViolation when any id is unknown, of another company or not active
     */
    public function resolveOrFail(int $companyId, array $userIds): Collection
    {
        $people = User::query()->where('company_id', $companyId)->active()->whereKey($userIds)
            ->orderBy('first_name')->orderBy('last_name')->get();

        if ($people->count() !== count(array_unique($userIds))) {
            throw new BusinessRuleViolation(self::INACTIVE_MESSAGE, 'member_inactive');
        }

        return $people;
    }

    /** @throws BusinessRuleViolation */
    public function oneOrFail(int $companyId, int $userId): User
    {
        return $this->resolveOrFail($companyId, [$userId])->first();
    }
}
