<?php

namespace App\Domain\People\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * RF-01.04. Fields follow the "Decisión" column of docs/01-relevamiento (no marital status, no notice period).
 *
 * @property int $user_id
 * @property string|null $employee_code
 * @property int|null $job_title_id
 * @property int|null $area_id
 * @property int|null $employment_type_id
 * @property int|null $reports_to_user_id
 * @property string|null $phone
 * @property string|null $address
 * @property Carbon|null $birth_date
 * @property string|null $gender
 * @property string|null $about
 * @property Carbon|null $hired_at
 * @property Carbon|null $left_at
 * @property Carbon|null $probation_ends_at
 * @property string|null $cuil
 * @property string|null $emergency_contact
 */
class EmployeeProfile extends Model
{
    use BelongsToCompany;

    /** Fields the person edits about themselves (RF-01.09). */
    public const SELF_EDITABLE = ['phone', 'address', 'about'];

    /** Fields only `users.manage` edits (RF-01.09). */
    public const ADMIN_EDITABLE = [
        'employee_code', 'job_title_id', 'area_id', 'employment_type_id', 'reports_to_user_id',
        'phone', 'address', 'birth_date', 'gender', 'about', 'hired_at', 'left_at', 'probation_ends_at',
        'cuil', 'emergency_contact',
    ];

    /** Only visible to `users.manage` (RNF-06, P-12). */
    public const SENSITIVE = ['cuil', 'emergency_contact', 'address', 'birth_date', 'gender'];

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hired_at' => 'date',
            'left_at' => 'date',
            'probation_ends_at' => 'date',
            'cuil' => 'encrypted',
            'emergency_contact' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<JobTitle, $this> */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<EmploymentType, $this> */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reports_to_user_id');
    }
}
