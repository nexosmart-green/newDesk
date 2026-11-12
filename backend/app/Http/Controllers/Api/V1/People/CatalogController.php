<?php

namespace App\Http\Controllers\Api\V1\People;

use App\Domain\Identity\Enums\Permission;
use App\Domain\People\Actions\DeleteCatalogItem;
use App\Domain\People\Actions\SaveCatalogItem;
use App\Domain\People\Enums\CatalogType;
use App\Domain\People\Models\CatalogItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\SaveCatalogItemRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RF-01.08 administrable catalogs: /catalogs/{job-titles|areas|employment-types|skills}.
 * Everyone reads them (profile forms, skill picker); `users.manage` edits them.
 */
class CatalogController extends Controller
{
    public function index(CatalogType $catalog): JsonResponse
    {
        return ApiResponse::ok($catalog->modelClass()::query()->orderBy('name')->get(['id', 'name']));
    }

    public function store(SaveCatalogItemRequest $request, CatalogType $catalog, SaveCatalogItem $save): JsonResponse
    {
        $item = $save($request->user(), $catalog, $request->itemName());

        return ApiResponse::ok($item->only('id', 'name'), 'Agregado.', 201);
    }

    public function update(SaveCatalogItemRequest $request, CatalogType $catalog, int $item, SaveCatalogItem $save): JsonResponse
    {
        $model = $save($request->user(), $catalog, $request->itemName(), $this->find($catalog, $item));

        return ApiResponse::ok($model->only('id', 'name'), 'Actualizado.');
    }

    public function destroy(Request $request, CatalogType $catalog, int $item, DeleteCatalogItem $delete): JsonResponse
    {
        Gate::authorize(Permission::UsersManage->value);

        $delete($request->user(), $catalog, $this->find($catalog, $item));

        return ApiResponse::ok(message: 'Borrado.');
    }

    private function find(CatalogType $catalog, int $id): CatalogItem
    {
        return $catalog->modelClass()::query()->findOrFail($id);
    }
}
