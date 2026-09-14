<?php

namespace App\Http\Requests\User\Concerns;

use App\Enums\ItemKind;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\ItemUnitMeasurement;
use Illuminate\Validation\Validator;

trait ValidatesItemKind
{
    protected function assertCashUsesPhpUnit(Validator $validator): void
    {
        if (ItemKind::tryFrom((string) $this->input('kind')) !== ItemKind::Cash) {
            return;
        }

        $unitName = ItemUnitMeasurement::query()
            ->whereKey($this->integer('item_unit_measurement_id'))
            ->value('name');

        if (! is_string($unitName) || strcasecmp($unitName, 'php') !== 0) {
            $validator->errors()->add(
                'item_unit_measurement_id',
                'Cash items must use the php unit of measurement.',
            );
        }
    }

    protected function assertCannotChangeStockedGoodsKind(Validator $validator, Item $item): void
    {
        if ($item->kind !== ItemKind::Goods || ItemKind::tryFrom((string) $this->input('kind')) === ItemKind::Goods) {
            return;
        }

        $onHand = (int) ItemStockBalance::query()
            ->where('item_id', $item->id)
            ->where('department_id', $item->department_id)
            ->value('on_hand');

        if ($onHand > 0) {
            $validator->errors()->add(
                'kind',
                'Cannot change kind while this item still has stock on hand.',
            );
        }
    }
}
