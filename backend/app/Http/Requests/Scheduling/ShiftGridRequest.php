<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Support\Facades\Gate;

/** RF-06.02 weekly grid (people × days): `shifts.manage`. */
class ShiftGridRequest extends CalendarRangeRequest
{
    public function authorize(): bool
    {
        Gate::authorize(Permission::ShiftsManage->value);

        return true;
    }
}
