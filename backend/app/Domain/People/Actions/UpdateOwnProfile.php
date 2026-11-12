<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Models\EmployeeProfile;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * RF-01.09: what a person edits about themselves — contact, bio, language and timezone.
 * Anything else in the payload is ignored on purpose (job title, rate, role… are Admin-only).
 */
final class UpdateOwnProfile
{
    private const USER_FIELDS = ['locale', 'timezone'];

    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $user, array $data): User
    {
        DB::transaction(function () use ($user, $data) {
            $profile = EmployeeProfile::query()->withoutGlobalScopes()->firstOrNew(
                ['user_id' => $user->id],
                ['company_id' => $user->company_id],
            );
            $before = $this->auditable($user, $profile);

            $user->fill(Arr::only($data, self::USER_FIELDS))->save();
            $profile->fill(Arr::only($data, EmployeeProfile::SELF_EDITABLE))->save();

            ($this->recordActivity)('profile.updated', $user, $user, $before, $this->auditable($user, $profile));
        });

        return $user->load('profile');
    }

    /** @return array<string, mixed> */
    private function auditable(User $user, EmployeeProfile $profile): array
    {
        return [
            ...$user->only(self::USER_FIELDS),
            ...Arr::only($profile->attributesToArray(), EmployeeProfile::SELF_EDITABLE),
        ];
    }
}
