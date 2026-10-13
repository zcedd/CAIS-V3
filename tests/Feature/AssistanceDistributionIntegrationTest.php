<?php

use App\Actions\Distribution\AssertDeliveredRequiresDistribution;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionItemApprovalStatus;
use App\Enums\RequestSubStatusCode;
use App\Models\DistributionApprovalTask;
use App\Models\DistributionBatch;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('an assistance profile opens individual distribution on the release form', function () {
    $fixture = distributionFixture();
    $assistance = $fixture['assistances'][0];
    $user = User::factory()->create(['department_id' => $fixture['department']->id]);

    $response = $this->actingAs($user)
        ->post(route('user.assistances.distribution.store', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $assistance,
        ]), ['mode' => 'individual'])
        ->assertSessionHasNoErrors();

    $batch = DistributionBatch::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('user.distributions.venue', [
        'department' => $fixture['department'],
        'distributionBatch' => $batch,
        'item' => $batch->items()->value('id'),
    ]));

    expect($batch->status)->toBe(DistributionBatchStatus::InProgress)
        ->and($batch->approval_mode->value)->toBe('beneficiary')
        ->and($batch->items)->toHaveCount(1);

    $this->actingAs($user)
        ->get(route('user.distributions.venue', [
            'department' => $fixture['department'],
            'distributionBatch' => $batch,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/distributions/venue')
            ->where('release_only', true)
            ->where('selected.assistance_id', $assistance->id));
});

test('an individual distribution opens for release without executive approval', function () {
    Notification::fake();
    $fixture = distributionFixture();

    $response = $this->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture, 'beneficiary'));

    $batch = DistributionBatch::query()->latest('id')->firstOrFail();
    $item = $batch->items()->with('lines')->firstOrFail();
    $line = $item->lines->firstOrFail();

    $response->assertRedirect(route('user.distributions.venue', [
        'department' => $fixture['department'],
        'distributionBatch' => $batch,
    ]));

    $this->actingAs($fixture['creator'])
        ->get(route('user.distributions.venue', [
            'department' => $fixture['department'],
            'distributionBatch' => $batch,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_only', true)
            ->where('selected.assistance_id', $fixture['assistances'][0]->id));

    expect($batch->status)->toBe(DistributionBatchStatus::InProgress)
        ->and($batch->approved_at)->not->toBeNull()
        ->and($batch->opened_at)->not->toBeNull()
        ->and($item->approval_status)->toBe(DistributionItemApprovalStatus::Approved)
        ->and($line->approved_quantity)->toBe($line->proposed_quantity)
        ->and($line->approved_amount)->toBe($line->proposed_amount)
        ->and(DistributionApprovalTask::query()->where('distribution_batch_id', $batch->id)->count())->toBe(0)
        ->and($fixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ReleaseInProgress);

    Notification::assertNothingSent();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'distribution',
        'event' => 'individual_opened',
        'subject_id' => $batch->id,
    ]);
});

test('a batch distribution stays draft and does not open the venue', function () {
    $fixture = distributionFixture();

    $response = $this->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture));

    $batch = DistributionBatch::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('user.distributions.show', [
        'department' => $fixture['department'],
        'distributionBatch' => $batch,
    ]));

    expect($batch->status)->toBe(DistributionBatchStatus::Draft)
        ->and($batch->items()->firstOrFail()->approval_status)->toBe(DistributionItemApprovalStatus::Pending)
        ->and(DistributionApprovalTask::query()->count())->toBe(0);
});

test('beneficiary approval for several requests stays a draft batch', function () {
    $fixture = distributionFixture(requests: 2);

    $response = $this->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture, 'beneficiary'));

    $batch = DistributionBatch::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('user.distributions.show', [
        'department' => $fixture['department'],
        'distributionBatch' => $batch,
    ]));

    expect($batch->status)->toBe(DistributionBatchStatus::Draft)
        ->and($batch->items)->toHaveCount(2);
});

test('an unapproved assistance request cannot be stored as an individual distribution', function () {
    $fixture = distributionFixture();
    $fixture['assistances'][0]->update([
        'current_request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Denied),
    ]);

    $this->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture, 'beneficiary'))
        ->assertSessionHasErrors([
            'assistance_ids' => 'Only verified, approved, or ready-for-release requests can be added.',
        ]);

    expect(DistributionBatch::query()->count())->toBe(0);
});

test('a releasing officer cannot store an individual distribution', function () {
    $fixture = distributionFixture();

    $this->actingAs($fixture['officer'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture, 'beneficiary'))
        ->assertForbidden();

    expect(DistributionBatch::query()->count())->toBe(0);
});

test('batch distribution from an assistance profile uses the same venue form', function () {
    $fixture = distributionFixture();
    $user = User::factory()->create(['department_id' => $fixture['department']->id]);

    $this->actingAs($user)
        ->post(route('user.assistances.distribution.store', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
        ]), ['mode' => 'batch'])
        ->assertRedirect(route('user.distributions.create', [
            'department' => $fixture['department'],
            'program_id' => $fixture['program']->id,
            'assistance_id' => $fixture['assistances'][0]->id,
            'mode' => 'batch',
        ]));
});

test('an assistance request that already has a distribution is not added to a second batch', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);
    $user = User::factory()->create(['department_id' => $fixture['department']->id]);

    $this->actingAs($user)
        ->post(route('user.assistances.distribution.store', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
        ]), ['mode' => 'individual'])
        ->assertRedirect(route('user.distributions.edit', [
            'department' => $fixture['department'],
            'distributionBatch' => $batch,
        ]));

    expect(DistributionBatch::query()->count())->toBe(1);
});

test('a releasing officer cannot start a distribution from the assistance profile', function () {
    $fixture = distributionFixture();

    $this->actingAs($fixture['officer'])
        ->post(route('user.assistances.distribution.store', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
        ]), ['mode' => 'individual'])
        ->assertForbidden();
});

test('an unverified request cannot start a distribution', function () {
    $fixture = distributionFixture();
    $fixture['assistances'][0]->update([
        'current_request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Denied),
    ]);
    $user = User::factory()->create(['department_id' => $fixture['department']->id]);

    $this->actingAs($user)
        ->from(route('user.assistances.show', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
        ]))
        ->post(route('user.assistances.distribution.store', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
        ]), ['mode' => 'individual'])
        ->assertSessionHasErrors([
            'assistance' => 'Only a verified, approved, or ready-for-release request can start a distribution.',
        ]);
});

test('assistance cannot be marked delivered without a distribution transaction', function () {
    $fixture = distributionFixture();
    $officer = $fixture['officer'];

    $this->actingAs($officer)
        ->from(route('user.assistances.show', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
        ]))
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
        ]), [
            'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Delivered),
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [],
        ])
        ->assertSessionHasErrors([
            'request_sub_status_id' => AssertDeliveredRequiresDistribution::MESSAGE,
        ]);

    expect($fixture['assistances'][0]->refresh()->was_delivered)->toBeFalse();
});
