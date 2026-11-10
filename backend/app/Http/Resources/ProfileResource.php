<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\People\Models\EmployeeProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full profile (RF-01.04). Sensitive fields only for the person themself and `users.manage` (RNF-06).
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->profile ?? new EmployeeProfile;
        $canSeeSensitive = $request->user()?->can('viewSensitive', $this->resource) ?? false;

        $employment = [
            'employee_code' => $profile->employee_code,
            'employment_type' => $profile->employmentType?->only('id', 'name'),
            'reports_to' => $profile->reportsTo ? ['id' => $profile->reportsTo->id, 'full_name' => $profile->reportsTo->fullName()] : null,
            'phone' => $profile->phone,
            'about' => $profile->about,
            'hired_at' => $profile->hired_at?->toDateString(),
            'left_at' => $profile->left_at?->toDateString(),
            'probation_ends_at' => $profile->probation_ends_at?->toDateString(),
            'locale' => $this->locale,
            'timezone' => $this->timezone,
        ];

        $sensitive = $canSeeSensitive ? [
            'address' => $profile->address,
            'birth_date' => $profile->birth_date?->toDateString(),
            'gender' => $profile->gender,
            'cuil' => $profile->cuil,
            'emergency_contact' => $profile->emergency_contact,
        ] : [];

        return [
            ...PersonResource::make($this->resource->loadMissing(['profile.jobTitle', 'profile.area', 'skills']))->toArray($request),
            ...$employment,
            ...$sensitive,
        ];
    }
}
