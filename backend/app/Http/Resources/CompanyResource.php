<?php

namespace App\Http\Resources;

use App\Domain\Companies\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tax_id' => $this->tax_id,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'settings' => array_map(fn (string $key) => $this->setting($key), array_combine(
                array_keys(Company::DEFAULT_SETTINGS),
                array_keys(Company::DEFAULT_SETTINGS),
            )),
        ];
    }
}
