<?php

namespace App\Http\Requests\People;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * `POST /people/{user}/rates` (RF-01.11). Authorization runs before validation.
 */
class AddHourlyRateRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('addRate', $this->route('user'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function amountCents(): int
    {
        return $this->integer('amount_cents');
    }

    public function validFrom(): Carbon
    {
        return Carbon::parse($this->string('valid_from')->value());
    }
}
