<?php

use App\Enums\StockMovementType;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\ItemUnitMeasurement;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\User\StockLedgerService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

test('the low stock sweep reminds the department when a threshold is set on stock already on hand', function () {
    Notification::fake();

    $department = Department::create(['name' => 'Department A']);
    $otherDepartment = Department::create(['name' => 'Department B']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $outsider = User::factory()->create(['department_id' => $otherDepartment->id]);
    $unit = ItemUnitMeasurement::factory()->create(['name' => 'kg']);
    $item = Item::factory()->forDepartment($department)->withUnitMeasurement($unit)->create([
        'name' => 'Rice',
        'low_stock_threshold' => null,
    ]);
    $stocked = Item::factory()->forDepartment($department)->withUnitMeasurement($unit)->create([
        'name' => 'Oil',
        'low_stock_threshold' => 5,
    ]);
    $ledger = app(StockLedgerService::class);

    $ledger->receive($item, $user, [
        'quantity' => 5,
        'type' => StockMovementType::OpeningBalance->value,
    ]);
    $ledger->receive($stocked, $user, [
        'quantity' => 10,
        'type' => StockMovementType::OpeningBalance->value,
    ]);

    $item->update(['low_stock_threshold' => 5]);

    $this->artisan('stock:notify-low')
        ->expectsOutput('Dispatched 1 low-stock notification(s).')
        ->assertSuccessful();

    Notification::assertSentTo(
        $user,
        LowStockNotification::class,
        function (LowStockNotification $notification) use ($user, $department, $item): bool {
            $payload = $notification->toArray($user);

            return $payload['category'] === 'stock'
                && $payload['title'] === 'Low stock alert'
                && $payload['item_id'] === $item->id
                && $payload['on_hand'] === 5
                && $payload['threshold'] === 5
                && $payload['message'] === 'Stock of Rice is at 5, at or below the threshold of 5.'
                && $payload['url'] === route('user.items.index', ['department' => $department->slug]);
        },
    );
    Notification::assertNotSentTo($outsider, LowStockNotification::class);
    expect(ItemStockBalance::query()->where('item_id', $item->id)->value('low_stock_notified'))->toBeTrue();

    $this->artisan('stock:notify-low')
        ->expectsOutput('Dispatched 0 low-stock notification(s).')
        ->assertSuccessful();

    Notification::assertSentToTimes($user, LowStockNotification::class, 1);
});

test('the low stock sweep runs once a day without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'stock:notify-low'));

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
