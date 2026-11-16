<?php

namespace App\Http\Requests\People;

use App\Domain\Identity\Enums\Permission;
use App\Domain\People\Enums\CatalogType;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create (`POST /catalogs/{catalog}`) or rename (`PUT /catalogs/{catalog}/{item}`) a catalog entry (RF-01.08).
 * Names are unique per company within each catalog.
 */
class SaveCatalogItemRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect(Permission::UsersManage->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var CatalogType $catalog */
        $catalog = $this->route('catalog');
        $itemId = $this->route('item');

        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique($catalog->table(), 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($itemId === null ? null : (int) $itemId),
            ],
        ];
    }

    public function itemName(): string
    {
        return $this->string('name')->trim()->value();
    }
}
