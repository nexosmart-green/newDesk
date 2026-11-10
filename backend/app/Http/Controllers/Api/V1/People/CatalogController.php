<?php

namespace App\Http\Controllers\Api\V1\People;

use App\Domain\Identity\Enums\Permission;
use App\Domain\People\Actions\DeleteCatalogItem;
use App\Domain\People\Actions\SaveCatalogItem;
use App\Domain\People\Enums\CatalogType;
use App\Domain\People\Models\CatalogItem;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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

    public function store(Request $request, CatalogType $catalog, SaveCatalogItem $save): JsonResponse
    {
        Gate::authorize(Permission::UsersManage->value);
        $request->validate(['name' => $this->nameRules($request, $catalog)]);

        $item = $save($request->user(), $catalog, $request->string('name')->trim()->value());

        return ApiResponse::ok($item->only('id', 'name'), 'Agregado.', 201);
    }

    public function update(Request $request, CatalogType $catalog, int $item, SaveCatalogItem $save): JsonResponse
    {
        Gate::authorize(Permission::UsersManage->value);
        $model = $this->find($catalog, $item);
        $request->validate(['name' => $this->nameRules($request, $catalog, $model->id)]);

        $model = $save($request->user(), $catalog, $request->string('name')->trim()->value(), $model);

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

    /** @return list<mixed> */
    private function nameRules(Request $request, CatalogType $catalog, ?int $ignoreId = null): array
    {
        return [
            'required', 'string', 'max:80',
            Rule::unique($catalog->table(), 'name')->where('company_id', $request->user()->company_id)->ignore($ignoreId),
        ];
    }
}
