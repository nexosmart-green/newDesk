<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Models\EmployeeProfile;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * RF-01.09: Admin-side edition of a person's file (names + employment data).
 */
final class UpdateEmployeeProfile
{
    private const USER_FIELDS = ['first_name', 'last_name'];

    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, User $user, array $data): User
    {
        if (array_key_exists('reports_to_user_id', $data) && $data['reports_to_user_id'] !== null) {
            $this->assertValidSuperior($user, (int) $data['reports_to_user_id']);
        }

        DB::transaction(function () use ($actor, $user, $data) {
            $profile = EmployeeProfile::query()->withoutGlobalScopes()->firstOrNew(
                ['user_id' => $user->id],
                ['company_id' => $user->company_id],
            );
            $before = [...$user->only(self::USER_FIELDS), ...$this->auditable($profile)];

            $user->fill(Arr::only($data, self::USER_FIELDS))->save();
            $profile->fill(Arr::only($data, EmployeeProfile::ADMIN_EDITABLE))->save();

            ($this->recordActivity)('user.profile_updated', $user, $actor, $before, [
                ...$user->only(self::USER_FIELDS),
                ...$this->auditable($profile),
            ]);
        });

        return $user->load('profile');
    }

    /**
     * "Reporta a" must be a colleague and must not create a loop (A → B → A).
     */
    private function assertValidSuperior(User $user, int $superiorId): void
    {
        $visited = [$user->id => true];
        $currentId = $superiorId;

        while ($currentId !== null) {
            if (isset($visited[$currentId])) {
                throw new BusinessRuleViolation('Esa relación "reporta a" generaría un ciclo.', 'reports_to_cycle');
            }
            $visited[$currentId] = true;

            $current = User::query()->where('company_id', $user->company_id)->team()->with('profile')->find($currentId);
            if ($current === null) {
                throw new BusinessRuleViolation('La persona superior no existe en la empresa.', 'reports_to_invalid');
            }
            $currentId = $current->profile?->reports_to_user_id;
        }
    }

    /** Sensitive values are audited as "changed", never in clear text. @return array<string, mixed> */
    private function auditable(EmployeeProfile $profile): array
    {
        $values = Arr::only($profile->attributesToArray(), EmployeeProfile::ADMIN_EDITABLE);
        foreach (['cuil', 'emergency_contact'] as $secret) {
            $values[$secret] = isset($values[$secret]) ? hash('sha256', (string) $values[$secret]) : null;
        }

        return $values;
    }
}
