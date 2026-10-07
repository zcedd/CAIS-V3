<?php

use App\Actions\User\EvaluateAssistanceEligibility;
use App\Enums\DocumentRequirementMilestone;
use App\Enums\ProgramFieldType;
use App\Enums\ProgramKind;
use App\Enums\StockMovementType;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Fund;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\ProgramEligibilityRule;
use App\Models\ProgramField;
use App\Models\ProgramItemCap;
use App\Models\User;
use App\Services\User\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{department: Department, user: User, fund: Fund, item: Item}
 */
function createProgramBatchContext(): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    return compact('department', 'user', 'fund', 'item');
}

test('staff can create a parent program when public intake is off', function () {
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = createProgramBatchContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), [
            'name' => 'Agri Ka Dito',
            'descriptions' => 'Farm aid',
            'start_at' => '2026-10-05',
            'kind' => ProgramKind::Scheme->value,
            'item_ids' => [$item->id],
            'public_intake' => false,
            'first_batch' => [
                'batch_name' => 'June 2026 - December 2026',
                'start_at' => '2026-10-05',
                'fund_ids' => [$fund->id],
                'public_intake' => false,
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $scheme = Program::query()->where('name', 'Agri Ka Dito')->first();

    expect($scheme)->not->toBeNull()
        ->and($scheme->kind)->toBe(ProgramKind::Scheme)
        ->and($scheme->public_intake)->toBeFalse()
        ->and($scheme->batches()->first()?->public_intake)->toBeFalse();
});

test('staff cannot enable public intake when creating a parent program', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $this->actingAs($user)
        ->from(route('user.programs.index', ['department' => $department->slug]))
        ->post(route('user.programs.store', ['department' => $department->slug]), [
            'name' => 'Agri Ka Dito',
            'descriptions' => 'Farm aid',
            'start_at' => '2026-10-05',
            'kind' => ProgramKind::Scheme->value,
            'item_ids' => [$item->id],
            'public_intake' => true,
        ])
        ->assertRedirect(route('user.programs.index', ['department' => $department->slug]))
        ->assertSessionHasErrors('public_intake');

    expect(Program::query()->where('name', 'Agri Ka Dito')->exists())->toBeFalse();
});

test('staff can create a parent program without funds', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $this->actingAs($user)
        ->from(route('user.programs.index', ['department' => $department->slug]))
        ->post(route('user.programs.store', ['department' => $department->slug]), [
            'name' => 'Educational Assistance 2026',
            'descriptions' => 'School year aid',
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
            'kind' => ProgramKind::Scheme->value,
            'item_ids' => [$item->id],
        ])
        ->assertRedirect(route('user.programs.show', [
            'department' => $department->slug,
            'program' => Program::query()->where('name', 'Educational Assistance 2026')->value('id'),
        ]));

    $scheme = Program::query()->where('name', 'Educational Assistance 2026')->first();

    expect($scheme)->not->toBeNull()
        ->and($scheme->kind)->toBe(ProgramKind::Scheme)
        ->and($scheme->fund()->count())->toBe(0)
        ->and($scheme->item()->pluck('items.id')->all())->toBe([$item->id]);
});

test('creating a parent program can include the first batch', function () {
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = createProgramBatchContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), [
            'name' => 'Educational Assistance 2026',
            'descriptions' => 'School year aid',
            'start_at' => '2026-01-01',
            'kind' => ProgramKind::Scheme->value,
            'item_ids' => [$item->id],
            'fields' => [
                [
                    'label' => 'School',
                    'type' => ProgramFieldType::Text->value,
                    'is_required' => true,
                    'show_in_table' => false,
                ],
            ],
            'first_batch' => [
                'batch_name' => 'Q1',
                'start_at' => '2026-01-01',
                'end_at' => '2026-03-31',
                'fund_ids' => [$fund->id],
            ],
        ])
        ->assertRedirect();

    $scheme = Program::query()->where('kind', ProgramKind::Scheme)->first();
    $batch = Program::query()->where('kind', ProgramKind::Batch)->first();

    expect($scheme)->not->toBeNull()
        ->and($batch)->not->toBeNull()
        ->and($batch->parent_id)->toBe($scheme->id)
        ->and($batch->batch_name)->toBe('Q1')
        ->and($batch->name)->toBe('Educational Assistance 2026 - Q1')
        ->and($batch->is_organization)->toBeFalse()
        ->and($batch->item()->pluck('items.id')->all())->toBe([$item->id])
        ->and($batch->fund()->pluck('funds.id')->all())->toBe([$fund->id])
        ->and($batch->fields()->pluck('label')->all())->toBe(['School']);
});

test('adding a batch copies items fields and documents from the parent', function () {
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create([
        'name' => 'Relief 2026',
        'descriptions' => 'Parent',
    ]);
    $scheme->item()->attach($item->id);

    ProgramField::factory()->forProgram($scheme)->create([
        'label' => 'Barangay note',
        'type' => ProgramFieldType::Text->value,
    ]);

    $documentType = DocumentType::factory()->create();
    $scheme->documentRequirements()->create([
        'document_type_id' => $documentType->id,
        'is_required' => true,
        'required_before' => DocumentRequirementMilestone::Verified->value,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('user.programs.batches.store', [
            'department' => $department->slug,
            'program' => $scheme->id,
        ]), [
            'batch_name' => 'Batch 1',
            'start_at' => '2026-01-01',
            'end_at' => '2026-03-31',
            'fund_ids' => [$fund->id],
        ])
        ->assertRedirect(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $scheme->id,
        ]));

    $batch = $scheme->batches()->first();

    expect($batch)->not->toBeNull()
        ->and($batch->department_id)->toBe($department->id)
        ->and($batch->item()->pluck('items.id')->all())->toBe([$item->id])
        ->and($batch->fields()->pluck('label')->all())->toBe(['Barangay note'])
        ->and($batch->documentRequirements()->pluck('document_type_id')->all())->toBe([$documentType->id])
        ->and($batch->fields()->first()->id)->not->toBe($scheme->fields()->first()->id);
});

test('the programs index lists parents only', function () {
    ['department' => $department, 'user' => $user] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create(['name' => 'Parent']);
    Program::factory()->batch($scheme)->create();
    $standalone = Program::factory()->standalone()->forDepartment($department)->create(['name' => 'One-off']);

    $this->actingAs($user)
        ->get(route('user.programs.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/programs/index')
            ->has('programs.data', 2)
            ->where('programs.data.0.name', $standalone->name)
            ->where('programs.data.1.name', $scheme->name)
            ->where('programs.data.1.kind', ProgramKind::Scheme->value)
            ->where('programs.data.1.batches_count', 1));
});

test('searching a batch name returns the parent program on the index', function () {
    ['department' => $department, 'user' => $user] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create(['name' => 'Parent Aid']);
    Program::factory()->batch($scheme)->create(['batch_name' => 'Q3 Run']);

    $this->actingAs($user)
        ->get(route('user.programs.index', [
            'department' => $department->slug,
            'search' => 'Q3 Run',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/programs/index')
            ->has('programs.data', 1)
            ->where('programs.data.0.id', $scheme->id));
});

test('the parent program show page lists batches and does not include the assistance table', function () {
    ['department' => $department, 'user' => $user] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create(['name' => 'Parent']);
    Program::factory()->batch($scheme)->create(['batch_name' => 'Q1']);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $scheme->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/programs/scheme')
            ->where('program.kind', ProgramKind::Scheme->value)
            ->has('batches', 1)
            ->missing('assistances'));
});

test('staff cannot encode assistance on a parent program', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    $scheme->item()->attach($item->id);

    $individual = Individual::factory()->create();
    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    $this->actingAs($user)
        ->post(route('user.programs.assistances.store', [
            'department' => $department->slug,
            'program' => $scheme->id,
        ]), [
            'beneficiary_id' => $individual->beneficiaryRecord->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ])
        ->assertForbidden();

    expect(Assistance::query()->count())->toBe(0);
});

test('staff can encode assistance on a batch', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    $batch = Program::factory()->batch($scheme)->create();
    $batch->item()->attach($item->id);

    $individual = Individual::factory()->create();
    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    $this->actingAs($user)
        ->post(route('user.programs.assistances.store', [
            'department' => $department->slug,
            'program' => $batch->id,
        ]), [
            'beneficiary_id' => $individual->beneficiaryRecord->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ])
        ->assertRedirect();

    expect(Assistance::query()->where('program_id', $batch->id)->count())->toBe(1);
});

test('open request eligibility uses sibling batches and parent rules', function () {
    ['department' => $department, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    ProgramEligibilityRule::query()->create([
        'program_id' => $scheme->id,
        'cooldown_days' => null,
    ]);

    $first = Program::factory()->batch($scheme)->create();
    $second = Program::factory()->batch($scheme)->create();
    $first->item()->attach($item->id);
    $second->item()->attach($item->id);

    $individual = Individual::factory()->create();
    $beneficiary = $individual->beneficiaryRecord;

    Assistance::query()->create([
        'program_id' => $first->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => now()->toDateString(),
        'was_delivered' => false,
        'user_id' => User::factory()->create(['department_id' => $department->id])->id,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)(
        $second->fresh(),
        $beneficiary,
    );

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['code'])->toBe('open_request');
});

test('item caps on the parent apply across sibling batches', function () {
    ['department' => $department, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    ProgramItemCap::query()->create([
        'program_id' => $scheme->id,
        'item_id' => $item->id,
        'max_released_per_year' => 1,
    ]);

    $first = Program::factory()->batch($scheme)->create();
    $second = Program::factory()->batch($scheme)->create();
    $first->item()->attach($item->id);
    $second->item()->attach($item->id);

    $individual = Individual::factory()->create();
    $beneficiary = $individual->beneficiaryRecord;
    $assistance = Assistance::query()->create([
        'program_id' => $first->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => now()->toDateString(),
        'date_delivered' => now()->toDateString(),
        'was_delivered' => true,
        'user_id' => User::factory()->create(['department_id' => $department->id])->id,
    ]);
    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'is_received' => true,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)(
        $second->fresh(),
        $beneficiary,
        [['item_id' => $item->id, 'quantity' => 1]],
    );

    expect(collect($findings)->pluck('code')->all())->toContain('item_cap');
});

test('assistance can transfer to a sibling batch with a timeline reason', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $requestStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'In Progress',
    ]);
    DB::table('request_sub_statuses')->insert([
        'name' => 'In Progress',
        'request_status_id' => $requestStatusId,
        'description' => null,
    ]);

    $scheme = Program::factory()->scheme()->forDepartment($department)->create(['name' => 'Aid 2026']);
    $source = Program::factory()->batch($scheme)->create();
    $target = Program::factory()->batch($scheme)->create();
    $source->item()->attach($item->id);
    $target->item()->attach($item->id);

    $individual = Individual::factory()->create();
    $assistance = Assistance::query()->create([
        'program_id' => $source->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'is_received' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.transfer', [
            'department' => $department->slug,
            'program' => $source->id,
            'assistance' => $assistance->id,
        ]), [
            'target_program_id' => $target->id,
            'reason' => 'Applicant belongs in Q2',
        ])
        ->assertRedirect();

    expect($assistance->fresh()->program_id)->toBe($target->id);

    $remark = AssistanceRequestSubStatus::query()
        ->where('assistance_id', $assistance->id)
        ->latest('id')
        ->value('remark');

    expect($remark)->toContain('Applicant belongs in Q2');
});

test('assistance cannot transfer to a batch under a different parent', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $schemeA = Program::factory()->scheme()->forDepartment($department)->create();
    $schemeB = Program::factory()->scheme()->forDepartment($department)->create();
    $source = Program::factory()->batch($schemeA)->create();
    $other = Program::factory()->batch($schemeB)->create();
    $source->item()->attach($item->id);
    $other->item()->attach($item->id);

    $individual = Individual::factory()->create();
    $assistance = Assistance::query()->create([
        'program_id' => $source->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'is_received' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.transfer', [
            'department' => $department->slug,
            'program' => $source->id,
            'assistance' => $assistance->id,
        ]), [
            'target_program_id' => $other->id,
            'reason' => 'Wrong parent',
        ])
        ->assertSessionHasErrors('target_program_id');
});

test('a one-off program cannot transfer to a batch', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $standalone = Program::factory()->standalone()->forDepartment($department)->create();
    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    $batch = Program::factory()->batch($scheme)->create();
    $standalone->item()->attach($item->id);
    $batch->item()->attach($item->id);

    $individual = Individual::factory()->create();
    $assistance = Assistance::query()->create([
        'program_id' => $standalone->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'is_received' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.transfer', [
            'department' => $department->slug,
            'program' => $standalone->id,
            'assistance' => $assistance->id,
        ]), [
            'target_program_id' => $batch->id,
            'reason' => 'Wrong type',
        ])
        ->assertSessionHasErrors('target_program_id');
});

test('assistance transfer requires a reason', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    $source = Program::factory()->batch($scheme)->create();
    $target = Program::factory()->batch($scheme)->create();
    $source->item()->attach($item->id);
    $target->item()->attach($item->id);

    $individual = Individual::factory()->create();
    $assistance = Assistance::query()->create([
        'program_id' => $source->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'is_received' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.transfer', [
            'department' => $department->slug,
            'program' => $source->id,
            'assistance' => $assistance->id,
        ]), [
            'target_program_id' => $target->id,
        ])
        ->assertSessionHasErrors('reason');
});

test('closing the last open batch closes the parent program', function () {
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create([
        'name' => 'Aid 2026',
        'descriptions' => 'Parent',
        'start_at' => '2026-01-01',
    ]);
    $batch = Program::factory()->batch($scheme)->create([
        'start_at' => '2026-01-01',
    ]);
    $batch->item()->attach($item->id);
    $batch->fund()->attach($fund->id);

    $this->actingAs($user)
        ->put(route('user.programs.update', [
            'department' => $department->slug,
            'program' => $batch->id,
        ]), [
            'batch_name' => $batch->batch_name,
            'descriptions' => $batch->descriptions,
            'start_at' => '2026-01-01',
            'is_closed' => true,
            'fund_ids' => [$fund->id],
            'item_ids' => [$item->id],
        ])
        ->assertRedirect();

    expect($batch->fresh()->is_closed)->toBeTrue()
        ->and($scheme->fresh()->is_closed)->toBeTrue();
});

test('stock cannot be allocated to a parent program', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    $scheme->item()->attach($item->id);

    $this->actingAs($user)
        ->post(route('user.items.stock.receipts.store', [
            'department' => $department->slug,
            'item' => $item->id,
        ]), [
            'quantity' => 10,
            'type' => StockMovementType::OpeningBalance->value,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('user.items.stock.allocations.store', [
            'department' => $department->slug,
            'item' => $item->id,
        ]), [
            'program_id' => $scheme->id,
            'type' => StockMovementType::Allocate->value,
            'quantity' => 2,
        ])
        ->assertSessionHasErrors('program_id');
});

test('dashboard program filter on a parent includes batch assistances', function () {
    ['department' => $department, 'user' => $user, 'item' => $item] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create(['name' => 'Aid']);
    $batch = Program::factory()->batch($scheme)->create();
    $standalone = Program::factory()->standalone()->forDepartment($department)->create();

    $individualA = Individual::factory()->create();
    $individualB = Individual::factory()->create();

    Assistance::query()->create([
        'program_id' => $batch->id,
        'beneficiary_id' => $individualA->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    Assistance::query()->create([
        'program_id' => $standalone->id,
        'beneficiary_id' => $individualB->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.dashboard.index', [
            'department' => $department->slug,
            'program' => [$scheme->id],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('kpis', fn ($reload) => $reload
                ->where('summary.total_requests', 1)));
});

test('dashboard filter options list parents only', function () {
    ['department' => $department] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create(['name' => 'Aid']);
    $batch = Program::factory()->batch($scheme)->create();
    $standalone = Program::factory()->standalone()->forDepartment($department)->create(['name' => 'One-off']);

    $options = app(DashboardService::class)->filterOptions($department);
    $programIds = collect($options['programs'])->pluck('value')->all();

    expect($programIds)->toContain((string) $scheme->id)
        ->and($programIds)->toContain((string) $standalone->id)
        ->and($programIds)->not->toContain((string) $batch->id);
});

test('dashboard programs table rolls up parent batches', function () {
    ['department' => $department, 'user' => $user] = createProgramBatchContext();

    $scheme = Program::factory()->scheme()->forDepartment($department)->create(['name' => 'Aid']);
    $batch = Program::factory()->batch($scheme)->create();

    Assistance::query()->create([
        'program_id' => $batch->id,
        'beneficiary_id' => Individual::factory()->create()->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $row = collect(app(DashboardService::class)->programsTable($department, []))
        ->firstWhere('id', $scheme->id);

    expect($row)->not->toBeNull()
        ->and($row['total_requests'])->toBe(1)
        ->and($row['batches'])->toHaveCount(1)
        ->and($row['batches'][0]['total_requests'])->toBe(1);
});
