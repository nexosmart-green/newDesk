<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base of every resource: returned directly (`XResource::make()` / `XResource::collection($paginator)`)
 * it already carries the `{ data, message, errors }` envelope. Extra top-level keys (`meta`) go in `additional()`.
 */
abstract class ApiResource extends JsonResource
{
    /** @return array{message: null, errors: null} */
    public function with(Request $request): array
    {
        return ['message' => null, 'errors' => null];
    }

    protected static function newCollection($resource): ApiResourceCollection
    {
        return new ApiResourceCollection($resource, static::class);
    }
}
