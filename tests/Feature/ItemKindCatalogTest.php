<?php

use App\Enums\ItemKind;
use App\Enums\RequestSubStatusCode;
use App\Enums\StockMovementType;
use App\Enums\WorkflowTemplate;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\User\AssistanceItemFulfillmentService;
use App\Services\User\ProgramService;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const BACKFILL_CASH_KIND_MIGRATION = 'database/migrations/2026_09_02_023109_backfill_cash_kind_on_php_unit_items.php';

/**
 * @return array{
 *     user: User,
 *     department: Department,
 *     program: Program,
 *     php: ItemUnitMeasurement,
 *     session: ItemUnitMeasurement
 * }
 */
function itemKindCatalogContext(): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'AICS',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $php = ItemUnitMeasurement::create(['name' => 'php']);
    $session = ItemUnitMeasurement::create(['name' => 'session']);

    return compact('user', 'department', 'program', 'php', 'session');
}

test('authenticated users can create cash and service catalog items', function () {
    $context = itemKindCatalogContext();

    $this->actingAs($context['user'])
        ->post(route('user.items.store', ['department' => $context['department']->slug]), [
            'name' => 'Burial assistance',
            'kind' => ItemKind::Cash->value,
            'item_unit_measurement_id' => $context['php']->id,
            'is_perishable' => 1,
            'low_stock_threshold' => 10,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($context['user'])
        ->post(route('user.items.store', ['department' => $context['department']->slug]), [
            'name' => 'Medical consultation',
            'kind' => ItemKind::Service->value,
            'item_unit_measurement_id' => $context['session']->id,
            'is_perishable' => 1,
            'low_stock_threshold' => 5,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('items', [
        'name' => 'Burial assistance',
        'kind' => ItemKind::Cash->value,
        'is_perishable' => 0,
        'low_stock_threshold' => null,
    ]);
    $this->assertDatabaseHas('items', [
        'name' => 'Medical consultation',
        'kind' => ItemKind::Service->value,
        'is_perishable' => 0,
        'low_stock_threshold' => null,
    ]);

    $this->actingAs($context['user'])
        ->get(route('user.items.index', ['department' => $context['department']->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/items/index')
            ->where('items.data.0.kind', ItemKind::Cash->value)
            ->where('items.data.1.kind', ItemKind::Service->value));
});

test('cash items must use the php unit of measurement', function () {
    $context = itemKindCatalogContext();

    $this->actingAs($context['user'])
        ->post(route('user.items.store', ['department' => $context['department']->slug]), [
            'name' => 'Burial assistance',
            'kind' => ItemKind::Cash->value,
            'item_unit_measurement_id' => $context['session']->id,
        ])
        ->assertSessionHasErrors('item_unit_measurement_id');
});

test('stock cannot be received or allocated for cash items', function () {
    $context = itemKindCatalogContext();
    $item = Item::factory()->forDepartment($context['department'])->cash()->create([
        'name' => 'Cash assistance',
    ]);
    $context['program']->item()->attach($item->id);

    $this->actingAs($context['user'])
        ->from(route('user.items.index', ['department' => $context['department']->slug]))
        ->post(route('user.items.stock.receipts.store', [
            'department' => $context['department']->slug,
            'item' => $item->id,
        ]), [
            'quantity' => 5000,
            'type' => StockMovementType::OpeningBalance->value,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('item');

    $this->actingAs($context['user'])
        ->from(route('user.items.index', ['department' => $context['department']->slug]))
        ->post(route('user.items.stock.allocations.store', [
            'department' => $context['department']->slug,
            'item' => $item->id,
        ]), [
            'program_id' => $context['program']->id,
            'type' => StockMovementType::Allocate->value,
            'quantity' => 5000,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('item');

    expect(StockMovement::query()->count())->toBe(0)
        ->and(ItemStockBalance::query()->where('item_id', $item->id)->exists())->toBeFalse();
});

test('goods with on-hand stock cannot change to cash', function () {
    $context = itemKindCatalogContext();
    $item = Item::factory()->forDepartment($context['department'])->create([
        'name' => 'Rice',
        'item_unit_measurement_id' => $context['session']->id,
    ]);
    $context['program']->item()->attach($item->id);
    seedProgramStock($context['program'], $item, 4, $context['user']);

    $this->actingAs($context['user'])
        ->put(route('user.items.update', [
            'department' => $context['department']->slug,
            'item' => $item->id,
        ]), [
            'name' => 'Rice',
            'kind' => ItemKind::Cash->value,
            'item_unit_measurement_id' => $context['php']->id,
        ])
        ->assertSessionHasErrors('kind');

    expect($item->fresh()->kind)->toBe(ItemKind::Goods);
});

test('goods without stock can change to cash', function () {
    $context = itemKindCatalogContext();
    $item = Item::factory()->forDepartment($context['department'])->create([
        'name' => 'Emergency aid',
        'item_unit_measurement_id' => $context['session']->id,
    ]);

    $this->actingAs($context['user'])
        ->put(route('user.items.update', [
            'department' => $context['department']->slug,
            'item' => $item->id,
        ]), [
            'name' => 'Emergency aid',
            'kind' => ItemKind::Cash->value,
            'item_unit_measurement_id' => $context['php']->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($item->fresh()->kind)->toBe(ItemKind::Cash);
});

test('cash and service assistances can be encoded and delivered without warehouse stock', function () {
    $context = itemKindCatalogContext();
    $cash = Item::factory()->forDepartment($context['department'])->cash()->create([
        'name' => 'Burial assistance',
    ]);
    $service = Item::factory()->forDepartment($context['department'])->service()->create([
        'name' => 'Medical consultation',
        'item_unit_measurement_id' => $context['session']->id,
    ]);
    $context['program']->item()->attach([$cash->id, $service->id]);

    expect($cash->fresh()->kind)->toBe(ItemKind::Cash)
        ->and($cash->fresh()->tracksInventory())->toBeFalse()
        ->and($service->fresh()->kind)->toBe(ItemKind::Service)
        ->and($service->fresh()->tracksInventory())->toBeFalse();

    $walkIn = app(EnsureDepartmentWorkflow::class)->create(
        $context['department'],
        WorkflowTemplate::WalkIn,
        false,
        'Walk-in relief',
    );
    $context['program']->update(['workflow_id' => $walkIn->id]);

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-KIND',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    $this->actingAs($context['user'])
        ->post(route('user.programs.assistances.store', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
        ]), [
            'beneficiary_id' => $beneficiary->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateTimeString(),
            'item_details' => [
                [
                    'item_id' => $cash->id,
                    'quantity' => 5000,
                ],
                [
                    'item_id' => $service->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assistance = Assistance::query()->firstOrFail();
    $cashLine = AssistanceItem::query()
        ->where('assistance_id', $assistance->id)
        ->where('item_id', $cash->id)
        ->firstOrFail();
    $serviceLine = AssistanceItem::query()
        ->where('assistance_id', $assistance->id)
        ->where('item_id', $service->id)
        ->firstOrFail();

    $delivered = catalogReasonId(RequestSubStatusCode::Delivered);

    $this->actingAs($context['user'])
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $context['department']->slug,
            'program' => $context['program']->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => $delivered,
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [
                [
                    'assistance_item_id' => $cashLine->id,
                    'quantity' => 5000,
                ],
                [
                    'assistance_item_id' => $serviceLine->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(StockMovement::query()->count())->toBe(0)
        ->and($cashLine->fresh()->is_received)->toBeTrue()
        ->and($serviceLine->fresh()->is_received)->toBeTrue();

    $assistance->load([
        'assistanceItem.item.unitMeasurement',
    ]);

    $summary = app(AssistanceItemFulfillmentService::class)->summarize(
        $assistance->assistanceItem,
    );

    expect($summary['requested'][0]['kind'])->toBe(ItemKind::Cash)
        ->and($summary['requested'][0]['requested_quantity'])->toBe(5000)
        ->and($summary['released'][0]['kind'])->toBe(ItemKind::Cash)
        ->and($summary['released'][0]['quantity'])->toBe(5000)
        ->and($summary['released'][1]['kind'])->toBe(ItemKind::Service);
});

test('program item options expose kind and omit remaining for cash', function () {
    $context = itemKindCatalogContext();
    $cash = Item::factory()->forDepartment($context['department'])->cash()->create([
        'name' => 'Burial assistance',
    ]);
    $context['program']->item()->attach($cash->id);

    $options = app(ProgramService::class)->programItemsForSelect($context['program']);

    expect($options)->toHaveCount(1)
        ->and($options[0]['kind'])->toBe(ItemKind::Cash)
        ->and($options[0]['remaining'])->toBeNull();
});

test('backfill marks php unit items as cash', function () {
    Artisan::call('migrate:rollback', ['--path' => BACKFILL_CASH_KIND_MIGRATION]);

    $departmentId = DB::table('departments')->insertGetId([
        'name' => 'Backfill Department',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $phpId = DB::table('item_unit_measurements')->insertGetId([
        'name' => 'PHP',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $kgId = DB::table('item_unit_measurements')->insertGetId([
        'name' => 'kg',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $cashId = DB::table('items')->insertGetId([
        'name' => 'Cash aid',
        'kind' => ItemKind::Goods->value,
        'department_id' => $departmentId,
        'item_unit_measurement_id' => $phpId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $riceId = DB::table('items')->insertGetId([
        'name' => 'Rice',
        'kind' => ItemKind::Goods->value,
        'department_id' => $departmentId,
        'item_unit_measurement_id' => $kgId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => BACKFILL_CASH_KIND_MIGRATION]);

    expect(DB::table('items')->where('id', $cashId)->value('kind'))->toBe(ItemKind::Cash->value)
        ->and(DB::table('items')->where('id', $riceId)->value('kind'))->toBe(ItemKind::Goods->value);
});
