<?php

use App\Enums\DistributionReceiptStatus;
use App\Enums\DistributionTransactionStatus;
use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Models\DistributionAcknowledgmentReceipt;
use App\Models\DistributionBatch;
use App\Models\DistributionEvidence;
use App\Models\DistributionReleaseRecord;
use App\Models\DistributionTransaction;
use App\Models\User;
use App\Services\Distribution\DistributionEvidenceService;
use App\Services\Distribution\DistributionReceiptService;
use App\Services\Distribution\DistributionReconciliation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function openCashDistribution(array $fixture): DistributionBatch
{
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['creator'])
        ->post(route('user.distributions.schedule', [$fixture['department'], $batch->refresh()]), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'venue' => 'Covered court',
            'venue_details' => 'Main hall',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.start', [$fixture['department'], $batch->refresh()]))
        ->assertRedirect();

    return $batch->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function finalizeVenueRelease(array $fixture, DistributionBatch $batch, int $itemId, array $overrides = []): DistributionTransaction
{
    $key = (string) Str::uuid();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.store', [$fixture['department'], $batch, $itemId]), [
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
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    if (($overrides['acknowledgment_mode'] ?? 'signature') === 'signature') {
        test()->actingAs($fixture['officer'])
            ->post(route('user.distributions.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
                'file' => UploadedFile::fake()->image('sign.png'),
                'evidence_type' => 'signature',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    $line = $batch->items()->findOrFail($itemId)->lines()->firstOrFail();
    $payload = array_merge([
        'received_by_type' => 'beneficiary',
        'received_by_name' => 'Recipient Example',
        'verification_method' => 'control_number',
        'identity_verified' => true,
        'acknowledgment_mode' => 'signature',
        'privacy_acknowledged' => true,
        'lines' => [[
            'id' => $line->id,
            'actual_amount' => '500',
        ]],
    ], $overrides);

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.finalize', [$fixture['department'], $batch, $transaction]), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return $transaction->refresh();
}

test('a finalized distribution receipt uses the assistance acknowledgment layout', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);
    $receipt = $transaction->currentReceipt;

    $this->actingAs($fixture['officer'])
        ->get(route('user.distributions.transactions.receipt', [
            'department' => $fixture['department'],
            'distributionBatch' => $batch,
            'transaction' => $transaction,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/assistances/receipt')
            ->where('receipt.assistance_id', $fixture['assistances'][0]->id)
            ->where('receipt.distribution.receipt_number', $receipt?->receipt_number)
            ->where('receipt.distribution.transaction_number', $transaction->transaction_number)
            ->where('receipt.distribution.batch_code', $batch->batch_code)
            ->where('receipt.distribution.venue', 'Covered court')
            ->where('receipt.beneficiary_name', $batch->items()->firstOrFail()->beneficiary?->name)
            ->where('receipt.distribution.signature_url', route('user.assistances.receipt.evidence', [
                'department' => $fixture['department'],
                'program' => $fixture['program'],
                'assistance' => $fixture['assistances'][0],
                'evidence' => $transaction->evidence()->where('evidence_type', 'signature')->firstOrFail(),
            ]))
            ->where('receipt.distribution.photo_url', route('user.assistances.receipt.evidence', [
                'department' => $fixture['department'],
                'program' => $fixture['program'],
                'assistance' => $fixture['assistances'][0],
                'evidence' => $transaction->evidence()->where('evidence_type', 'live_photo')->firstOrFail(),
            ])));

    $this->actingAs($fixture['officer'])
        ->get(route('user.assistances.receipt', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('receipt.distribution.receipt_number', $receipt?->receipt_number)
            ->where('receipt.distribution.transaction_number', $transaction->transaction_number)
            ->where('receipt.distribution.signature_url', route('user.assistances.receipt.evidence', [
                'department' => $fixture['department'],
                'program' => $fixture['program'],
                'assistance' => $fixture['assistances'][0],
                'evidence' => $transaction->evidence()->where('evidence_type', 'signature')->firstOrFail(),
            ]))
            ->where('receipt.distribution.photo_url', route('user.assistances.receipt.evidence', [
                'department' => $fixture['department'],
                'program' => $fixture['program'],
                'assistance' => $fixture['assistances'][0],
                'evidence' => $transaction->evidence()->where('evidence_type', 'live_photo')->firstOrFail(),
            ])));
});

test('an acknowledgment receipt includes the signature and photo proof of delivery', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);
    $signature = $transaction->evidence()->where('evidence_type', 'signature')->firstOrFail();
    $photo = $transaction->evidence()->where('evidence_type', 'live_photo')->firstOrFail();
    $evidenceRoute = [
        'department' => $fixture['department'],
        'program' => $fixture['program'],
        'assistance' => $fixture['assistances'][0],
    ];

    $this->actingAs($fixture['officer'])
        ->get(route('user.assistances.receipt.evidence', [...$evidenceRoute, 'evidence' => $signature]))
        ->assertOk()
        ->assertHeader('content-type', $signature->mime_type);

    $this->actingAs($fixture['officer'])
        ->get(route('user.assistances.receipt.evidence', [...$evidenceRoute, 'evidence' => $photo]))
        ->assertOk()
        ->assertHeader('content-type', $photo->mime_type);

    $receipt = $transaction->currentReceipt()->firstOrFail();
    $html = app(DistributionReceiptService::class)->html($receipt);

    expect($html)
        ->toContain('BENEFICIARY')
        ->toContain('Proof of delivery')
        ->toContain('data:image/');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'distribution',
        'event' => 'evidence_viewed',
    ]);
});

test('acknowledgment receipt evidence from another release is not found', function () {
    Storage::fake('local');
    $fixture = distributionFixture(requests: 2);
    $batch = openCashDistribution($fixture);
    $items = $batch->items()->orderBy('id')->get();
    finalizeVenueRelease($fixture, $batch, $items[0]->id);
    $secondLine = $items[1]->lines()->firstOrFail();
    $second = finalizeVenueRelease($fixture, $batch, $items[1]->id, [
        'lines' => [[
            'id' => $secondLine->id,
            'actual_amount' => (string) $secondLine->approved_amount,
        ]],
    ]);
    $otherPhoto = $second->evidence()->where('evidence_type', 'live_photo')->firstOrFail();

    $this->actingAs($fixture['officer'])
        ->get(route('user.assistances.receipt.evidence', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
            'evidence' => $otherPhoto,
        ]))
        ->assertNotFound();
});

test('users who cannot view the assistance cannot open receipt evidence', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);
    $signature = $transaction->evidence()->where('evidence_type', 'signature')->firstOrFail();
    $stranger = User::factory()->create(['department_id' => $fixture['department']->id]);
    $stranger->syncRoles([RoleName::Beneficiary->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    auth()->logout();

    $this->get(route('user.assistances.receipt.evidence', [
        'department' => $fixture['department'],
        'program' => $fixture['program'],
        'assistance' => $fixture['assistances'][0],
        'evidence' => $signature,
    ]))->assertRedirect(route('login'));

    test()->actingAs($stranger)
        ->get(route('user.assistances.receipt.evidence', [
            'department' => $fixture['department'],
            'program' => $fixture['program'],
            'assistance' => $fixture['assistances'][0],
            'evidence' => $signature,
        ]))
        ->assertForbidden();
});

test('an officer can finalize a release with live photo evidence and a signature', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();

    $transaction = finalizeVenueRelease($fixture, $batch, $item->id);

    expect($transaction->status)->toBe(DistributionTransactionStatus::Finalized)
        ->and($transaction->releases)->toHaveCount(1)
        ->and($transaction->currentReceipt)->not->toBeNull()
        ->and($transaction->currentReceipt->receipt_number)->toStartWith('AR-')
        ->and($fixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Delivered);

    $evidence = DistributionEvidence::query()->where('evidence_type', 'live_photo')->firstOrFail();
    expect(app(DistributionEvidenceService::class)->assertHash($evidence))->toBeTrue()
        ->and(Storage::disk('local')->exists($evidence->path))->toBeTrue()
        ->and(Storage::disk('public')->exists($evidence->path))->toBeFalse();

    test()->actingAs($fixture['officer'])
        ->get(route('user.distributions.transactions.evidence.show', [$fixture['department'], $batch, $transaction, $evidence]))
        ->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'distribution',
        'event' => 'evidence_viewed',
    ]);

    test()->actingAs($fixture['officer'])
        ->get(route('user.distributions.venue', [
            'department' => $fixture['department'],
            'distributionBatch' => $batch,
            'item' => $item->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('user/distributions/venue'));
});

test('finalizing the same transaction again does not release the assistance twice', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();
    $transaction = finalizeVenueRelease($fixture, $batch, $item->id);

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.finalize', [$fixture['department'], $batch, $transaction]), [
            'received_by_type' => 'beneficiary',
            'received_by_name' => 'Recipient Example',
            'verification_method' => 'control_number',
            'identity_verified' => true,
            'acknowledgment_mode' => 'signature',
            'privacy_acknowledged' => true,
            'lines' => [[
                'id' => $item->lines()->firstOrFail()->id,
                'actual_amount' => '500',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(DistributionReleaseRecord::query()->count())->toBe(1)
        ->and(DistributionAcknowledgmentReceipt::query()->count())->toBe(1);
});

test('a release without live photo evidence stays a draft', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect();

    $transaction = DistributionTransaction::query()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.finalize', [$fixture['department'], $batch, $transaction]), [
            'received_by_type' => 'beneficiary',
            'received_by_name' => 'Recipient Example',
            'verification_method' => 'control_number',
            'identity_verified' => true,
            'acknowledgment_mode' => 'alternative',
            'alternative_reason' => 'Recipient cannot sign.',
            'privacy_acknowledged' => true,
            'lines' => [[
                'id' => $item->lines()->firstOrFail()->id,
                'actual_amount' => '500',
            ]],
        ])
        ->assertSessionHasErrors('live_photo');

    expect($transaction->refresh()->status)->toBe(DistributionTransactionStatus::Draft)
        ->and(DistributionReleaseRecord::query()->count())->toBe(0);
});

test('an officer cannot waive the live photo', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();
    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => (string) Str::uuid(),
        ]);
    $transaction = DistributionTransaction::query()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.finalize', [$fixture['department'], $batch, $transaction]), [
            'received_by_type' => 'beneficiary',
            'received_by_name' => 'Recipient Example',
            'verification_method' => 'control_number',
            'identity_verified' => true,
            'acknowledgment_mode' => 'alternative',
            'alternative_reason' => 'Thumbprint on the logbook.',
            'privacy_acknowledged' => true,
            'camera_waiver_reason' => 'Camera battery failed.',
            'lines' => [[
                'id' => $item->lines()->firstOrFail()->id,
                'actual_amount' => '500',
            ]],
        ])
        ->assertSessionHasErrors('camera_waiver_reason');
});

test('unsupported and oversized evidence files are rejected', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();
    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => (string) Str::uuid(),
        ]);
    $transaction = DistributionTransaction::query()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
            'evidence_type' => 'live_photo',
            'capture_source' => 'camera',
        ])
        ->assertSessionHasErrors('file');

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->image('huge.jpg')->size(6000),
            'evidence_type' => 'live_photo',
            'capture_source' => 'camera',
        ])
        ->assertSessionHasErrors('file');

    expect(DistributionEvidence::query()->count())->toBe(0);
});

test('a gallery upload is not accepted as a live photo', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->firstOrFail();
    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => (string) Str::uuid(),
        ]);
    $transaction = DistributionTransaction::query()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->image('gallery.jpg'),
            'evidence_type' => 'live_photo',
            'capture_source' => 'gallery',
        ])
        ->assertSessionHasErrors('capture_source');
});

test('an excessive release is rejected and a partial release keeps the balance', function () {
    Storage::fake('local');
    $fixture = distributionFixture(kind: 'goods');
    seedProgramStock($fixture['program'], $fixture['item'], 20, $fixture['officer']);
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('lines')->firstOrFail();

    finalizeVenueRelease($fixture, $batch, $item->id, [
        'notes' => 'Only part of the allocation was on hand.',
        'lines' => [[
            'id' => $item->lines->first()->id,
            'actual_quantity' => '1',
        ]],
    ]);

    Model::preventLazyLoading(true);

    try {
        $summary = app(DistributionReconciliation::class)->summarize($batch->refresh());
    } finally {
        Model::preventLazyLoading(false);
    }

    expect($summary['partial_beneficiaries'])->toBe(1)
        ->and($summary['served_beneficiaries'])->toBe(0);

    expect($fixture['assistances'][0]->refresh()->currentRequestSubStatus?->code)
        ->toBe(RequestSubStatusCode::PartiallyDelivered);

    $secondKey = (string) Str::uuid();
    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => $secondKey,
        ])
        ->assertRedirect();
    $second = DistributionTransaction::query()->where('idempotency_key', $secondKey)->firstOrFail();
    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.evidence.store', [$fixture['department'], $batch, $second]), [
            'file' => UploadedFile::fake()->image('live.jpg'),
            'evidence_type' => 'live_photo',
            'capture_source' => 'camera',
        ]);
    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.evidence.store', [$fixture['department'], $batch, $second]), [
            'file' => UploadedFile::fake()->image('sign.png'),
            'evidence_type' => 'signature',
        ]);

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.finalize', [$fixture['department'], $batch, $second]), [
            'received_by_type' => 'beneficiary',
            'received_by_name' => 'Recipient Example',
            'verification_method' => 'control_number',
            'identity_verified' => true,
            'acknowledgment_mode' => 'signature',
            'privacy_acknowledged' => true,
            'lines' => [[
                'id' => $item->lines->first()->id,
                'actual_quantity' => '4',
            ]],
        ])
        ->assertSessionHasErrors('lines');

    expect(DistributionReleaseRecord::query()->count())->toBe(1);
});

test('unauthorized users cannot view evidence', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);
    $evidence = DistributionEvidence::query()->firstOrFail();
    $stranger = User::factory()->create(['department_id' => $fixture['department']->id]);
    $stranger->syncRoles([RoleName::Beneficiary->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->actingAs($stranger)
        ->get(route('user.distributions.transactions.evidence.show', [$fixture['department'], $batch, $transaction, $evidence]))
        ->assertForbidden();
});

test('a receipt can be reprinted and superseded without another release', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);
    $receipt = $transaction->currentReceipt;
    expect($receipt)->not->toBeNull();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.receipts.reprint', [$fixture['department'], $batch, $receipt]))
        ->assertRedirect();

    expect($receipt->refresh()->reprint_count)->toBe(1)
        ->and(DistributionReleaseRecord::query()->count())->toBe(1);

    test()->actingAs($fixture['head'])
        ->post(route('user.distributions.receipts.supersede', [$fixture['department'], $batch, $receipt]), [
            'reason' => 'Correct the printed recipient name.',
            'recipient_name' => 'Corrected Recipient',
        ])
        ->assertRedirect();

    $receipt->refresh();
    $replacement = DistributionAcknowledgmentReceipt::query()->where('status', DistributionReceiptStatus::Issued)->firstOrFail();

    expect($receipt->status)->toBe(DistributionReceiptStatus::Superseded)
        ->and($replacement->version)->toBe(2)
        ->and($replacement->snapshot['recipient_name'])->toBe('Corrected Recipient')
        ->and(DistributionReleaseRecord::query()->count())->toBe(1);

    test()->actingAs($fixture['officer'])
        ->get(route('user.distributions.transactions.receipt.pdf', [$fixture['department'], $batch, $transaction]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('public receipt verification hides personal evidence', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $item = $batch->items()->with('beneficiary')->firstOrFail();
    $transaction = finalizeVenueRelease($fixture, $batch, $item->id);
    $receipt = $transaction->currentReceipt;

    $this->get(route('public.distribution-receipts.verify', ['token' => $receipt->verification_token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/distributions/verify')
            ->where('receipt.receipt_number', $receipt->receipt_number)
            ->missing('receipt.snapshot')
            ->missing('receipt.beneficiary_reference')
            ->missing('receipt.signature_url')
            ->missing('receipt.photo_url'))
        ->assertDontSee($item->beneficiary->cais_number);
});

test('voiding a release requires authority and keeps the audit record', function () {
    Storage::fake('local');
    $fixture = distributionFixture();
    $batch = openCashDistribution($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.void', [$fixture['department'], $batch, $transaction]), [
            'reason' => 'Wrong person.',
        ])
        ->assertForbidden();

    test()->actingAs($fixture['head'])
        ->post(route('user.distributions.transactions.void', [$fixture['department'], $batch, $transaction]), [
            'reason' => 'Wrong person was recorded.',
        ])
        ->assertRedirect();

    expect($transaction->refresh()->status)->toBe(DistributionTransactionStatus::Voided)
        ->and($transaction->currentReceipt->status)->toBe(DistributionReceiptStatus::Voided)
        ->and(DistributionReleaseRecord::query()->count())->toBe(1);

    $this->assertDatabaseHas('activity_log', [
        'event' => 'transaction_voided',
        'subject_id' => $batch->id,
    ]);
});

test('a cancelled batch cannot start a venue release', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);

    test()->actingAs($fixture['creator'])
        ->post(route('user.distributions.cancel', [$fixture['department'], $batch]), [
            'reason' => 'Schedule was withdrawn.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.transactions.store', [$fixture['department'], $batch->refresh(), $batch->items()->firstOrFail()]), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('status');
});

test('reconciliation separates excluded beneficiaries from releases', function () {
    Storage::fake('local');
    $fixture = distributionFixture(requests: 2);
    $batch = storeDistribution($fixture, 'beneficiary');
    submitDistribution($fixture, $batch);
    $items = $batch->items()->orderBy('id')->get();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch, $items[0]]), [
            'decision' => 'excluded',
            'remarks' => 'Did not meet the program rule.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.items.decide', [$batch->refresh(), $items[1]]), [
            'decision' => 'approved',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.distributions.finalize', $batch->refresh()))
        ->assertRedirect();

    test()->actingAs($fixture['creator'])
        ->post(route('user.distributions.schedule', [$fixture['department'], $batch->refresh()]), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'venue' => 'Covered court',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.distributions.start', [$fixture['department'], $batch->refresh()]))
        ->assertRedirect();

    finalizeVenueRelease($fixture, $batch->refresh(), $items[1]->id, [
        'lines' => [[
            'id' => $items[1]->lines()->firstOrFail()->id,
            'actual_amount' => '600',
        ]],
    ]);

    $summary = app(DistributionReconciliation::class)->summarize($batch->refresh());

    expect($summary['excluded_beneficiaries'])->toBe(1)
        ->and($summary['served_beneficiaries'])->toBe(1)
        ->and($summary['approved_cash'])->toBe('600.00')
        ->and($summary['actual_cash'])->toBe('600.00');
});
