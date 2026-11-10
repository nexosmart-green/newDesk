<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Enums\CatalogType;
use App\Domain\People\Models\CatalogItem;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

final class DeleteCatalogItem
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, CatalogType $type, CatalogItem $item): void
    {
        [$table, $column] = $type->usage();

        DB::transaction(function () use ($actor, $item, $table, $column) {
            if (DB::table($table)->where($column, $item->id)->exists()) {
                throw new BusinessRuleViolation(
                    "«{$item->name}» está en uso. Reasigná a las personas antes de borrarlo.",
                    'catalog_item_in_use',
                );
            }

            ($this->recordActivity)('catalog.deleted', $item, $actor, $item->only('name'));
            $item->delete();
        });
    }
}
