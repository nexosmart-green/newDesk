<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Enums\CatalogType;
use App\Domain\People\Models\CatalogItem;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * Creates (when $item is null) or renames a catalog value.
 */
final class SaveCatalogItem
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, CatalogType $type, string $name, ?CatalogItem $item = null): CatalogItem
    {
        return DB::transaction(function () use ($actor, $type, $name, $item) {
            $before = $item?->only('name');
            $item ??= new ($type->modelClass())(['company_id' => $actor->company_id]);
            $item->name = $name;
            $item->save();

            ($this->recordActivity)($before === null ? 'catalog.created' : 'catalog.renamed', $item, $actor, $before, $item->only('name'));

            return $item;
        });
    }
}
