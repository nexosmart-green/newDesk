<?php

namespace App\Http\Requests\People;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $inCompany = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);
        /** @var User $person */
        $person = $this->route('user');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:80'],
            'last_name' => ['sometimes', 'required', 'string', 'max:80'],
            'employee_code' => [
                'sometimes', 'nullable', 'string', 'max:30',
                Rule::unique('employee_profiles', 'employee_code')->where('company_id', $companyId)->ignore($person->id, 'user_id'),
            ],
            'job_title_id' => ['sometimes', 'nullable', 'integer', $inCompany('job_titles')],
            'area_id' => ['sometimes', 'nullable', 'integer', $inCompany('areas')],
            'employment_type_id' => ['sometimes', 'nullable', 'integer', $inCompany('employment_types')],
            'reports_to_user_id' => ['sometimes', 'nullable', 'integer', Rule::notIn([$person->id]), $inCompany('users')],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:30'],
            'about' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'hired_at' => ['sometimes', 'nullable', 'date'],
            'left_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:hired_at'],
            'probation_ends_at' => ['sometimes', 'nullable', 'date'],
            'cuil' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}-?\d{8}-?\d$/'],
            'emergency_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
