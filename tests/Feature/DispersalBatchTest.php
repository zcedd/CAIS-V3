<?php

use App\Actions\Dispersal\SaveDispersalApprovalPolicy;
use App\Enums\DispersalApprovalStrategy;
use App\Enums\DispersalBatchStatus;
use App\Enums\DispersalDecisionAction;
use App\Enums\DispersalItemApprovalStatus;
use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\DispersalApprovalDecision;
use App\Models\DispersalBatch;
use App\Models\DispersalBatchItem;
use App\Models\Individual;
use App\Models\Item;
use App\Models\Program;
use App\Models\User;
use App\Notifications\DispersalBatchNotification;
use App\Services\Dispersal\DispersalNotifier;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

/**
 * @return array<string, mixed>
 */
function dispersalFixture(string $kind = 'cash', int $requests = 1): array
{
    $department = Department::create(['name' => 'Social Welfare '.uniqid()]);
    $creator = User::factory()->create(['department_id' => $department->id]);
    $creator->syncRoles([RoleName::Program->value]);
    $governor = User::factory()->create(['department_id' => null]);
    $governor->syncRoles([RoleName::Governor->value]);
    $head = User::factory()->create(['department_id' => $department->id]);
    $head->syncRoles([RoleName::DepartmentHead->value]);
    $officer = User::factory()->create(['department_id' => $department->id]);
    $officer->syncRoles([RoleName::ReleasingOfficer->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $program = Program::factory()->forDepartment($department)->create();
    $item = Item::factory()->forDepartment($department)->when(
        $kind === 'cash',
        fn ($factory) => $factory->cash(),
        fn ($factory) => $factory,
    )->create(['name' => $kind === 'cash' ? 'Cash aid' : 'Rice']);
    $program->item()->attach($item->id);

    $assistances = [];

    for ($index = 0; $index < $requests; $index++) {
        $individual = Individual::factory()->create();
        $assistance = Assistance::query()->create([
            'program_id' => $program->id,
            'beneficiary_id' => $individual->beneficiaryRecord->id,
            'date_requested' => now()->toDateString(),
            'user_id' => $creator->id,
            'current_request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Verified),
        ]);
        AssistanceItem::query()->create([
            'assistance_id' => $assistance->id,
            'item_id' => $item->id,
            'quantity' => $kind === 'cash' ? 500 + ($index * 100) : 4,
            'requested_quantity' => $kind === 'cash' ? 500 + ($index * 100) : 4,
        ]);
        $assistances[] = $assistance;
    }

    return compact('department', 'creator', 'governor', 'head', 'officer', 'program', 'item', 'assistances');
}

function dispersalPayload(array $fixture, string $mode = 'batch'): array
{
    return [
        'program_id' => $fixture['program']->id,
        'title' => 'Barangay release',
        'description' => 'Scheduled payout',
        'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'venue' => 'Covered court',
        'approval_mode' => $mode,
        'assistance_ids' => collect($fixture['assistances'])->pluck('id')->all(),
    ];
}

function storeDispersal(array $fixture, string $mode = 'batch'): DispersalBatch
{
    test()->actingAs($fixture['creator'])
        ->post(route('user.dispersals.store', $fixture['department']), dispersalPayload($fixture, $mode))
        ->assertRedirect();

    return DispersalBatch::query()->latest('id')->firstOrFail();
}

function submitDispersal(array $fixture, DispersalBatch $batch): void
{
    test()->actingAs($fixture['creator'])
        ->post(route('user.dispersals.validate', [$fixture['department'], $batch]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    test()->actingAs($fixture['creator'])
        ->post(route('user.dispersals.submit', [$fixture['department'], $batch]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
}

test('authorized staff can create a dispersal batch', function () {
    $fixture = dispersalFixture();

    $batch = storeDispersal($fixture);

    expect($batch->batch_code)->toStartWith('DSP-'.now()->format('Y').'-')
        ->and($batch->status)->toBe(DispersalBatchStatus::Draft)
        ->and($batch->items)->toHaveCount(1)
        ->and($batch->items->first()->assistance_id)->toBe($fixture['assistances'][0]->id);

    test()->actingAs($fixture['creator'])
        ->get(route('user.dispersals.index', $fixture['department']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/dispersals/index')
            ->has('batches.data', 1));
});

test('the same assistance request cannot be added twice', function () {
    $fixture = dispersalFixture();
    $payload = dispersalPayload($fixture);
    $payload['assistance_ids'] = [$fixture['assistances'][0]->id, $fixture['assistances'][0]->id];

    test()->actingAs($fixture['creator'])
        ->post(route('user.dispersals.store', $fixture['department']), $payload)
        ->assertSessionHasErrors('assistance_ids.0');

    storeDispersal($fixture);

    test()->actingAs($fixture['creator'])
        ->post(route('user.dispersals.store', $fixture['department']), dispersalPayload($fixture))
        ->assertSessionHasErrors('assistance_ids');
});

test('a request that is not eligible cannot join a batch', function () {
    $fixture = dispersalFixture();
    $fixture['assistances'][0]->update(['current_request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Denied)]);

    test()->actingAs($fixture['creator'])
        ->post(route('user.dispersals.store', $fixture['department']), dispersalPayload($fixture))
        ->assertSessionHasErrors('assistance_ids');
});

test('an executive can approve a whole batch', function () {
    Notification::fake();
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $batch->refresh();

    expect($batch->status)->toBe(DispersalBatchStatus::Approved)
        ->and($batch->approved_cash_total)->toBe('500.00')
        ->and($batch->locked_at)->not->toBeNull();

    Notification::assertSentTo($fixture['governor'], DispersalBatchNotification::class);
});

test('an executive can return or reject a whole batch', function () {
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.decide', $batch), [
            'decision' => 'returned',
            'remarks' => 'Fix the venue.',
        ])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DispersalBatchStatus::Returned)
        ->and(DispersalApprovalDecision::query()->where('decision', 'returned')->exists())->toBeTrue();

    $rejected = storeDispersal(dispersalFixture());
    $second = dispersalFixture();
    $rejected = storeDispersal($second);
    submitDispersal($second, $rejected);

    test()->actingAs($second['governor'])
        ->post(route('executive.dispersals.decide', $rejected), [
            'decision' => 'rejected',
            'remarks' => 'Outside the program rules.',
        ])
        ->assertRedirect();

    expect($rejected->refresh()->status)->toBe(DispersalBatchStatus::Rejected);

    test()->actingAs($second['creator'])
        ->post(route('user.dispersals.reopen', [$second['department'], $rejected]), [
            'reason' => 'Try again',
        ])
        ->assertSessionHasErrors('status');
});

test('beneficiary review can approve return and exclude', function () {
    $fixture = dispersalFixture(requests: 3);
    $batch = storeDispersal($fixture, 'beneficiary');
    submitDispersal($fixture, $batch);
    $items = $batch->items()->orderBy('id')->get();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$batch, $items[0]]), [
            'decision' => 'approved',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$batch, $items[1]]), [
            'decision' => 'excluded',
            'exclusion_reason' => 'Did not meet the cutoff.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$batch->refresh(), $items[2]]), [
            'decision' => 'returned',
            'remarks' => 'Need a clearer ID.',
        ])
        ->assertRedirect();

    expect($items[0]->refresh()->approval_status)->toBe(DispersalItemApprovalStatus::Approved)
        ->and($items[1]->refresh()->approval_status)->toBe(DispersalItemApprovalStatus::Excluded)
        ->and($batch->refresh()->status)->toBe(DispersalBatchStatus::Returned)
        ->and(DispersalApprovalDecision::query()->count())->toBe(3);
});

test('amount changes are refused unless the policy allows them', function () {
    $fixture = dispersalFixture(requests: 1);
    $batch = storeDispersal($fixture, 'beneficiary');
    submitDispersal($fixture, $batch);
    $item = $batch->items()->firstOrFail();
    $line = $item->lines()->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$batch, $item]), [
            'decision' => 'approved',
            'remarks' => 'Reduce the grant.',
            'lines' => [['id' => $line->id, 'approved_amount' => '250']],
        ])
        ->assertForbidden();

    $allowedFixture = dispersalFixture();
    app(SaveDispersalApprovalPolicy::class)($allowedFixture['governor'], [
        'name' => 'Editable amounts',
        'department_id' => $allowedFixture['department']->id,
        'program_id' => $allowedFixture['program']->id,
        'approval_mode' => 'beneficiary',
        'strategy' => DispersalApprovalStrategy::Sequential->value,
        'allow_amount_modification' => true,
        'stages' => [['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Governor->value]],
    ]);

    $allowed = storeDispersal($allowedFixture, 'beneficiary');
    submitDispersal($allowedFixture, $allowed);
    $allowedItem = $allowed->items()->firstOrFail();
    $allowedLine = $allowedItem->lines()->firstOrFail();

    test()->actingAs($allowedFixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$allowed, $allowedItem]), [
            'decision' => 'approved',
            'remarks' => 'Reduce the grant.',
            'lines' => [['id' => $allowedLine->id, 'approved_amount' => '250']],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($allowedLine->refresh()->approved_amount)->toBe('250.00')
        ->and($allowed->refresh()->approved_cash_total)->toBe('250.00')
        ->and(DispersalApprovalDecision::query()->where('dispersal_batch_id', $allowed->id)->first()->decision)->toBe(DispersalDecisionAction::Modified);
});

test('finalization waits for every beneficiary unless partial approval is enabled', function () {
    $fixture = dispersalFixture(requests: 2);
    $batch = storeDispersal($fixture, 'beneficiary');
    submitDispersal($fixture, $batch);
    $first = $batch->items()->orderBy('id')->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$batch, $first]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.finalize', $batch))
        ->assertSessionHasErrors('roster');

    expect($batch->refresh()->status)->toBe(DispersalBatchStatus::ForApproval);

    $partialFixture = dispersalFixture(requests: 2);
    app(SaveDispersalApprovalPolicy::class)($partialFixture['governor'], [
        'name' => 'Partial allowed',
        'department_id' => $partialFixture['department']->id,
        'program_id' => $partialFixture['program']->id,
        'approval_mode' => 'beneficiary',
        'strategy' => DispersalApprovalStrategy::Sequential->value,
        'allow_partial_finalization' => true,
        'stages' => [['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Governor->value]],
    ]);

    $partial = storeDispersal($partialFixture, 'beneficiary');
    submitDispersal($partialFixture, $partial);
    $approved = $partial->items()->orderBy('id')->firstOrFail();

    test()->actingAs($partialFixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$partial, $approved]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($partialFixture['governor'])
        ->post(route('executive.dispersals.finalize', $partial))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $partial->refresh();

    expect($partial->status)->toBe(DispersalBatchStatus::Approved)
        ->and($partial->finalization_scope->value)->toBe('partial')
        ->and($partial->items()->where('approval_status', 'pending')->exists())->toBeTrue()
        ->and($partial->approved_beneficiary_count)->toBe(1);
});

test('sequential stages block later approvers and parallel stages need every approval', function () {
    $fixture = dispersalFixture();
    app(SaveDispersalApprovalPolicy::class)($fixture['head'], [
        'name' => 'Two steps',
        'department_id' => $fixture['department']->id,
        'program_id' => $fixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DispersalApprovalStrategy::Sequential->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Governor->value],
        ],
    ]);

    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();

    test()->actingAs($fixture['head'])
        ->post(route('user.dispersals.decide', [$fixture['department'], $batch]), ['decision' => 'approved'])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DispersalBatchStatus::ForApproval);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DispersalBatchStatus::Approved);

    app(SaveDispersalApprovalPolicy::class)($fixture['head'], [
        'name' => 'Parallel',
        'department_id' => $fixture['department']->id,
        'program_id' => $fixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DispersalApprovalStrategy::Parallel->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Governor->value],
        ],
    ]);

    $parallel = storeDispersal(dispersalFixture());
    $parallelFixture = dispersalFixture();
    app(SaveDispersalApprovalPolicy::class)($parallelFixture['head'], [
        'name' => 'Parallel specific',
        'department_id' => $parallelFixture['department']->id,
        'program_id' => $parallelFixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DispersalApprovalStrategy::Parallel->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Governor->value],
        ],
    ]);
    $parallel = storeDispersal($parallelFixture);
    submitDispersal($parallelFixture, $parallel);

    test()->actingAs($parallelFixture['head'])
        ->post(route('user.dispersals.decide', [$parallelFixture['department'], $parallel]), ['decision' => 'approved'])
        ->assertRedirect();

    expect($parallel->refresh()->status)->toBe(DispersalBatchStatus::ForApproval);

    test()->actingAs($parallelFixture['governor'])
        ->post(route('executive.dispersals.decide', $parallel), ['decision' => 'approved'])
        ->assertRedirect();

    expect($parallel->refresh()->status)->toBe(DispersalBatchStatus::Approved);
});

test('a creator cannot approve their own batch', function () {
    $fixture = dispersalFixture();
    $fixture['creator']->assignRole(RoleName::Governor->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);

    test()->actingAs($fixture['creator'])
        ->post(route('executive.dispersals.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();
});

test('another department cannot open the batch', function () {
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);
    $other = Department::create(['name' => 'Other office']);
    $intruder = User::factory()->create(['department_id' => $other->id]);
    $intruder->syncRoles([RoleName::Program->value]);

    test()->actingAs($intruder)
        ->get(route('user.dispersals.show', [$fixture['department'], $batch]))
        ->assertForbidden();
});

test('invalid status changes are rejected', function () {
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);

    test()->actingAs($fixture['officer'])
        ->post(route('user.dispersals.complete', [$fixture['department'], $batch]))
        ->assertSessionHasErrors('status');
});

test('a returned batch keeps history and starts a new revision on resubmit', function () {
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.decide', $batch), [
            'decision' => 'returned',
            'remarks' => 'Correct the title.',
        ])
        ->assertRedirect();

    $payload = dispersalPayload($fixture);
    $payload['title'] = 'Corrected release';
    $payload['lock_version'] = $batch->refresh()->lock_version;

    test()->actingAs($fixture['creator'])
        ->put(route('user.dispersals.update', [$fixture['department'], $batch]), $payload)
        ->assertRedirect();

    submitDispersal($fixture, $batch->refresh());

    expect($batch->refresh()->revision)->toBe(2)
        ->and($batch->status)->toBe(DispersalBatchStatus::ForApproval)
        ->and(DispersalApprovalDecision::query()->where('decision', 'returned')->exists())->toBeTrue()
        ->and($batch->revisions()->count())->toBe(2);
});

test('the same beneficiary cannot be approved twice for one revision', function () {
    $fixture = dispersalFixture(requests: 1);
    $batch = storeDispersal($fixture, 'beneficiary');
    submitDispersal($fixture, $batch);
    $item = $batch->items()->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$batch, $item]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.items.decide', [$batch->refresh(), $item]), ['decision' => 'approved'])
        ->assertSessionHasErrors('decision');
});

test('releases record absence partial service and stock movement', function () {
    $fixture = dispersalFixture(kind: 'goods', requests: 2);
    seedProgramStock($fixture['program'], $fixture['item'], 20, $fixture['officer']);
    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.dispersals.releases.store', [$fixture['department'], $batch]), [
            'dispersal_batch_item_id' => $batch->items()->first()->id,
            'outcome' => 'served',
        ])
        ->assertSessionHasErrors('status');

    test()->actingAs($fixture['creator'])
        ->post(route('user.dispersals.schedule', [$fixture['department'], $batch->refresh()]), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'venue' => 'Covered court',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.dispersals.start', [$fixture['department'], $batch->refresh()]))
        ->assertRedirect();

    $items = $batch->refresh()->items()->with('lines')->orderBy('id')->get();

    test()->actingAs($fixture['officer'])
        ->post(route('user.dispersals.releases.store', [$fixture['department'], $batch]), [
            'dispersal_batch_item_id' => $items[0]->id,
            'outcome' => 'absent',
            'remarks' => 'Did not attend.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.dispersals.releases.store', [$fixture['department'], $batch]), [
            'dispersal_batch_item_id' => $items[1]->id,
            'dispersal_batch_item_line_id' => $items[1]->lines->first()->id,
            'outcome' => 'partial',
            'actual_quantity' => 1,
            'identity_verified' => true,
            'receipt_reference' => 'ACK-1',
            'remarks' => 'Only one sack was on hand for this person.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.dispersals.releases.store', [$fixture['department'], $batch]), [
            'dispersal_batch_item_id' => $items[1]->id,
            'dispersal_batch_item_line_id' => $items[1]->lines->first()->id,
            'outcome' => 'served',
            'actual_quantity' => 4,
            'identity_verified' => true,
            'receipt_reference' => 'ACK-1',
        ])
        ->assertSessionHasErrors();

    expect($batch->refresh()->items()->find($items[0]->id)->releases)->toHaveCount(1);

    $this->assertDatabaseHas('stock_movements', [
        'item_id' => $fixture['item']->id,
        'type' => StockMovementType::Issue->value,
    ]);

    test()->actingAs($fixture['officer'])
        ->post(route('user.dispersals.complete', [$fixture['department'], $batch]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($batch->refresh()->status)->toBe(DispersalBatchStatus::Completed);
});

test('approval notices are not sent twice for the same event', function () {
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);
    $batch->refresh();

    $notifier = app(DispersalNotifier::class);
    $notifier->send(collect([$fixture['governor']]), $batch, 'dispersal-test-key', 'Title', 'Message');
    $notifier->send(collect([$fixture['governor']]), $batch, 'dispersal-test-key', 'Title', 'Message');

    expect($fixture['governor']->notifications()->where('data->dedupe_key', 'dispersal-test-key')->count())->toBe(1);
});

test('a release cannot be recorded before approval', function () {
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);

    test()->actingAs($fixture['officer'])
        ->post(route('user.dispersals.releases.store', [$fixture['department'], $batch]), [
            'dispersal_batch_item_id' => $batch->items()->first()->id,
            'outcome' => 'absent',
            'remarks' => 'Too early',
        ])
        ->assertSessionHasErrors('status');
});

test('decisions are kept in the audit log', function () {
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'dispersal',
        'event' => 'batch_decided',
        'subject_id' => $batch->id,
    ]);

    expect(DispersalApprovalDecision::query()->where('dispersal_batch_id', $batch->id)->count())->toBe(1);
});

test('unauthorized users cannot complete or cancel a batch', function () {
    $fixture = dispersalFixture();
    $batch = storeDispersal($fixture);
    $stranger = User::factory()->create(['department_id' => $fixture['department']->id]);
    $stranger->syncRoles([RoleName::Beneficiary->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->actingAs($stranger)
        ->post(route('user.dispersals.complete', [$fixture['department'], $batch]))
        ->assertForbidden();

    test()->actingAs($stranger)
        ->post(route('user.dispersals.cancel', [$fixture['department'], $batch]), ['reason' => 'No'])
        ->assertForbidden();
});

test('bulk approval still records a decision for each beneficiary', function () {
    $fixture = dispersalFixture(requests: 2);
    $batch = storeDispersal($fixture, 'beneficiary');
    submitDispersal($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.dispersals.items.bulk-decide', $batch), [
            'item_ids' => $batch->items()->pluck('id')->all(),
            'decision' => 'approved',
        ])
        ->assertRedirect();

    expect(DispersalBatchItem::query()->where('dispersal_batch_id', $batch->id)->where('approval_status', 'approved')->count())->toBe(2)
        ->and(DispersalApprovalDecision::query()->where('dispersal_batch_id', $batch->id)->count())->toBe(2);
});

test('super admin can save an approval policy without becoming the approver', function () {
    $fixture = dispersalFixture();
    $admin = User::factory()->create(['department_id' => $fixture['department']->id]);
    assignSuperAdminRole($admin);

    test()->actingAs($admin)
        ->post(route('admin.dispersal-policies.store'), [
            'name' => 'Office policy',
            'department_id' => $fixture['department']->id,
            'strategy' => 'sequential',
            'stages' => [
                ['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Governor->value],
            ],
        ])
        ->assertRedirect();

    $batch = storeDispersal($fixture);
    submitDispersal($fixture, $batch);

    test()->actingAs($admin)
        ->post(route('executive.dispersals.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();
});
