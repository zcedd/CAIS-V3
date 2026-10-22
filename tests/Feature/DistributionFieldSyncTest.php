<?php

use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionSyncOperationStatus;
use App\Enums\DistributionSyncOperationType;
use App\Models\Department;
use App\Models\DistributionBatchAssignment;
use App\Models\DistributionSyncOperation;
use App\Models\User;

test('the batch page lists field sync counts, officers, and refused records', function () {
    $this->travelTo('2026-10-22 10:00:00');

    $fixture = distributionFixture();
    $fixture['officer']->update([
        'firstName' => 'Nora',
        'lastName' => 'Reyes',
    ]);
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('beneficiary')->firstOrFail();
    $silent = User::factory()->create([
        'department_id' => $fixture['department']->id,
        'firstName' => 'Silent',
        'lastName' => 'Officer',
    ]);

    DistributionBatchAssignment::query()->create([
        'distribution_batch_id' => $batch->id,
        'user_id' => $silent->id,
        'assignment_role' => 'releasing_officer',
        'assigned_by' => $fixture['head']->id,
    ]);

    $slow = syncOperation($fixture['officer'], $batch->id, $item->id, DistributionSyncOperationStatus::Conflict, [
        'message' => 'There is not enough stock for rice.',
    ]);
    $exact = syncOperation($fixture['officer'], $batch->id, $item->id, DistributionSyncOperationStatus::Conflict, [
        'message' => 'The quantity is above what was approved.',
    ]);
    $ahead = syncOperation($fixture['officer'], $batch->id, $item->id, DistributionSyncOperationStatus::Conflict, [
        'message' => 'The phone clock is ahead of the server.',
    ]);
    $accepted = syncOperation($fixture['officer'], $batch->id, $item->id, DistributionSyncOperationStatus::Applied);

    stampSyncOperation($slow, '2026-10-22 09:29:00', '2026-10-22 10:00:00');
    stampSyncOperation($exact, '2026-10-22 09:28:00', '2026-10-22 09:58:00');
    stampSyncOperation($ahead, '2026-10-22 10:05:00', '2026-10-22 09:57:00');
    stampSyncOperation($accepted, '2026-10-22 09:50:00', '2026-10-22 09:56:00');

    $this->actingAs($fixture['officer'])
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/distributions/show')
            ->where('field_sync.applied_count', 1)
            ->where('field_sync.conflict_count', 3)
            ->where('field_sync.last_received_at', '2026-10-22T10:00:00+08:00')
            ->where('field_sync.lag_warning_minutes', 30)
            ->where('field_sync.unserved_beneficiaries', 1)
            ->where('field_sync.unserved_with_conflict', 1)
            ->where('field_sync.officers.0.name', 'Nora Reyes')
            ->where('field_sync.officers.0.applied_count', 1)
            ->where('field_sync.officers.0.conflict_count', 3)
            ->where('field_sync.officers.0.worst_lag_seconds', 1860)
            ->where('field_sync.officers.0.lag_warning', true)
            ->where('field_sync.officers.1.name', 'Silent Officer')
            ->where('field_sync.officers.1.applied_count', 0)
            ->where('field_sync.officers.1.conflict_count', 0)
            ->where('field_sync.officers.1.last_received_at', null)
            ->where('field_sync.officers.1.worst_lag_seconds', null)
            ->where('field_sync.officers.1.lag_warning', false)
            ->where('field_sync.conflicts.0.message', 'There is not enough stock for rice.')
            ->where('field_sync.conflicts.0.beneficiary_name', $item->beneficiary?->name)
            ->where('field_sync.conflicts.0.cais_number', $item->beneficiary?->cais_number)
            ->where('field_sync.conflicts.0.officer_name', 'Nora Reyes')
            ->where('field_sync.conflicts.0.type', 'release')
            ->where('field_sync.conflicts.0.device_captured_at', '2026-10-22T09:29:00+08:00')
            ->where('field_sync.conflicts.0.received_at', '2026-10-22T10:00:00+08:00')
            ->where('field_sync.conflicts.0.lag_seconds', 1860)
            ->where('field_sync.conflicts.0.lag_warning', true)
            ->where('field_sync.conflicts.1.lag_seconds', 1800)
            ->where('field_sync.conflicts.1.lag_warning', false)
            ->where('field_sync.conflicts.2.lag_seconds', 0)
            ->where('field_sync.conflicts.2.lag_warning', false)
            ->where('field_sync.conflicts.2.message', 'The phone clock is ahead of the server.'));
});

test('the batch page keeps the newest 25 refused records', function () {
    $this->travelTo('2026-10-22 10:00:00');

    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();

    foreach (range(1, 26) as $minutesAgo) {
        $operation = syncOperation($fixture['officer'], $batch->id, $item->id, DistributionSyncOperationStatus::Conflict, [
            'message' => "Refused {$minutesAgo}",
        ]);
        stampSyncOperation(
            $operation,
            now()->subMinutes($minutesAgo + 1)->toDateTimeString(),
            now()->subMinutes($minutesAgo)->toDateTimeString(),
        );
    }

    $this->actingAs($fixture['officer'])
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('field_sync.conflict_count', 26)
            ->has('field_sync.conflicts', 25)
            ->where('field_sync.conflicts.0.message', 'Refused 1')
            ->where('field_sync.conflicts.24.message', 'Refused 25'));
});

test('the batch page omits field sync before the distribution starts', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);

    expect($batch->status)->toBe(DistributionBatchStatus::Draft);

    $this->actingAs($fixture['creator'])
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('field_sync', null));
});

test('the batch page omits field sync when nobody is assigned and nothing has been received', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);

    $this->actingAs($fixture['officer'])
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('field_sync', null));
});

test('the batch page still shows field sync after the distribution is completed', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();

    syncOperation($fixture['officer'], $batch->id, $item->id, DistributionSyncOperationStatus::Applied);
    $batch->update(['status' => DistributionBatchStatus::Completed]);

    $this->actingAs($fixture['officer'])
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('field_sync.applied_count', 1));
});

test('the venue desk lists refused field records and leaves the list empty when every record was accepted', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();

    syncOperation($fixture['officer'], $batch->id, $item->id, DistributionSyncOperationStatus::Conflict, [
        'message' => 'There is not enough stock for rice.',
    ]);

    $this->actingAs($fixture['officer'])
        ->get(route('user.distributions.venue', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/distributions/venue')
            ->where('field_sync.conflict_count', 1)
            ->where('field_sync.conflicts.0.message', 'There is not enough stock for rice.')
            ->missing('field_sync.officers'));

    $accepted = distributionFixture();
    $acceptedBatch = openCashDistribution($accepted);
    $acceptedItem = $acceptedBatch->items()->firstOrFail();
    syncOperation($accepted['officer'], $acceptedBatch->id, $acceptedItem->id, DistributionSyncOperationStatus::Applied);

    $this->actingAs($accepted['officer'])
        ->get(route('user.distributions.venue', [$accepted['department'], $acceptedBatch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('field_sync.conflict_count', 0)
            ->where('field_sync.applied_count', 1)
            ->has('field_sync.conflicts', 0));
});

test('the venue desk omits field sync when nothing has been received', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);

    $this->actingAs($fixture['officer'])
        ->get(route('user.distributions.venue', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('field_sync', null));
});

test('a user from another department cannot view field sync for the batch', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();

    syncOperation($fixture['officer'], $batch->id, $item->id, DistributionSyncOperationStatus::Conflict, [
        'message' => 'There is not enough stock for rice.',
    ]);

    $otherDepartment = Department::create(['name' => 'Other Office '.uniqid()]);
    $outsider = User::factory()->create(['department_id' => $otherDepartment->id]);

    $this->actingAs($outsider)
        ->get(route('user.distributions.show', [$otherDepartment, $batch]))
        ->assertNotFound()
        ->assertDontSee('There is not enough stock for rice.');
});

/**
 * @param  array<string, mixed>|null  $error
 */
function syncOperation(
    User $officer,
    int $batchId,
    int $itemId,
    DistributionSyncOperationStatus $status,
    ?array $error = null,
): DistributionSyncOperation {
    return DistributionSyncOperation::factory()->create([
        'user_id' => $officer->id,
        'distribution_batch_id' => $batchId,
        'distribution_batch_item_id' => $itemId,
        'type' => DistributionSyncOperationType::Release,
        'status' => $status,
        'error' => $error,
    ]);
}

function stampSyncOperation(DistributionSyncOperation $operation, string $capturedAt, string $receivedAt): void
{
    DistributionSyncOperation::query()->whereKey($operation->id)->update([
        'device_captured_at' => $capturedAt,
        'updated_at' => $receivedAt,
    ]);
}
