<?php

namespace App\Http\Resources;

use App\Domain\Clients\Models\Client;
use Illuminate\Http\Request;

/**
 * @mixin Client
 */
class ClientResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'notes' => $this->notes,
            'active' => $this->active,
            'projects_count' => $this->whenCounted('projects'),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
