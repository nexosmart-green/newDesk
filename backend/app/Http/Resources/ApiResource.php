<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base resource: single items and collections both carry the `{ data, message, errors }` envelope.
 */
abstract class ApiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function with(Request $request): array
    {
        return ['message' => null, 'errors' => null];
    }

    public static function collection($resource): AnonymousResourceCollection
    {
        return parent::collection($resource)->additional(['message' => null, 'errors' => null]);
    }
}
