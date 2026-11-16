<?php

namespace App\Http\Requests\Clients;

use App\Domain\Clients\Support\ClientRules;
use Illuminate\Foundation\Http\FormRequest;

class SaveClientRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ClientRules::save();
    }
}
