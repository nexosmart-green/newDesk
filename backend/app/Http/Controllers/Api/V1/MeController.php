<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): CurrentUserResource
    {
        return CurrentUserResource::make($request->user());
    }
}
