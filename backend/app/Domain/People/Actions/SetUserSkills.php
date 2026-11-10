<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Models\Skill;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * RF-01.06: the person's skills from the company catalog, each with a 0–100 level.
 */
final class SetUserSkills
{
    /** @param  array<int, int>  $levelsBySkillId */
    public function __invoke(User $user, array $levelsBySkillId): User
    {
        $known = Skill::query()->forCompany($user->company_id)->whereKey(array_keys($levelsBySkillId))->count();
        if ($known !== count($levelsBySkillId)) {
            throw new BusinessRuleViolation('Alguna habilidad no existe en el catálogo.', 'skill_unknown');
        }

        $user->skills()->sync(array_map(fn (int $level) => ['level' => $level], $levelsBySkillId));

        return $user->load('skills');
    }
}
