<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Models\Skill;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * RF-01.06: the person's skills from the company catalog, each with a 0–100 level.
 */
final class SetUserSkills
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array<int, int>  $levelsBySkillId */
    public function __invoke(User $user, array $levelsBySkillId): User
    {
        $known = Skill::query()->forCompany($user->company_id)->whereKey(array_keys($levelsBySkillId))->count();
        if ($known !== count($levelsBySkillId)) {
            throw new BusinessRuleViolation('Una de las habilidades ya no está en el catálogo. Recargá y elegí de nuevo.', 'skill_unknown');
        }

        return DB::transaction(function () use ($user, $levelsBySkillId) {
            $before = $this->auditable($user->skills()->get()->all());

            $user->skills()->sync(array_map(fn (int $level) => ['level' => $level], $levelsBySkillId));
            $user->load('skills');

            ($this->recordActivity)('profile.skills_updated', $user, $user, ['skills' => $before], ['skills' => $this->auditable($user->skills->all())]);

            return $user;
        });
    }

    /**
     * @param  list<Skill>  $skills
     * @return list<array{id: int, name: string, level: int}>
     */
    private function auditable(array $skills): array
    {
        return array_map(fn (Skill $skill) => [
            'id' => $skill->id,
            'name' => $skill->name,
            'level' => (int) $skill->pivot->level,
        ], $skills);
    }
}
