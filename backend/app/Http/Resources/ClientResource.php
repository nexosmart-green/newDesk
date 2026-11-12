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
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'display_name' => $this->displayName(),
            'tax_id' => $this->tax_id,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'projects_count' => $this->whenCounted('projects'),
            'projects' => ProjectSummaryResource::collection($this->whenLoaded('projects')),
        ];
    }
}
