<?php

use App\Actions\Distribution\StartDistributionTransaction;
use App\Actions\Mobile\IssueMobileToken;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionEvidenceType;
use App\Enums\DistributionReleaseOutcome;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\DistributionEvidence;
use App\Models\DistributionReleaseRecord;
use App\Models\DistributionTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

test('the package includes the approved roster and release status', function () {
    $this->travelTo('2026-10-20 02:00:00');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('beneficiary', 'lines')->firstOrFail();

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->getJson(route('api.mobile.distributions.package', $batch))
        ->assertOk()
        ->assertJsonPath('batch.id', $batch->id)
        ->assertJsonPath('batch.batch_code', $batch->batch_code)
        ->assertJsonPath('batch.status', 'in_progress')
        ->assertJsonPath('batch.venue', 'Covered court')
        ->assertJsonPath('items.0.id', $item->id)
        ->assertJsonPath('items.0.assistance_id', $item->assistance_id)
        ->assertJsonPath('items.0.name', $item->beneficiary?->name)
        ->assertJsonPath('items.0.cais_number', $item->beneficiary?->cais_number)
        ->assertJsonPath('items.0.release_status', 'pending')
        ->assertJsonPath('items.0.lines.0.id', $item->lines->first()?->id)
        ->assertJsonPath('items.0.lines.0.kind', 'cash')
        ->assertJsonPath('items.0.lines.0.unit', null)
        ->assertJsonPath('items.0.lines.0.approved_amount', '500.00')
        ->assertJsonPath('items.0.lines.0.released_amount', '0.00')
        ->assertJsonPath('items.0.lines.0.remaining_amount', '500.00')
        ->assertJsonPath('can.execute', true)
        ->assertJsonPath('can.issueReceipt', true)
        ->assertJsonPath('can.waive', false)
        ->assertJsonPath('server_time', '2026-10-20T02:00:00+08:00')
        ->assertJsonPath('retention_notice', (string) config('distribution.evidence_retention'))
        ->assertJsonPath('privacy_notice', 'Personal information, a live photograph, and a signature or alternative acknowledgment are collected to document the release of government assistance. The lawful basis is the agency\'s delivery of an approved assistance program and the Data Privacy Act of 2012. A camera image, timestamp, or file hash does not by itself prove identity or that the recipient was present.')
        ->assertJsonFragment([
            'id' => $fixture['officer']->id,
            'name' => $fixture['officer']->name,
        ]);
});

test('lists in-progress batches the officer can execute', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->getJson(route('api.mobile.distributions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $batch->id)
        ->assertJsonPath('data.0.status', 'in_progress')
        ->assertJsonPath('data.0.batch_code', $batch->batch_code);

    $this->app['auth']->forgetGuards();

    $this->withToken(mobileDistributionToken($fixture['creator']))
        ->getJson(route('api.mobile.distributions.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('includes a scheduled batch and omits a completed batch', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $batch->forceFill(['status' => DistributionBatchStatus::Scheduled])->save();

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->getJson(route('api.mobile.distributions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $batch->id)
        ->assertJsonPath('data.0.status', 'scheduled');

    $batch->forceFill(['status' => DistributionBatchStatus::Completed])->save();
    $this->app['auth']->forgetGuards();

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->getJson(route('api.mobile.distributions.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('returns 404 for a batch outside the officer department', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $otherDepartment = Department::create(['name' => 'Other Office '.uniqid()]);
    $outsider = User::factory()->create(['department_id' => $otherDepartment->id]);
    $outsider->syncRoles([RoleName::ReleasingOfficer->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withToken(mobileDistributionToken($outsider))
        ->getJson(route('api.mobile.distributions.package', $batch))
        ->assertNotFound();
});

test('returns 403 when the user cannot execute the batch', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);

    $this->withToken(mobileDistributionToken($fixture['creator']))
        ->getJson(route('api.mobile.distributions.package', $batch))
        ->assertForbidden();
});

test('returns 403 when the token lacks the mobile distribution ability', function () {
    $fixture = distributionFixture();
    openCashDistribution($fixture);
    $token = $fixture['officer']->createToken('Field phone', ['other'])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.mobile.distributions.index'))
        ->assertForbidden();
});

test('returns 401 when the distribution list is requested without a token', function () {
    $this->getJson(route('api.mobile.distributions.index'))->assertUnauthorized();
});

test('syncs a release with device time evidence receipt and a mobile audit source', function () {
    Storage::fake('local');
    $this->travelTo('2026-10-20 02:00:00');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('lines')->firstOrFail();
    $capturedAt = '2026-10-19T18:30:00+08:00';

    $payload = mobileReleasePayload($item, $capturedAt);

    $response = $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'applied');

    $transaction = DistributionTransaction::query()->where('idempotency_key', $payload['idempotency_key'])->firstOrFail();
    $receiptNumber = $transaction->currentReceipt?->receipt_number;

    expect($response->json('transaction_number'))->toBe($transaction->transaction_number)
        ->and($response->json('receipt_number'))->toBe($receiptNumber)
        ->and($transaction->captured_at?->toIso8601String())->toBe($capturedAt)
        ->and($transaction->finalized_at?->toIso8601String())->toBe('2026-10-20T02:00:00+08:00');

    $photo = DistributionEvidence::query()
        ->where('distribution_transaction_id', $transaction->id)
        ->where('evidence_type', DistributionEvidenceType::LivePhoto)
        ->firstOrFail();
    $signature = DistributionEvidence::query()
        ->where('distribution_transaction_id', $transaction->id)
        ->where('evidence_type', DistributionEvidenceType::Signature)
        ->firstOrFail();
    $release = DistributionReleaseRecord::query()->where('distribution_transaction_id', $transaction->id)->firstOrFail();

    expect($photo->captured_at?->toIso8601String())->toBe($capturedAt)
        ->and($photo->received_at?->toIso8601String())->toBe('2026-10-20T02:00:00+08:00')
        ->and($photo->metadata['capture_source'])->toBe('camera')
        ->and($photo->metadata['source'])->toBe('mobile')
        ->and($signature->metadata['source'])->toBe('mobile')
        ->and($release->released_at?->toIso8601String())->toBe($capturedAt);

    $this->assertDatabaseHas('distribution_sync_operations', [
        'client_operation_id' => $payload['client_operation_id'],
        'status' => 'applied',
        'type' => 'release',
        'distribution_transaction_id' => $transaction->id,
    ]);

    $activity = Activity::query()
        ->where('event', 'transaction_finalized')
        ->where('subject_id', $batch->id)
        ->latest('id')
        ->first();

    expect($activity?->properties->get('source'))->toBe('mobile');

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->get(route('api.mobile.distributions.transactions.receipt', [$batch, $transaction]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('replaying an applied release returns already applied without a second release', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('lines')->firstOrFail();
    $payload = mobileReleasePayload($item, '2026-10-19T18:30:00+08:00');

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'applied');

    $replay = mobileReleasePayload($item, '2026-10-19T18:30:00+08:00');
    $replay['idempotency_key'] = (string) Str::uuid();

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), $replay)
        ->assertOk()
        ->assertJsonPath('status', 'already_applied')
        ->assertJsonPath('transaction_number', DistributionTransaction::query()->firstOrFail()->transaction_number);

    $this->assertDatabaseCount('distribution_transactions', 1);
    $this->assertDatabaseCount('distribution_release_records', 1);
    $this->assertDatabaseCount('distribution_acknowledgment_receipts', 1);
});

test('returns 422 when the same operation id is sent for another beneficiary', function () {
    Storage::fake('local');
    $fixture = distributionFixture('cash', 2);
    $batch = openCashDistribution($fixture);
    $items = $batch->items()->with('lines')->orderBy('id')->get();
    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $items[0]]), mobileReleasePayload($items[0], '2026-10-19T18:30:00+08:00'))
        ->assertOk();

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $items[1]]), mobileReleasePayload($items[1], '2026-10-19T18:30:00+08:00'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'client_operation_id' => 'This offline operation is already tied to another beneficiary.',
        ]);

    $this->assertDatabaseCount('distribution_release_records', 1);
});

test('returns 422 when the batch is not in progress', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $batch->forceFill(['status' => DistributionBatchStatus::Scheduled])->save();
    $item = $batch->items()->with('lines')->firstOrFail();
    $payload = mobileReleasePayload($item, '2026-10-19T18:30:00+08:00');

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'status' => 'Start the distribution before releasing assistance.',
        ]);

    $this->assertDatabaseHas('distribution_sync_operations', [
        'client_operation_id' => $payload['client_operation_id'],
        'status' => 'conflict',
    ]);
    $this->assertDatabaseCount('distribution_release_records', 0);
});

test('returns 422 when the quantity exceeds the approved amount and the same operation can be retried', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('lines')->firstOrFail();
    $payload = mobileReleasePayload($item, '2026-10-19T18:30:00+08:00', [
        'lines' => [[
            'id' => $item->lines->firstOrFail()->id,
            'actual_amount' => '600',
        ]],
    ]);

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'lines' => 'The released amount exceeds the approved amount.',
        ]);

    $this->assertDatabaseHas('distribution_sync_operations', [
        'client_operation_id' => $payload['client_operation_id'],
        'status' => 'conflict',
    ]);

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), mobileReleasePayload($item, '2026-10-19T18:30:00+08:00'))
        ->assertOk()
        ->assertJsonPath('status', 'applied');

    $this->assertDatabaseCount('distribution_release_records', 1);
    $this->assertDatabaseHas('distribution_sync_operations', [
        'client_operation_id' => $payload['client_operation_id'],
        'status' => 'applied',
    ]);
});

test('returns 422 when another officer holds the draft release', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('lines')->firstOrFail();
    $other = User::factory()->create(['department_id' => $fixture['department']->id]);
    $other->syncRoles([RoleName::ReleasingOfficer->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app(StartDistributionTransaction::class)($batch, $item, $other, (string) Str::uuid());

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), mobileReleasePayload($item, '2026-10-19T18:30:00+08:00'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'transaction' => 'Another officer already has an open release for this beneficiary.',
        ]);

    $this->assertDatabaseCount('distribution_release_records', 0);
});

test('returns 422 when required release fields are missing', function () {
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_operation_id', 'idempotency_key', 'captured_at', 'received_by_type']);
});

test('syncs an absence and replays it without a second record', function () {
    $this->travelTo('2026-10-20 02:00:00');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();
    $payload = [
        'client_operation_id' => (string) Str::uuid(),
        'remarks' => 'Beneficiary was not present.',
        'captured_at' => '2026-10-19T18:30:00+08:00',
    ];

    $response = $this->withToken(mobileDistributionToken($fixture['officer']))
        ->postJson(route('api.mobile.distributions.absent.store', [$batch, $item]), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'applied');

    $record = DistributionReleaseRecord::query()->where('distribution_batch_item_id', $item->id)->firstOrFail();

    expect($response->json('distribution_release_id'))->toBe($record->id)
        ->and($record->outcome)->toBe(DistributionReleaseOutcome::Absent)
        ->and($record->remarks)->toBe('Beneficiary was not present.')
        ->and($record->released_at?->toIso8601String())->toBe('2026-10-19T18:30:00+08:00');

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->postJson(route('api.mobile.distributions.absent.store', [$batch, $item]), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'already_applied')
        ->assertJsonPath('distribution_release_id', $record->id);

    $this->assertDatabaseCount('distribution_release_records', 1);
});

test('a web finalize without captured_at records the server time', function () {
    Storage::fake('local');
    $this->travelTo('2026-10-20 02:00:00');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);
    $photo = $transaction->evidence()->where('evidence_type', 'live_photo')->firstOrFail();

    expect($transaction->captured_at?->toIso8601String())->toBe('2026-10-20T02:00:00+08:00')
        ->and($transaction->finalized_at?->toIso8601String())->toBe('2026-10-20T02:00:00+08:00')
        ->and(array_key_exists('source', $photo->metadata ?? []))->toBeFalse();
});

test('returns 404 when the receipt belongs to another department', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('lines')->firstOrFail();

    $this->withToken(mobileDistributionToken($fixture['officer']))
        ->post(route('api.mobile.distributions.releases.store', [$batch, $item]), mobileReleasePayload($item, '2026-10-19T18:30:00+08:00'))
        ->assertOk();

    $transaction = DistributionTransaction::query()->firstOrFail();
    $otherDepartment = Department::create(['name' => 'Receipt Office '.uniqid()]);
    $outsider = User::factory()->create(['department_id' => $otherDepartment->id]);
    $outsider->syncRoles([RoleName::ReleasingOfficer->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->app['auth']->forgetGuards();

    $this->withToken(mobileDistributionToken($outsider))
        ->get(route('api.mobile.distributions.transactions.receipt', [$batch, $transaction]))
        ->assertNotFound();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mobileReleasePayload(mixed $item, string $capturedAt, array $overrides = []): array
{
    $lineId = $item->lines->firstOrFail()->id;

    return array_replace_recursive([
        'client_operation_id' => '11111111-1111-4111-8111-111111111111',
        'idempotency_key' => '22222222-2222-4222-8222-222222222222',
        'captured_at' => $capturedAt,
        'received_by_type' => 'beneficiary',
        'received_by_name' => 'Recipient Example',
        'verification_method' => 'control_number',
        'identity_verified' => true,
        'acknowledgment_mode' => 'signature',
        'privacy_acknowledged' => true,
        'lines' => [[
            'id' => $lineId,
            'actual_amount' => '500',
        ]],
        'live_photo' => UploadedFile::fake()->image('live.jpg'),
        'signature' => UploadedFile::fake()->image('sign.png'),
    ], $overrides);
}

function mobileDistributionToken(User $user): string
{
    return $user->createToken('Field phone', [IssueMobileToken::ABILITY])->plainTextToken;
}
