<?php

use App\Enums\AssistanceItemOrigin;
use App\Enums\RequestSubStatusCode;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     user: User,
 *     department: Department,
 *     program: Program,
 *     assistance: Assistance,
 *     rice_item: Item,
 *     oil_item: Item,
 *     noodles_item: Item,
 *     requested_rice: AssistanceItem,
 *     delivered_sub_status_id: int,
 *     verified_sub_status_id: int
 * }
 */
function seedAssistanceAwaitingRelease(int $requestedRiceQuantity = 2): array
{
    $verifiedSubStatusId = catalogReasonId(RequestSubStatusCode::Verified);
    $deliveredSubStatusId = catalogReasonId(RequestSubStatusCode::Delivered);

    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    $program = Program::create([
        'name' => 'Relief Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $riceItem = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $oilItem = Item::create([
        'name' => 'Cooking oil',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $noodlesItem = Item::create([
        'name' => 'Noodles',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program->item()->attach([$riceItem->id, $oilItem->id, $noodlesItem->id]);

    seedProgramStock($program, $riceItem, 50, $user);
    seedProgramStock($program, $oilItem, 50, $user);
    seedProgramStock($program, $noodlesItem, 50, $user);

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => ModeOfRequest::create(['name' => 'Walk In'])->id,
        'date_requested' => '2026-05-01',
        'user_id' => $user->id,
    ]);

    $requestedRice = AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $riceItem->id,
        'origin' => AssistanceItemOrigin::Requested->value,
        'quantity' => $requestedRiceQuantity,
        'requested_quantity' => $requestedRiceQuantity,
        'is_received' => false,
    ]);

    return [
        'user' => $user,
        'department' => $department,
        'program' => $program,
        'assistance' => $assistance,
        'rice_item' => $riceItem,
        'oil_item' => $oilItem,
        'noodles_item' => $noodlesItem,
        'requested_rice' => $requestedRice,
        'delivered_sub_status_id' => $deliveredSubStatusId,
        'verified_sub_status_id' => $verifiedSubStatusId,
    ];
}

/**
 * @param  array<string, mixed>  $context
 * @param  array<string, mixed>  $payload
 */
function releaseAssistanceItems(object $test, array $context, array $payload): TestResponse
{
    $programShowUrl = route('user.programs.show', [
        'department' => $context['department']->slug,
        'program' => $context['program']->id,
    ]);

    return $test->actingAs($context['user'])->from($programShowUrl)->patch(
        route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]),
        $payload,
    );
}

test('an additional item is released on its own line without touching the request', function () {
    $context = seedAssistanceAwaitingRelease();

    $response = releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'delivered_items' => [
            [
                'assistance_item_id' => $context['requested_rice']->id,
                'quantity' => 2,
            ],
        ],
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Additional->value,
                'item_id' => $context['oil_item']->id,
                'quantity' => 1,
                'fulfillment_reason' => 'leftover pack',
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $requestedRice = $context['requested_rice']->fresh();
    $additionalOil = AssistanceItem::query()
        ->where('assistance_id', $context['assistance']->id)
        ->where('item_id', $context['oil_item']->id)
        ->sole();

    expect($requestedRice->is_received)->toBeTrue()
        ->and($requestedRice->requested_quantity)->toBe(2)
        ->and($additionalOil->origin)->toBe(AssistanceItemOrigin::Additional)
        ->and($additionalOil->is_received)->toBeTrue()
        ->and($additionalOil->quantity)->toBe(1)
        ->and($additionalOil->requested_quantity)->toBe(0)
        ->and($additionalOil->fulfillment_reason)->toBe('leftover pack')
        ->and($additionalOil->substituted_for_assistance_item_id)->toBeNull();
});

test('a substitute retires the requested line it replaces instead of deleting it', function () {
    $context = seedAssistanceAwaitingRelease();

    $response = releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Substitute->value,
                'item_id' => $context['noodles_item']->id,
                'quantity' => 4,
                'fulfillment_reason' => 'rice stock ran out',
                'substituted_for_assistance_item_id' => $context['requested_rice']->id,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $requestedRice = $context['requested_rice']->fresh();
    $substituteNoodles = AssistanceItem::query()
        ->where('assistance_id', $context['assistance']->id)
        ->where('item_id', $context['noodles_item']->id)
        ->sole();

    expect($requestedRice->is_received)->toBeFalse()
        ->and($requestedRice->quantity)->toBe(2)
        ->and($requestedRice->requested_quantity)->toBe(2)
        ->and($requestedRice->isSubstituted())->toBeTrue()
        ->and($substituteNoodles->origin)->toBe(AssistanceItemOrigin::Substitute)
        ->and($substituteNoodles->is_received)->toBeTrue()
        ->and($substituteNoodles->requested_quantity)->toBe(0)
        ->and($substituteNoodles->substituted_for_assistance_item_id)->toBe($requestedRice->id);
});

test('substituting a requested line wins when that line was also selected for full release', function () {
    $context = seedAssistanceAwaitingRelease();

    $response = releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'delivered_items' => [
            [
                'assistance_item_id' => $context['requested_rice']->id,
                'quantity' => 2,
            ],
        ],
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Substitute->value,
                'item_id' => $context['noodles_item']->id,
                'quantity' => 4,
                'fulfillment_reason' => 'rice stock ran out',
                'substituted_for_assistance_item_id' => $context['requested_rice']->id,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $requestedRice = $context['requested_rice']->fresh();
    $substituteNoodles = AssistanceItem::query()
        ->where('assistance_id', $context['assistance']->id)
        ->where('item_id', $context['noodles_item']->id)
        ->sole();

    expect($requestedRice->is_received)->toBeFalse()
        ->and($requestedRice->isSubstituted())->toBeTrue()
        ->and($substituteNoodles->origin)->toBe(AssistanceItemOrigin::Substitute)
        ->and($substituteNoodles->is_received)->toBeTrue()
        ->and(
            AssistanceItem::query()
                ->where('assistance_id', $context['assistance']->id)
                ->where('item_id', $context['rice_item']->id)
                ->where('is_received', true)
                ->exists(),
        )->toBeFalse();
});

test('releasing more than the outstanding requested quantity is rejected', function () {
    $context = seedAssistanceAwaitingRelease();

    releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'delivered_items' => [
            [
                'assistance_item_id' => $context['requested_rice']->id,
                'quantity' => 3,
            ],
        ],
    ])->assertSessionHasErrors('delivered_items.0.quantity');

    expect($context['requested_rice']->fresh()->requested_quantity)->toBe(2);
});

test('an unrequested release requires a reason', function () {
    $context = seedAssistanceAwaitingRelease();

    releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Additional->value,
                'item_id' => $context['oil_item']->id,
                'quantity' => 1,
            ],
        ],
    ])->assertSessionHasErrors('extra_items.0.fulfillment_reason');
});

test('a substitute requires the requested line it replaces', function () {
    $context = seedAssistanceAwaitingRelease();

    releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Substitute->value,
                'item_id' => $context['noodles_item']->id,
                'quantity' => 4,
                'fulfillment_reason' => 'rice stock ran out',
            ],
        ],
    ])->assertSessionHasErrors('extra_items.0.substituted_for_assistance_item_id');
});

test('an item outside the program catalog cannot be released', function () {
    $context = seedAssistanceAwaitingRelease();

    $otherDepartment = Department::create(['name' => 'Department B']);
    $foreignItem = Item::create([
        'name' => 'Tarpaulin',
        'department_id' => $otherDepartment->id,
        'item_unit_measurement_id' => ItemUnitMeasurement::create(['name' => 'pc'])->id,
    ]);

    releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Additional->value,
                'item_id' => $foreignItem->id,
                'quantity' => 1,
                'fulfillment_reason' => 'on-site assessment',
            ],
        ],
    ])->assertSessionHasErrors('extra_items.0.item_id');
});

test('unrequested items cannot be attached to a status other than delivered', function () {
    $context = seedAssistanceAwaitingRelease();

    releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['verified_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Additional->value,
                'item_id' => $context['oil_item']->id,
                'quantity' => 1,
                'fulfillment_reason' => 'leftover pack',
            ],
        ],
    ])->assertSessionHasErrors('extra_items');
});

test('a substituted requested line can no longer be released', function () {
    $context = seedAssistanceAwaitingRelease();

    releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Substitute->value,
                'item_id' => $context['noodles_item']->id,
                'quantity' => 4,
                'fulfillment_reason' => 'rice stock ran out',
                'substituted_for_assistance_item_id' => $context['requested_rice']->id,
            ],
        ],
    ])->assertSessionHasNoErrors();

    releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-16',
        'delivered_items' => [
            [
                'assistance_item_id' => $context['requested_rice']->id,
                'quantity' => 2,
            ],
        ],
    ])->assertSessionHasErrors('delivered_items.0.assistance_item_id');
});

test('the assistance profile groups requested against released items', function () {
    $context = seedAssistanceAwaitingRelease();

    releaseAssistanceItems($this, $context, [
        'request_sub_status_id' => $context['delivered_sub_status_id'],
        'recorded_at' => '2026-05-15',
        'delivered_items' => [
            [
                'assistance_item_id' => $context['requested_rice']->id,
                'quantity' => 1,
            ],
        ],
        'extra_items' => [
            [
                'origin' => AssistanceItemOrigin::Additional->value,
                'item_id' => $context['oil_item']->id,
                'quantity' => 3,
                'fulfillment_reason' => 'leftover pack',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($context['user'])
        ->get(route('user.assistances.show', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $context['assistance']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('user/assistances/show')
            ->where('assistance.requested_items.0.name', 'Rice')
            ->where('assistance.requested_items.0.requested_quantity', 2)
            ->where('assistance.requested_items.0.released_quantity', 1)
            ->where('assistance.requested_items.0.pending_quantity', 1)
            ->where('assistance.item_variance.requested_quantity', 2)
            ->where('assistance.item_variance.fulfilled_quantity', 1)
            ->where('assistance.item_variance.additional_quantity', 3)
            ->where('assistance.item_variance.released_quantity', 4)
            ->where('assistance.item_variance.shortfall_quantity', 1)
            ->where('assistance.item_variance.has_variance', true)
            ->etc());
});
