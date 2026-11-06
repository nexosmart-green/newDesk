<?php

namespace App\Http\Controllers\Api\V1\Companies;

use App\Domain\Companies\Actions\ReplaceCompanyLogo;
use App\Domain\Companies\Actions\UpdateCompanySettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RF-11: the authenticated user's company. Everyone reads it; `company.manage` edits it.
 */
class CompanyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::ok(CompanyResource::make($request->user()->company));
    }

    public function update(UpdateCompanyRequest $request, UpdateCompanySettings $updateSettings): JsonResponse
    {
        $company = $request->user()->company;
        Gate::authorize('update', $company);

        $company = $updateSettings($request->user(), $company, $request->companyData(), $request->settings());

        return ApiResponse::ok(CompanyResource::make($company), 'Configuración guardada.');
    }

    public function updateLogo(Request $request, ReplaceCompanyLogo $replaceLogo): JsonResponse
    {
        $company = $request->user()->company;
        Gate::authorize('update', $company);

        // No SVG: it can carry scripts.
        $request->validate(['logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        $company = $replaceLogo($request->user(), $company, $request->file('logo'));

        return ApiResponse::ok(CompanyResource::make($company), 'Logo actualizado.');
    }
}
