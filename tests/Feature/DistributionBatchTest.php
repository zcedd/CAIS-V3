<?php

use App\Actions\Distribution\SaveDistributionApprovalPolicy;
use App\Enums\DistributionApprovalStrategy;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionDecisionAction;
use App\Enums\DistributionItemApprovalStatus;
use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Enums\WorkflowStepType;
use App\Enums\WorkflowTaskStatus;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\DistributionApprovalDecision;
use App\Models\DistributionBatchItem;
use App\Models\DistributionTransaction;
use App\Models\Item;
use App\Models\User;
use App\Notifications\DistributionBatchNotification;
use App\Services\Distribution\DistributionBatchDetails;
use App\Services\Distribution\DistributionNotifier;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

test('authorized staff can create a distribution batch', function () {
    $fixture = distributionFixture();

    $batch = storeDistribution($fixture);

    expect($batch->batch_code)->toStartWith('DSB-'.now()->format('Y').'-')
        ->and($batch->status)->toBe(DistributionBatchStatus::Draft)
        ->and($batch->items)->toHaveCount(1)
        ->and($batch->items->first()->assistance_id)->toBe($fixture['assistances'][0]->id);

    test()->actingAs($fixture['creator'])
        ->get(route('user.distributions.index', $fixture['department']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/distributions/index')
            ->has('batches.data', 1));
});

test('the same assistance request cannot be added twice', function () {
    $fixture = distributionFixture();
    $payload = distributionPayload($fixture);
    $payload['assistance_ids'] = [$fixture['assistances'][0]->id, $fixture['assistances'][0]->id];

    test()->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), $payload)
        ->assertSessionHasErrors('assistance_ids.0');

    storeDistribution($fixture);

    test()->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture))
        ->assertSessionHasErrors('assistance_ids');
});

test('a request that is not eligible cannot join a batch', function () {
    $fixture = distributionFixture();
    $fixture['assistances'][0]->update(['current_request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Denied)]);

    test()->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture))
        ->assertSessionHasErrors('assistance_ids');
});

test('distribution status is copied onto the request and its workflow task', function () {
    $fixture = distributionFixture();
    $assistance = $fixture['assistances'][0];
    $engine = app(WorkflowEngine::class);
    $engine->start($assistance, $fixture['creator'], false);

    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    $assistance->refresh();

    expect($assistance->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ForDistributionApproval)
        ->and($engine->currentTask($assistance)?->step?->step_type)->toBe(WorkflowStepType::Approval);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assistance->refresh();

    expect($assistance->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ReadyForRelease)
        ->and($engine->currentTask($assistance)?->step?->step_type)->toBe(WorkflowStepType::Preparation);

    test()->actingAs($fixture['creator'])
        ->post(route('user.distributions.schedule', [$fixture['department'], $batch->refresh()]), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'venue' => 'Covered court',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assistance->refresh();
    $scheduledTask = $engine->currentTask($assistance);

    expect($assistance->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ScheduledForRelease)
        ->and($scheduledTask?->step?->step_type)->toBe(WorkflowStepType::Preparation)
        ->and($scheduledTask?->remarks)->toBe('The assistance request is scheduled for release.');

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.start', [$fixture['department'], $batch->refresh()]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assistance->refresh();

    expect($assistance->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ReleaseInProgress)
        ->and($engine->currentTask($assistance)?->status)->toBe(WorkflowTaskStatus::InProgress)
        ->and($engine->currentTask($assistance)?->step?->step_type)->toBe(WorkflowStepType::Preparation);
});

test('an executive can approve a whole batch', function () {
    Notification::fake();
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $batch->refresh();

    expect($batch->status)->toBe(DistributionBatchStatus::Approved)
        ->and($batch->approved_cash_total)->toBe('500.00')
        ->and($batch->locked_at)->not->toBeNull()
        ->and($fixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ReadyForRelease);

    Notification::assertSentTo($fixture['governor'], DistributionBatchNotification::class);
});

test('an executive can return or reject a whole batch', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), [
            'decision' => 'returned',
            'remarks' => 'Fix the venue.',
        ])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DistributionBatchStatus::Returned)
        ->and(DistributionApprovalDecision::query()->where('decision', 'returned')->exists())->toBeTrue();

    $rejected = storeDistribution(distributionFixture());
    $second = distributionFixture();
    $rejected = storeDistribution($second);
    submitDistribution($second, $rejected);

    test()->actingAs($second['governor'])
        ->post(route('executive.distributions.decide', $rejected), [
            'decision' => 'rejected',
            'remarks' => 'Outside the program rules.',
        ])
        ->assertRedirect();

    expect($rejected->refresh()->status)->toBe(DistributionBatchStatus::Rejected)
        ->and($second['assistances'][0]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::DistributionRejected);

    test()->actingAs($second['creator'])
        ->post(route('user.distributions.reopen', [$second['department'], $rejected]), [
            'reason' => 'Try again',
        ])
        ->assertSessionHasErrors('status');
});

test('an approver can approve one beneficiary or the whole batch', function () {
    $fixture = distributionFixture(requests: 2);
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);
    $first = $batch->items()->orderBy('id')->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch, $first]), [
            'decision' => 'approved',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($first->refresh()->approval_status)->toBe(DistributionItemApprovalStatus::Approved)
        ->and($batch->refresh()->status)->toBe(DistributionBatchStatus::ForApproval);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($batch->refresh()->status)->toBe(DistributionBatchStatus::Approved)
        ->and($batch->items()->where('approval_status', 'approved')->count())->toBe(2);
});

test('beneficiary review can approve return and exclude', function () {
    $fixture = distributionFixture(requests: 3);
    $batch = storeDistribution($fixture, 'beneficiary');
    submitDistribution($fixture, $batch);
    $items = $batch->items()->orderBy('id')->get();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch, $items[0]]), [
            'decision' => 'approved',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch, $items[1]]), [
            'decision' => 'excluded',
            'exclusion_reason' => 'Did not meet the cutoff.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch->refresh(), $items[2]]), [
            'decision' => 'returned',
            'remarks' => 'Need a clearer ID.',
        ])
        ->assertRedirect();

    expect($items[0]->refresh()->approval_status)->toBe(DistributionItemApprovalStatus::Approved)
        ->and($items[1]->refresh()->approval_status)->toBe(DistributionItemApprovalStatus::Excluded)
        ->and($batch->refresh()->status)->toBe(DistributionBatchStatus::Returned)
        ->and(DistributionApprovalDecision::query()->count())->toBe(3);
});

test('amount changes are refused unless the policy allows them', function () {
    $fixture = distributionFixture(requests: 1);
    $batch = storeDistribution($fixture, 'beneficiary');
    submitDistribution($fixture, $batch);
    $item = $batch->items()->firstOrFail();
    $line = $item->lines()->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch, $item]), [
            'decision' => 'approved',
            'remarks' => 'Reduce the grant.',
            'lines' => [['id' => $line->id, 'approved_amount' => '250']],
        ])
        ->assertForbidden();

    $allowedFixture = distributionFixture();
    app(SaveDistributionApprovalPolicy::class)($allowedFixture['governor'], [
        'name' => 'Editable amounts',
        'department_id' => $allowedFixture['department']->id,
        'program_id' => $allowedFixture['program']->id,
        'approval_mode' => 'beneficiary',
        'strategy' => DistributionApprovalStrategy::Sequential->value,
        'allow_amount_modification' => true,
        'stages' => [['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Executive->value]],
    ]);

    $allowed = storeDistribution($allowedFixture, 'beneficiary');
    submitDistribution($allowedFixture, $allowed);
    $allowedItem = $allowed->items()->firstOrFail();
    $allowedLine = $allowedItem->lines()->firstOrFail();

    test()->actingAs($allowedFixture['governor'])
        ->post(route('executive.distributions.items.decide', [$allowed, $allowedItem]), [
            'decision' => 'approved',
            'remarks' => 'Reduce the grant.',
            'lines' => [['id' => $allowedLine->id, 'approved_amount' => '250']],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($allowedLine->refresh()->approved_amount)->toBe('250.00')
        ->and($allowed->refresh()->approved_cash_total)->toBe('250.00')
        ->and(DistributionApprovalDecision::query()->where('distribution_batch_id', $allowed->id)->first()->decision)->toBe(DistributionDecisionAction::Modified);
});

test('finalization waits for every beneficiary unless partial approval is enabled', function () {
    $fixture = distributionFixture(requests: 2);
    $batch = storeDistribution($fixture, 'beneficiary');
    submitDistribution($fixture, $batch);
    $first = $batch->items()->orderBy('id')->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch, $first]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.finalize', $batch))
        ->assertSessionHasErrors('roster');

    expect($batch->refresh()->status)->toBe(DistributionBatchStatus::ForApproval)
        ->and($fixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ForDistributionApproval);

    $partialFixture = distributionFixture(requests: 2);
    app(SaveDistributionApprovalPolicy::class)($partialFixture['governor'], [
        'name' => 'Partial allowed',
        'department_id' => $partialFixture['department']->id,
        'program_id' => $partialFixture['program']->id,
        'approval_mode' => 'beneficiary',
        'strategy' => DistributionApprovalStrategy::Sequential->value,
        'allow_partial_finalization' => true,
        'stages' => [['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Executive->value]],
    ]);

    $partial = storeDistribution($partialFixture, 'beneficiary');
    submitDistribution($partialFixture, $partial);
    $approved = $partial->items()->orderBy('id')->firstOrFail();

    test()->actingAs($partialFixture['governor'])
        ->post(route('executive.distributions.items.decide', [$partial, $approved]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($partialFixture['governor'])
        ->post(route('executive.distributions.finalize', $partial))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $partial->refresh();

    expect($partial->status)->toBe(DistributionBatchStatus::Approved)
        ->and($partial->finalization_scope->value)->toBe('partial')
        ->and($partial->items()->where('approval_status', 'pending')->exists())->toBeTrue()
        ->and($partial->approved_beneficiary_count)->toBe(1)
        ->and($partialFixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ReadyForRelease)
        ->and($partialFixture['assistances'][1]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Verified);
});

test('sequential stages block later approvers and parallel stages need every approval', function () {
    $fixture = distributionFixture();
    app(SaveDistributionApprovalPolicy::class)($fixture['head'], [
        'name' => 'Two steps',
        'department_id' => $fixture['department']->id,
        'program_id' => $fixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DistributionApprovalStrategy::Sequential->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Executive->value],
        ],
    ]);

    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();

    test()->actingAs($fixture['head'])
        ->post(route('user.distributions.decide', [$fixture['department'], $batch]), ['decision' => 'approved'])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DistributionBatchStatus::ForApproval)
        ->and($fixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ForDistributionApproval);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DistributionBatchStatus::Approved)
        ->and($fixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ReadyForRelease);

    app(SaveDistributionApprovalPolicy::class)($fixture['head'], [
        'name' => 'Parallel',
        'department_id' => $fixture['department']->id,
        'program_id' => $fixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DistributionApprovalStrategy::Parallel->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Executive->value],
        ],
    ]);

    $parallel = storeDistribution(distributionFixture());
    $parallelFixture = distributionFixture();
    app(SaveDistributionApprovalPolicy::class)($parallelFixture['head'], [
        'name' => 'Parallel specific',
        'department_id' => $parallelFixture['department']->id,
        'program_id' => $parallelFixture['program']->id,
        'approval_mode' => 'batch',
        'strategy' => DistributionApprovalStrategy::Parallel->value,
        'stages' => [
            ['name' => 'Department', 'sequence' => 1, 'role_name' => RoleName::DepartmentHead->value],
            ['name' => 'Executive', 'sequence' => 2, 'role_name' => RoleName::Executive->value],
        ],
    ]);
    $parallel = storeDistribution($parallelFixture);
    submitDistribution($parallelFixture, $parallel);

    test()->actingAs($parallelFixture['head'])
        ->post(route('user.distributions.decide', [$parallelFixture['department'], $parallel]), ['decision' => 'approved'])
        ->assertRedirect();

    expect($parallel->refresh()->status)->toBe(DistributionBatchStatus::ForApproval);

    test()->actingAs($parallelFixture['governor'])
        ->post(route('executive.distributions.decide', $parallel), ['decision' => 'approved'])
        ->assertRedirect();

    expect($parallel->refresh()->status)->toBe(DistributionBatchStatus::Approved);
});

test('a creator cannot approve their own batch', function () {
    $fixture = distributionFixture();
    $fixture['creator']->assignRole(RoleName::Executive->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['creator'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();
});

test('another department cannot open the batch', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);
    $other = Department::create(['name' => 'Other office']);
    $intruder = User::factory()->create(['department_id' => $other->id]);
    $intruder->syncRoles([RoleName::Program->value]);

    test()->actingAs($intruder)
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertForbidden();
});

test('invalid status changes are rejected', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.complete', [$fixture['department'], $batch]))
        ->assertSessionHasErrors('status');
});

test('a returned batch keeps history and starts a new revision on resubmit', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), [
            'decision' => 'returned',
            'remarks' => 'Correct the title.',
        ])
        ->assertRedirect();

    $payload = distributionPayload($fixture);
    $payload['title'] = 'Corrected release';
    $payload['lock_version'] = $batch->refresh()->lock_version;

    test()->actingAs($fixture['creator'])
        ->put(route('user.distributions.update', [$fixture['department'], $batch]), $payload)
        ->assertRedirect();

    submitDistribution($fixture, $batch->refresh());

    expect($batch->refresh()->revision)->toBe(2)
        ->and($batch->status)->toBe(DistributionBatchStatus::ForApproval)
        ->and(DistributionApprovalDecision::query()->where('decision', 'returned')->exists())->toBeTrue()
        ->and($batch->revisions()->count())->toBe(2);
});

test('the same beneficiary cannot be approved twice for one revision', function () {
    $fixture = distributionFixture(requests: 1);
    $batch = storeDistribution($fixture, 'beneficiary');
    submitDistribution($fixture, $batch);
    $item = $batch->items()->firstOrFail();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch, $item]), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch->refresh(), $item]), ['decision' => 'approved'])
        ->assertSessionHasErrors('decision');
});

test('releases record absence partial service and stock movement', function () {
    $fixture = distributionFixture(kind: 'goods', requests: 2);
    seedProgramStock($fixture['program'], $fixture['item'], 20, $fixture['officer']);
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.releases.store', [$fixture['department'], $batch]), [
            'distribution_batch_item_id' => $batch->items()->first()->id,
            'outcome' => 'served',
        ])
        ->assertSessionHasErrors('status');

    test()->actingAs($fixture['creator'])
        ->post(route('user.distributions.schedule', [$fixture['department'], $batch->refresh()]), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'venue' => 'Covered court',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.start', [$fixture['department'], $batch->refresh()]))
        ->assertRedirect();

    $items = $batch->refresh()->items()->with('lines')->orderBy('id')->get();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.releases.store', [$fixture['department'], $batch]), [
            'distribution_batch_item_id' => $items[0]->id,
            'outcome' => 'absent',
            'remarks' => 'Did not attend.',
        ])
        ->assertRedirect();

    expect($fixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)
        ->toBe(RequestSubStatusCode::Absent);

    Storage::fake('local');

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.releases.store', [$fixture['department'], $batch]), [
            'distribution_batch_item_id' => $items[1]->id,
            'distribution_batch_item_line_id' => $items[1]->lines->first()->id,
            'outcome' => 'partial',
            'actual_quantity' => 1,
            'identity_verified' => true,
            'receipt_reference' => 'ACK-1',
            'remarks' => 'Only one sack was on hand for this person.',
        ])
        ->assertSessionHasErrors('outcome');

    $key = (string) Str::uuid();
    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.store', [$fixture['department'], $batch, $items[1]]), [
            'idempotency_key' => $key,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $transaction = DistributionTransaction::query()->where('idempotency_key', $key)->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->image('live.jpg'),
            'evidence_type' => 'live_photo',
            'capture_source' => 'camera',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->image('sign.png'),
            'evidence_type' => 'signature',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.finalize', [$fixture['department'], $batch, $transaction]), [
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
        ->post(route('user.distributions.releases.store', [$fixture['department'], $batch]), [
            'distribution_batch_item_id' => $items[1]->id,
            'distribution_batch_item_line_id' => $items[1]->lines->first()->id,
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
        ->post(route('user.distributions.complete', [$fixture['department'], $batch]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $batch->refresh();
    $absent = $fixture['assistances'][0]->refresh();
    $partial = $fixture['assistances'][1]->refresh();
    $engine = app(WorkflowEngine::class);

    expect($batch->status)->toBe(DistributionBatchStatus::Completed)
        ->and($absent->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Closed)
        ->and($partial->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Closed)
        ->and($engine->currentTask($absent))->toBeNull()
        ->and($engine->currentTask($partial))->toBeNull();
});

test('approval notices are not sent twice for the same event', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);
    $batch->refresh();

    $notifier = app(DistributionNotifier::class);
    $notifier->send(collect([$fixture['governor']]), $batch, 'distribution-test-key', 'Title', 'Message');
    $notifier->send(collect([$fixture['governor']]), $batch, 'distribution-test-key', 'Title', 'Message');

    expect($fixture['governor']->notifications()->where('data->dedupe_key', 'distribution-test-key')->count())->toBe(1);
});

test('a release cannot be recorded before approval', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.releases.store', [$fixture['department'], $batch]), [
            'distribution_batch_item_id' => $batch->items()->first()->id,
            'outcome' => 'absent',
            'remarks' => 'Too early',
        ])
        ->assertSessionHasErrors('status');
});

test('decisions are kept in the audit log', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'distribution',
        'event' => 'batch_decided',
        'subject_id' => $batch->id,
    ]);

    expect(DistributionApprovalDecision::query()->where('distribution_batch_id', $batch->id)->count())->toBe(1);
});

test('unauthorized users cannot complete or cancel a batch', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);
    $stranger = User::factory()->create(['department_id' => $fixture['department']->id]);
    $stranger->syncRoles([RoleName::Beneficiary->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->actingAs($stranger)
        ->post(route('user.distributions.complete', [$fixture['department'], $batch]))
        ->assertForbidden();

    test()->actingAs($stranger)
        ->post(route('user.distributions.cancel', [$fixture['department'], $batch]), ['reason' => 'No'])
        ->assertForbidden();
});

test('bulk approval still records a decision for each beneficiary', function () {
    $fixture = distributionFixture(requests: 2);
    $batch = storeDistribution($fixture, 'beneficiary');
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.bulk-decide', $batch), [
            'item_ids' => $batch->items()->pluck('id')->all(),
            'decision' => 'approved',
        ])
        ->assertRedirect();

    expect(DistributionBatchItem::query()->where('distribution_batch_id', $batch->id)->where('approval_status', 'approved')->count())->toBe(2)
        ->and(DistributionApprovalDecision::query()->where('distribution_batch_id', $batch->id)->count())->toBe(2);
});

test('super admin can save an approval policy without becoming the approver', function () {
    $fixture = distributionFixture();
    $admin = User::factory()->create(['department_id' => $fixture['department']->id]);
    assignSuperAdminRole($admin);

    test()->actingAs($admin)
        ->post(route('admin.distribution-policies.store'), [
            'name' => 'Office policy',
            'department_id' => $fixture['department']->id,
            'strategy' => 'sequential',
            'stages' => [
                ['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Executive->value],
            ],
        ])
        ->assertRedirect();

    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($admin)
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();
});

test('a distribution separates goods, services, and cash', function () {
    $fixture = distributionFixture();
    $goods = Item::factory()->forDepartment($fixture['department'])->create(['name' => 'Rice']);
    $service = Item::factory()->forDepartment($fixture['department'])->service()->create(['name' => 'Checkup']);
    AssistanceItem::query()->create([
        'assistance_id' => $fixture['assistances'][0]->id,
        'item_id' => $goods->id,
        'quantity' => 3,
        'requested_quantity' => 3,
    ]);
    AssistanceItem::query()->create([
        'assistance_id' => $fixture['assistances'][0]->id,
        'item_id' => $service->id,
        'quantity' => 1,
        'requested_quantity' => 1,
    ]);

    $batch = storeDistribution($fixture);

    test()->actingAs($fixture['creator'])
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('batch.reconciliation.composition.cash.proposed_amount', '500.00')
            ->where('batch.reconciliation.composition.goods.lines.0.name', 'Rice')
            ->where('batch.reconciliation.composition.service.lines.0.name', 'Checkup')
            ->where('batch.reconciliation.composition.cash.lines.0.name', 'Cash aid'));

    $offer = app(DistributionBatchDetails::class)->payload($batch)['reconciliation']['composition'];

    expect($offer['goods']['lines'][0]['proposed_quantity'])->toBe('3.00')
        ->and($offer['service']['lines'][0]['proposed_quantity'])->toBe('1.00')
        ->and($offer['cash']['approved_amount'])->toBe('0.00');
});
