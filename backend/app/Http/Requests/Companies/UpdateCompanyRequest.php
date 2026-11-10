<?php

namespace App\Http\Requests\Companies;

use App\Domain\Companies\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'not_in:Default Company'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'timezone:all'],
            'settings' => ['sometimes', 'array:'.implode(',', array_keys(Company::DEFAULT_SETTINGS))],
            'settings.manual_entry_days_back' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'settings.forgotten_timer_max_hours' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'settings.forgotten_timer_after_shift_hours' => ['sometimes', 'integer', 'min:0', 'max:12'],
            'settings.hour_alert_thresholds' => ['sometimes', 'array', 'size:2'],
            'settings.hour_alert_thresholds.0' => ['required_with:settings.hour_alert_thresholds', 'integer', 'min:1', 'max:99'],
            'settings.hour_alert_thresholds.1' => ['required_with:settings.hour_alert_thresholds', 'integer', 'min:1', 'max:100', 'gt:settings.hour_alert_thresholds.0'],
        ];
    }

    /** @return array{name: string, tax_id: string|null, currency: string, timezone: string} */
    public function companyData(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'tax_id' => $this->input('tax_id'),
            'currency' => $this->string('currency')->value(),
            'timezone' => $this->string('timezone')->value(),
        ];
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return $this->validated('settings', []);
    }
}
