<?php

namespace App\Http\Resources;

use App\Domain\Scheduling\Models\Holiday;
use Illuminate\Http\Request;

/** @mixin Holiday */
class HolidayResource extends ApiResource
{
    /** @return array{id: int, name: string, scope: string, date: string} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scope' => $this->scope,
            'date' => $this->date->toDateString(),
        ];
    }
}
