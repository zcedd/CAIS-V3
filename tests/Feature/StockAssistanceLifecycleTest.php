<?php

use App\Enums\AssistanceItemOrigin;
use App\Enums\RequestSubStatusCode;
use App\Enums\StockMovementType;
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
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     user: User,
 *     department: Department,
 *     program: Program,
 *     assistance: Assistance,
 *     item: Item,
 *     assistance_item: AssistanceItem,
 *     delivered: int,
 *     denied: int,
 *     closed: int
 * }
 */
function seedDeliverableAssistance(): array
{
    $delivered = catalogReasonId(RequestSubStatusCode::Delivered);
    $denied = catalogReasonId(RequestSubStatusCode::Denied);
    $closed = catalogReasonId(RequestSubStatusCode::Closed);

    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'Relief',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program->item()->attach($item->id);
    seedProgramStock($program, $item, 10, $user);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => Beneficiary::create([
            'cais_number' => 'CAIS-STK',
            'name' => 'Juan',
            'beneficiable_type' => 'App\\Models\\Individual',
            'beneficiable_id' => 1,
        ])->id,
        'mode_of_request_id' => ModeOfRequest::create(['name' => 'Walk In'])->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    $assistanceItem = AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'origin' => AssistanceItemOrigin::Requested->value,
        'quantity' => 4,
        'requested_quantity' => 4,
        'is_received' => false,
    ]);

    return compact(
        'user',
        'department',
        'program',
        'assistance',
        'item',
        'assistanceItem',
        'delivered',
        'denied',
        'closed',
    );
}

function deliverAssistance(array $context, int $quantity = 4): void
{
    test()->actingAs($context['user'])->patch(
        route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]),
        [
            'request_sub_status_id' => $context['delivered'],
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [
                [
                    'assistance_item_id' => $context['assistanceItem']->id,
                    'quantity' => $quantity,
                ],
            ],
        ],
    )->assertSessionHasNoErrors();
}

test('delivery deducts requested additional and substitute quantities from program stock', function () {
    $context = seedDeliverableAssistance();
    $oil = Item::create([
        'name' => 'Oil',
        'department_id' => $context['department']->id,
        'item_unit_measurement_id' => $context['item']->item_unit_measurement_id,
    ]);
    $context['program']->item()->attach($oil->id);
    seedProgramStock($context['program'], $oil, 5, $context['user']);

    test()->actingAs($context['user'])->patch(
        route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]),
        [
            'request_sub_status_id' => $context['delivered'],
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [
                [
                    'assistance_item_id' => $context['assistanceItem']->id,
                    'quantity' => 2,
                ],
            ],
            'extra_items' => [
                [
                    'origin' => AssistanceItemOrigin::Additional->value,
                    'item_id' => $oil->id,
                    'quantity' => 1,
                    'fulfillment_reason' => 'leftover pack',
                ],
            ],
        ],
    )->assertSessionHasNoErrors();

    expect(ProgramItemStock::query()->where('item_id', $context['item']->id)->value('remaining'))->toBe(8)
        ->and(ProgramItemStock::query()->where('item_id', $oil->id)->value('remaining'))->toBe(4);
});

test('delivery deducts substitute quantities from program stock', function () {
    $context = seedDeliverableAssistance();
    $oil = Item::create([
        'name' => 'Oil',
        'department_id' => $context['department']->id,
        'item_unit_measurement_id' => $context['item']->item_unit_measurement_id,
    ]);
    $context['program']->item()->attach($oil->id);
    seedProgramStock($context['program'], $oil, 5, $context['user']);

    test()->actingAs($context['user'])->patch(
        route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]),
        [
            'request_sub_status_id' => $context['delivered'],
            'recorded_at' => now()->toDateTimeString(),
            'extra_items' => [
                [
                    'origin' => AssistanceItemOrigin::Substitute->value,
                    'item_id' => $oil->id,
                    'quantity' => 2,
                    'fulfillment_reason' => 'rice unavailable',
                    'substituted_for_assistance_item_id' => $context['assistanceItem']->id,
                ],
            ],
        ],
    )->assertSessionHasNoErrors();

    expect(ProgramItemStock::query()->where('item_id', $context['item']->id)->value('remaining'))->toBe(10)
        ->and(ProgramItemStock::query()->where('item_id', $oil->id)->value('remaining'))->toBe(3)
        ->and(StockMovement::query()->where('type', StockMovementType::Issue)->sum('quantity'))->toBe(2);
});

test('delivery is blocked when the program allocation is insufficient', function () {
    $context = seedDeliverableAssistance();

    test()->actingAs($context['user'])->from(route('user.programs.show', [
        'department' => $context['department']->slug,
        'program' => $context['program']->id,
    ]))->patch(
        route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]),
        [
            'request_sub_status_id' => $context['delivered'],
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [
                [
                    'assistance_item_id' => $context['assistanceItem']->id,
                    'quantity' => 4,
                ],
            ],
            'extra_items' => [
                [
                    'origin' => AssistanceItemOrigin::Additional->value,
                    'item_id' => $context['item']->id,
                    'quantity' => 20,
                    'fulfillment_reason' => 'extra sacks',
                ],
            ],
        ],
    )->assertSessionHasErrors('delivered_items');
});

test('denying a delivered assistance restores stock', function () {
    $context = seedDeliverableAssistance();
    deliverAssistance($context);

    expect(ProgramItemStock::query()->where('item_id', $context['item']->id)->value('remaining'))->toBe(6);

    test()->actingAs($context['user'])->patch(
        route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]),
        [
            'request_sub_status_id' => $context['denied'],
            'recorded_at' => now()->toDateTimeString(),
            'remark' => 'Request denied after delivery',
        ],
    )->assertSessionHasNoErrors();

    expect(ProgramItemStock::query()->where('item_id', $context['item']->id)->value('remaining'))->toBe(10)
        ->and(ItemStockBalance::query()->where('item_id', $context['item']->id)->value('on_hand'))->toBe(10);
});

test('deleting a delivered assistance restores stock', function () {
    $context = seedDeliverableAssistance();
    deliverAssistance($context);

    test()->actingAs($context['user'])->delete(route('user.programs.assistances.destroy', [
        'department' => $context['department']->slug,
        'program' => $context['program']->id,
        'assistance' => $context['assistance']->id,
    ]))->assertRedirect();

    expect(ProgramItemStock::query()->where('item_id', $context['item']->id)->value('remaining'))->toBe(10);
});

test('closing after resolution does not restore stock', function () {
    $context = seedDeliverableAssistance();
    deliverAssistance($context);

    test()->actingAs($context['user'])->patch(
        route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]),
        [
            'request_sub_status_id' => $context['closed'],
            'recorded_at' => now()->toDateTimeString(),
        ],
    )->assertSessionHasNoErrors();

    expect(ProgramItemStock::query()->where('item_id', $context['item']->id)->value('remaining'))->toBe(6)
        ->and(StockMovement::query()->where('type', StockMovementType::Restore)->count())->toBe(0);
});

test('delivering the same assistance again without new lines does not double-issue', function () {
    $context = seedDeliverableAssistance();
    deliverAssistance($context);

    expect(ProgramItemStock::query()->where('item_id', $context['item']->id)->value('remaining'))->toBe(6)
        ->and(StockMovement::query()->where('type', StockMovementType::Issue)->sum('quantity'))->toBe(4);

    test()->actingAs($context['user'])->from(route('user.programs.show', [
        'department' => $context['department']->slug,
        'program' => $context['program']->id,
    ]))->patch(
        route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]),
        [
            'request_sub_status_id' => $context['delivered'],
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [
                [
                    'assistance_item_id' => $context['assistanceItem']->id,
                    'quantity' => 4,
                ],
            ],
        ],
    )->assertSessionHasErrors('delivered_items.0.assistance_item_id');

    expect(ProgramItemStock::query()->where('item_id', $context['item']->id)->value('remaining'))->toBe(6)
        ->and(StockMovement::query()->where('type', StockMovementType::Issue)->sum('quantity'))->toBe(4);
});
