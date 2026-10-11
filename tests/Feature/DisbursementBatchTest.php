<?php

use App\Actions\Disbursement\SaveDisbursementApprovalPolicy;
use App\Enums\DisbursementApprovalStrategy;
use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementDecisionAction;
use App\Enums\DisbursementItemApprovalStatus;
use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Department;
use App\Models\DisbursementApprovalDecision;
use App\Models\DisbursementBatchItem;
use App\Models\DisbursementTransaction;
use App\Models\User;
use App\Notifications\DisbursementBatchNotification;
use App\Services\Disbursement\DisbursementNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

test('authorized staff can create a disbursement batch', function () {
    $fixture = disbursementFixture();

    $batch = storeDisbursement($fixture);

    expect($batch->batch_code)->toStartWith('DSB-'.now()->format('Y').'-')
        ->and($batch->status)->toBe(DisbursementBatchStatus::Draft)
        ->and($batch->items)->toHaveCount(1)
        ->and($batch->items->first()->assistance_id)->toBe($fixture['assistances'][0]->id);

    test()->actingAs($fixture['creator'])
        ->get(route('user.disbursements.index', $fixture['department']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/disbursements/index')
            ->has('batches.data', 1));
});

test('the same assistance request cannot be added twice', function () {
    $fixture = disbursementFixture();
    $payload = disbursementPayload($fixture);
    $payload['assistance_ids'] = [$fixture['assistances'][0]->id, $fixture['assistances'][0]->id];

    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.store', $fixture['department']), $payload)
        ->assertSessionHasErrors('assistance_ids.0');

    storeDisbursement($fixture);

    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.store', $fixture['department']), disbursementPayload($fixture))
        ->assertSessionHasErrors('assistance_ids');
});

test('a request that is not eligible cannot join a batch', function () {
    $fixture = disbursementFixture();
    $fixture['assistances'][0]->update(['current_request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Denied)]);

    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.store', $fixture['department']), disbursementPayload($fixture))
        ->assertSessionHasErrors('assistance_ids');
});

test('an executive can approve a whole batch', function () {
    Notification::fake();
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $batch->refresh();

    expect($batch->status)->toBe(DisbursementBatchStatus::Approved)
        ->and($batch->approved_cash_total)->toBe('500.00')
        ->and($batch->locked_at)->not->toBeNull();

    Notification::assertSentTo($fixture['governor'], DisbursementBatchNotification::class);
});

test('an executive can return or reject a whole batch', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.decide', $batch), [
            'decision' => 'returned',
            'remarks' => 'Fix the venue.',
        ])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DisbursementBatchStatus::Returned)
        ->and(DisbursementApprovalDecision::query()->where('decision', 'returned')->exists())->toBeTrue();

    $rejected = storeDisbursement(disbursementFixture());
    $second = disbursementFixture();
    $rejected = storeDisbursement($second);
    submitDisbursement($second, $rejected);

    test()->actingAs($second['governor'])
        ->post(route('executive.disbursements.decide', $rejected), [
            'decision' => 'rejected',
            'remarks' => 'Outside the program rules.',
        ])
        ->assertRedirect();

    expect($rejected->refresh()->status)->toBe(DisbursementBatchStatus::Rejected);

    test()->actingAs($second['creator'])
        ->post(route('user.disbursements.reopen', [$second['department'], $rejected]), [
            'reason' => 'Try again',
        ])
        ->assertSessionHasErrors('status');
});

test('beneficiary review can approve return and exclude', function () {
    $fixture = disbursementFixture(requests: 3);
    $batch = storeDisbursement($fixture, 'beneficiary');
    submitDisbursement($fixture, $batch);
    $items = $batch->items()->orderBy('id')->get();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch, $items[0]]), [
            'decision' => 'approved',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch, $items[1]]), [
            'decision' => 'excluded',
            'exclusion_reason' => 'Did not meet the cutoff.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch->refresh(), $items[2]]), [
            'decision' => 'returned',
            'remarks' => 'Need a clearer ID.',
        ])
        ->assertRedirect();

    expect($items[0]->refresh()->approval_status)->toBe(DisbursementItemApprovalStatus::Approved)
        ->and($items[1]->refresh()->approval_status)->toBe(DisbursementItemApprovalStatus::Excluded)
        ->and($batch->refresh()->status)->toBe(DisbursementBatchStatus::Returned)
        ->and(DisbursementApprovalDecision::query()->count())->toBe(3);
});

test('amount changes are refused unless the policy allows them', function () {
    $fixture = disbursementFixture(requests: 1);
    $batch = storeDisbursement($fixture, 'beneficiary');
    submitDisbursement($fixture, $batch);
    $item = $batch->items()->firstOrFail();
    $line = $item->lines()->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch, $item]), [
            'decision' => 'approved',
            'remarks' => 'Reduce the grant.',
            'lines' => [['id' => $line->id, 'approved_amount' => '250']],
        ])
        ->assertForbidden();

    $allowedFixture = disbursementFixture();
    app(SaveDisbursementApprovalPolicy::class)($allowedFixture['governor'], [
        'name' => 'Editable amounts',
        'department_id' => $allowedFixture['department']->id,
        'program_id' => $allowedFixture['program']->id,
        'approval_mode' => 'beneficiary',
        'strategy' => DisbursementApprovalStrategy::Sequential->value,
        'allow_amount_modification' => true,
        'stages' => [['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Executive->value]],
    ]);

    $allowed = storeDisbursement($allowedFixture, 'beneficiary');
    submitDisbursement($allowedFixture, $allowed);
    $allowedItem = $allowed->items()->firstOrFail();
    $allowedLine = $allowedItem->lines()->firstOrFail();

    test()->actingAs($allowedFixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$allowed, $allowedItem]), [
            'decision' => 'approved',
            'remarks' => 'Reduce the grant.',
            'lines' => [['id' => $allowedLine->id, 'approved_amount' => '250']],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($allowedLine->refresh()->approved_amount)->toBe('250.00')
        ->and($allowed->refresh()->approved_cash_total)->toBe('250.00')
        ->and(DisbursementApprovalDecision::query()->where('disbursement_batch_id', $allowed->id)->first()->decision)->toBe(DisbursementDecisionAction::Modified);
});

test('finalization waits for every beneficiary unless partial approval is enabled', function () {
    $fixture = disbursementFixture(requests: 2);
    $batch = storeDisbursement($fixture, 'beneficiary');
    submitDisbursement($fixture, $batch);
    $first = $batch->items()->orderBy('id')->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch, $first]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.finalize', $batch))
        ->assertSessionHasErrors('roster');

    expect($batch->refresh()->status)->toBe(DisbursementBatchStatus::ForApproval);

    $partialFixture = disbursementFixture(requests: 2);
    app(SaveDisbursementApprovalPolicy::class)($partialFixture['governor'], [
        'name' => 'Partial allowed',
        'department_id' => $partialFixture['department']->id,
        'program_id' => $partialFixture['program']->id,
        'approval_mode' => 'beneficiary',
        'strategy' => DisbursementApprovalStrategy::Sequential->value,
        'allow_partial_finalization' => true,
        'stages' => [['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Executive->value]],
    ]);

    $partial = storeDisbursement($partialFixture, 'beneficiary');
    submitDisbursement($partialFixture, $partial);
    $approved = $partial->items()->orderBy('id')->firstOrFail();

    test()->actingAs($partialFixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$partial, $approved]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($partialFixture['governor'])
        ->post(route('executive.disbursements.finalize', $partial))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $partial->refresh();

    expect($partial->status)->toBe(DisbursementBatchStatus::Approved)
        ->and($partial->finalization_scope->value)->toBe('partial')
        ->and($partial->items()->where('approval_status', 'pending')->exists())->toBeTrue()
        ->and($partial->approved_beneficiary_count)->toBe(1);
});

test('sequential stages block later approvers and parallel stages need every approval', function () {
    $fixture = disbursementFixture();
    app(SaveDisbursementApprovalPolicy::class)($fixture['head'], [
        'name' => 'Two steps',
        'department_id' => $fixture['department']->id,
        'program_id' => $fixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DisbursementApprovalStrategy::Sequential->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Executive->value],
        ],
    ]);

    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();

    test()->actingAs($fixture['head'])
        ->post(route('user.disbursements.decide', [$fixture['department'], $batch]), ['decision' => 'approved'])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DisbursementBatchStatus::ForApproval);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DisbursementBatchStatus::Approved);

    app(SaveDisbursementApprovalPolicy::class)($fixture['head'], [
        'name' => 'Parallel',
        'department_id' => $fixture['department']->id,
        'program_id' => $fixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DisbursementApprovalStrategy::Parallel->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Executive->value],
        ],
    ]);

    $parallel = storeDisbursement(disbursementFixture());
    $parallelFixture = disbursementFixture();
    app(SaveDisbursementApprovalPolicy::class)($parallelFixture['head'], [
        'name' => 'Parallel specific',
        'department_id' => $parallelFixture['department']->id,
        'program_id' => $parallelFixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DisbursementApprovalStrategy::Parallel->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Executive->value],
        ],
    ]);
    $parallel = storeDisbursement($parallelFixture);
    submitDisbursement($parallelFixture, $parallel);

    test()->actingAs($parallelFixture['head'])
        ->post(route('user.disbursements.decide', [$parallelFixture['department'], $parallel]), ['decision' => 'approved'])
        ->assertRedirect();

    expect($parallel->refresh()->status)->toBe(DisbursementBatchStatus::ForApproval);

    test()->actingAs($parallelFixture['governor'])
        ->post(route('executive.disbursements.decide', $parallel), ['decision' => 'approved'])
        ->assertRedirect();

    expect($parallel->refresh()->status)->toBe(DisbursementBatchStatus::Approved);
});

test('a creator cannot approve their own batch', function () {
    $fixture = disbursementFixture();
    $fixture['creator']->assignRole(RoleName::Executive->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['creator'])
        ->post(route('executive.disbursements.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();
});

test('another department cannot open the batch', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);
    $other = Department::create(['name' => 'Other office']);
    $intruder = User::factory()->create(['department_id' => $other->id]);
    $intruder->syncRoles([RoleName::Program->value]);

    test()->actingAs($intruder)
        ->get(route('user.disbursements.show', [$fixture['department'], $batch]))
        ->assertForbidden();
});

test('invalid status changes are rejected', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.complete', [$fixture['department'], $batch]))
        ->assertSessionHasErrors('status');
});

test('a returned batch keeps history and starts a new revision on resubmit', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.decide', $batch), [
            'decision' => 'returned',
            'remarks' => 'Correct the title.',
        ])
        ->assertRedirect();

    $payload = disbursementPayload($fixture);
    $payload['title'] = 'Corrected release';
    $payload['lock_version'] = $batch->refresh()->lock_version;

    test()->actingAs($fixture['creator'])
        ->put(route('user.disbursements.update', [$fixture['department'], $batch]), $payload)
        ->assertRedirect();

    submitDisbursement($fixture, $batch->refresh());

    expect($batch->refresh()->revision)->toBe(2)
        ->and($batch->status)->toBe(DisbursementBatchStatus::ForApproval)
        ->and(DisbursementApprovalDecision::query()->where('decision', 'returned')->exists())->toBeTrue()
        ->and($batch->revisions()->count())->toBe(2);
});

test('the same beneficiary cannot be approved twice for one revision', function () {
    $fixture = disbursementFixture(requests: 1);
    $batch = storeDisbursement($fixture, 'beneficiary');
    submitDisbursement($fixture, $batch);
    $item = $batch->items()->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch, $item]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch->refresh(), $item]), ['decision' => 'approved'])
        ->assertSessionHasErrors('decision');
});

test('releases record absence partial service and stock movement', function () {
    $fixture = disbursementFixture(kind: 'goods', requests: 2);
    seedProgramStock($fixture['program'], $fixture['item'], 20, $fixture['officer']);
    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.releases.store', [$fixture['department'], $batch]), [
            'disbursement_batch_item_id' => $batch->items()->first()->id,
            'outcome' => 'served',
        ])
        ->assertSessionHasErrors('status');

    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.schedule', [$fixture['department'], $batch->refresh()]), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'venue' => 'Covered court',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.start', [$fixture['department'], $batch->refresh()]))
        ->assertRedirect();

    $items = $batch->refresh()->items()->with('lines')->orderBy('id')->get();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.releases.store', [$fixture['department'], $batch]), [
            'disbursement_batch_item_id' => $items[0]->id,
            'outcome' => 'absent',
            'remarks' => 'Did not attend.',
        ])
        ->assertRedirect();

    Storage::fake('local');

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.releases.store', [$fixture['department'], $batch]), [
            'disbursement_batch_item_id' => $items[1]->id,
            'disbursement_batch_item_line_id' => $items[1]->lines->first()->id,
            'outcome' => 'partial',
            'actual_quantity' => 1,
            'identity_verified' => true,
            'receipt_reference' => 'ACK-1',
            'remarks' => 'Only one sack was on hand for this person.',
        ])
        ->assertSessionHasErrors('outcome');

    $key = (string) Str::uuid();
    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.store', [$fixture['department'], $batch, $items[1]]), [
            'idempotency_key' => $key,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $transaction = DisbursementTransaction::query()->where('idempotency_key', $key)->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->image('live.jpg'),
            'evidence_type' => 'live_photo',
            'capture_source' => 'camera',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->image('sign.png'),
            'evidence_type' => 'signature',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.finalize', [$fixture['department'], $batch, $transaction]), [
            'received_by_type' => 'beneficiary',
            'received_by_name' => 'Recipient Example',
            'verification_method' => 'control_number',
            'identity_verified' => true,
            'acknowledgment_mode' => 'signature',
            'privacy_acknowledged' => true,
            'notes' => 'Only one sack was on hand for this person.',
            'lines' => [[
                'id' => $items[1]->lines->first()->id,
                'actual_quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.releases.store', [$fixture['department'], $batch]), [
            'disbursement_batch_item_id' => $items[1]->id,
            'disbursement_batch_item_line_id' => $items[1]->lines->first()->id,
            'outcome' => 'served',
            'actual_quantity' => 4,
            'identity_verified' => true,
            'receipt_reference' => 'ACK-1',
        ])
        ->assertSessionHasErrors('outcome');

    expect($batch->refresh()->items()->find($items[0]->id)->releases)->toHaveCount(1);

    $this->assertDatabaseHas('stock_movements', [
        'item_id' => $fixture['item']->id,
        'type' => StockMovementType::Issue->value,
    ]);

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.complete', [$fixture['department'], $batch]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($batch->refresh()->status)->toBe(DisbursementBatchStatus::Completed);
});

test('approval notices are not sent twice for the same event', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);
    $batch->refresh();

    $notifier = app(DisbursementNotifier::class);
    $notifier->send(collect([$fixture['governor']]), $batch, 'disbursement-test-key', 'Title', 'Message');
    $notifier->send(collect([$fixture['governor']]), $batch, 'disbursement-test-key', 'Title', 'Message');

    expect($fixture['governor']->notifications()->where('data->dedupe_key', 'disbursement-test-key')->count())->toBe(1);
});

test('a release cannot be recorded before approval', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.releases.store', [$fixture['department'], $batch]), [
            'disbursement_batch_item_id' => $batch->items()->first()->id,
            'outcome' => 'absent',
            'remarks' => 'Too early',
        ])
        ->assertSessionHasErrors('status');
});

test('decisions are kept in the audit log', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'disbursement',
        'event' => 'batch_decided',
        'subject_id' => $batch->id,
    ]);

    expect(DisbursementApprovalDecision::query()->where('disbursement_batch_id', $batch->id)->count())->toBe(1);
});

test('unauthorized users cannot complete or cancel a batch', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);
    $stranger = User::factory()->create(['department_id' => $fixture['department']->id]);
    $stranger->syncRoles([RoleName::Beneficiary->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->actingAs($stranger)
        ->post(route('user.disbursements.complete', [$fixture['department'], $batch]))
        ->assertForbidden();

    test()->actingAs($stranger)
        ->post(route('user.disbursements.cancel', [$fixture['department'], $batch]), ['reason' => 'No'])
        ->assertForbidden();
});

test('bulk approval still records a decision for each beneficiary', function () {
    $fixture = disbursementFixture(requests: 2);
    $batch = storeDisbursement($fixture, 'beneficiary');
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.bulk-decide', $batch), [
            'item_ids' => $batch->items()->pluck('id')->all(),
            'decision' => 'approved',
        ])
        ->assertRedirect();

    expect(DisbursementBatchItem::query()->where('disbursement_batch_id', $batch->id)->where('approval_status', 'approved')->count())->toBe(2)
        ->and(DisbursementApprovalDecision::query()->where('disbursement_batch_id', $batch->id)->count())->toBe(2);
});

test('super admin can save an approval policy without becoming the approver', function () {
    $fixture = disbursementFixture();
    $admin = User::factory()->create(['department_id' => $fixture['department']->id]);
    assignSuperAdminRole($admin);

    test()->actingAs($admin)
        ->post(route('admin.disbursement-policies.store'), [
            'name' => 'Office policy',
            'department_id' => $fixture['department']->id,
            'strategy' => 'sequential',
            'stages' => [
                ['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Executive->value],
            ],
        ])
        ->assertRedirect();

    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($admin)
        ->post(route('executive.disbursements.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();
});
