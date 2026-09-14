<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\ProgramItemStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\User\StockLedgerService;
use App\Support\AssistanceItemOrigin;
use App\Support\StockMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array{department: Department, user: User, item: Item, program: Program, ledger: StockLedgerService}
 */
function stockLedgerContext(bool $perishable = false): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
        'is_perishable' => $perishable,
    ]);
    $program = Program::create([
        'name' => 'Relief',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $program->item()->attach($item->id);

    return [
        'department' => $department,
        'user' => $user,
        'item' => $item,
        'program' => $program,
        'ledger' => app(StockLedgerService::class),
    ];
}

test('receiving stock increases on-hand and available quantity', function () {
    ['user' => $user, 'item' => $item, 'ledger' => $ledger] = stockLedgerContext();

    $ledger->receive($item, $user, [
        'quantity' => 10,
        'type' => StockMovementType::OpeningBalance,
    ]);

    $balance = ItemStockBalance::query()->where('item_id', $item->id)->first();

    expect($balance)->not->toBeNull()
        ->and($balance->on_hand)->toBe(10)
        ->and($balance->allocated)->toBe(0)
        ->and($balance->available)->toBe(10)
        ->and(StockLot::query()->where('item_id', $item->id)->count())->toBe(1);
});

test('allocating more than available stock fails', function () {
    ['user' => $user, 'item' => $item, 'program' => $program, 'ledger' => $ledger] = stockLedgerContext();

    $ledger->receive($item, $user, [
        'quantity' => 5,
        'type' => StockMovementType::Receipt,
    ]);

    expect(fn () => $ledger->allocate($item, $user, [
        'program_id' => $program->id,
        'quantity' => 6,
    ]))->toThrow(InsufficientStockException::class);
});

test('issue uses FEFO across lots and skips expired stock', function () {
    ['user' => $user, 'item' => $item, 'program' => $program, 'ledger' => $ledger] = stockLedgerContext();

    $ledger->receive($item, $user, [
        'quantity' => 4,
        'type' => StockMovementType::Receipt,
        'batch_number' => 'EXP',
        'expires_at' => now()->subDay()->toDateString(),
    ]);
    $ledger->receive($item, $user, [
        'quantity' => 3,
        'type' => StockMovementType::Receipt,
        'batch_number' => 'SOON',
        'expires_at' => now()->addDays(5)->toDateString(),
    ]);
    $ledger->receive($item, $user, [
        'quantity' => 5,
        'type' => StockMovementType::Receipt,
        'batch_number' => 'LATER',
        'expires_at' => now()->addDays(30)->toDateString(),
    ]);
    $ledger->allocate($item, $user, [
        'program_id' => $program->id,
        'quantity' => 6,
    ]);

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-STOCK',
        'name' => 'Juan',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => ModeOfRequest::create(['name' => 'Walk In'])->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    $released = AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'origin' => AssistanceItemOrigin::Requested,
        'quantity' => 4,
        'requested_quantity' => 4,
        'is_received' => true,
    ]);

    $ledger->issue($released, $program, $user);

    $soonLot = StockLot::query()->where('batch_number', 'SOON')->first();
    $laterLot = StockLot::query()->where('batch_number', 'LATER')->first();
    $expiredLot = StockLot::query()->where('batch_number', 'EXP')->first();

    expect($soonLot->balance->on_hand)->toBe(0)
        ->and($laterLot->balance->on_hand)->toBe(4)
        ->and($expiredLot->balance->on_hand)->toBe(4)
        ->and(ProgramItemStock::query()->where('program_id', $program->id)->where('item_id', $item->id)->value('remaining'))->toBe(2)
        ->and(StockMovement::query()->where('type', StockMovementType::Issue)->count())->toBe(2);
});

test('perishable receipts require a batch and expiry date', function () {
    ['user' => $user, 'item' => $item, 'ledger' => $ledger] = stockLedgerContext(perishable: true);

    expect(fn () => $ledger->receive($item, $user, [
        'quantity' => 5,
        'type' => StockMovementType::Receipt,
    ]))->toThrow(ValidationException::class);
});

test('issuing the same released line twice does not double-deduct', function () {
    ['user' => $user, 'item' => $item, 'program' => $program, 'ledger' => $ledger] = stockLedgerContext();

    seedProgramStock($program, $item, 10, $user);

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-STOCK-2',
        'name' => 'Maria',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 2,
    ]);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => ModeOfRequest::create(['name' => 'Walk In'])->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    $released = AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'origin' => AssistanceItemOrigin::Requested,
        'quantity' => 3,
        'requested_quantity' => 3,
        'is_received' => true,
    ]);

    $ledger->issue($released, $program, $user);
    $ledger->issue($released, $program, $user);

    expect(StockMovement::query()->where('type', StockMovementType::Issue)->sum('quantity'))->toBe(3)
        ->and(ProgramItemStock::query()->where('program_id', $program->id)->where('item_id', $item->id)->value('remaining'))->toBe(7);
});

test('cash catalog items cannot receive warehouse stock', function () {
    ['user' => $user, 'program' => $program, 'ledger' => $ledger] = stockLedgerContext();
    $cash = Item::factory()->forDepartment($program->department)->cash()->create([
        'name' => 'Cash assistance',
    ]);
    $program->item()->attach($cash->id);

    expect(fn () => $ledger->receive($cash, $user, [
        'quantity' => 5000,
        'type' => StockMovementType::OpeningBalance,
    ]))->toThrow(ValidationException::class);
});
