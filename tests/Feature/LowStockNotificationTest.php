<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\ItemUnitMeasurement;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\User\StockLedgerService;
use App\Support\StockMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('low stock notification fires once until stock recovers', function () {
    Notification::fake();

    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
        'low_stock_threshold' => 5,
    ]);
    $ledger = app(StockLedgerService::class);

    $ledger->receive($item, $user, [
        'quantity' => 5,
        'type' => StockMovementType::OpeningBalance,
    ]);

    Notification::assertSentTo($user, LowStockNotification::class);
    expect(ItemStockBalance::query()->where('item_id', $item->id)->value('low_stock_notified'))->toBeTrue();

    Notification::fake();

    $lot = $item->stockLots()->first();
    $ledger->adjust($item, $user, [
        'stock_lot_id' => $lot->id,
        'type' => StockMovementType::AdjustmentOut,
        'quantity' => 1,
        'reason' => 'spoilage',
    ]);

    Notification::assertNothingSent();

    $ledger->adjust($item, $user, [
        'stock_lot_id' => $lot->id,
        'type' => StockMovementType::AdjustmentIn,
        'quantity' => 10,
        'reason' => 'replenish',
    ]);

    expect(ItemStockBalance::query()->where('item_id', $item->id)->value('low_stock_notified'))->toBeFalse();

    Notification::fake();

    $ledger->adjust($item, $user, [
        'stock_lot_id' => $lot->id,
        'type' => StockMovementType::AdjustmentOut,
        'quantity' => 10,
        'reason' => 'issue from warehouse',
    ]);

    Notification::assertSentTo($user, LowStockNotification::class);
});
