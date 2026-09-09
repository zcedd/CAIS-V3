<?php

use App\Enums\ItemKind;
use App\Support\EmptyCell;

test('missing values use n/a as the empty cell filler', function () {
    expect(EmptyCell::VALUE)->toBe('N/A');
});

test('item quantities without a unit use the empty cell filler', function () {
    expect(ItemKind::formatQuantity(null, null, ItemKind::Goods))->toBe(EmptyCell::VALUE);
});
