<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Models\EmployeeProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * RF-01.09: what a person edits about themselves — contact, bio, language and timezone.
 * Anything else in the payload is ignored on purpose (job title, rate, role… are Admin-only).
 */
final class UpdateOwnProfile
{
    private const USER_FIELDS = ['locale', 'timezone'];

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $user, array $data): User
    {
        DB::transaction(function () use ($user, $data) {
            $user->fill(Arr::only($data, self::USER_FIELDS))->save();

            EmployeeProfile::query()->withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $user->id],
                ['company_id' => $user->company_id, ...Arr::only($data, EmployeeProfile::SELF_EDITABLE)],
            );
        });

        return $user->load('profile');
    }
}
