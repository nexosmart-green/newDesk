<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Collection counterpart of ApiResource: same envelope, plus Laravel's `links`/`meta` when paginated. */
class ApiResourceCollection extends AnonymousResourceCollection
{
    /** @return array{message: null, errors: null} */
    public function with(Request $request): array
    {
        return ['message' => null, 'errors' => null];
    }
}
