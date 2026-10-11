<?php

use App\Enums\DisbursementReceiptStatus;
use App\Enums\DisbursementTransactionStatus;
use App\Enums\RoleName;
use App\Models\DisbursementAcknowledgmentReceipt;
use App\Models\DisbursementBatch;
use App\Models\DisbursementEvidence;
use App\Models\DisbursementReleaseRecord;
use App\Models\DisbursementTransaction;
use App\Models\User;
use App\Services\Disbursement\DisbursementEvidenceService;
use App\Services\Disbursement\DisbursementReconciliation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function openCashDisbursement(array $fixture): DisbursementBatch
{
    $batch = storeDisbursement($fixture);
    submitDisbursement($fixture, $batch);

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.schedule', [$fixture['department'], $batch->refresh()]), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'venue' => 'Covered court',
            'venue_details' => 'Main hall',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.start', [$fixture['department'], $batch->refresh()]))
        ->assertRedirect();

    return $batch->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function finalizeVenueRelease(array $fixture, DisbursementBatch $batch, int $itemId, array $overrides = []): DisbursementTransaction
{
    $key = (string) Str::uuid();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.store', [$fixture['department'], $batch, $itemId]), [
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
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    if (($overrides['acknowledgment_mode'] ?? 'signature') === 'signature') {
        test()->actingAs($fixture['officer'])
            ->post(route('user.disbursements.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
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
        ->post(route('user.disbursements.transactions.finalize', [$fixture['department'], $batch, $transaction]), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return $transaction->refresh();
}

test('an officer can finalize a release with live photo evidence and a signature', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $item = $batch->items()->firstOrFail();

    $transaction = finalizeVenueRelease($fixture, $batch, $item->id);

    expect($transaction->status)->toBe(DisbursementTransactionStatus::Finalized)
        ->and($transaction->releases)->toHaveCount(1)
        ->and($transaction->currentReceipt)->not->toBeNull()
        ->and($transaction->currentReceipt->receipt_number)->toStartWith('AR-');

    $evidence = DisbursementEvidence::query()->where('evidence_type', 'live_photo')->firstOrFail();
    expect(app(DisbursementEvidenceService::class)->assertHash($evidence))->toBeTrue()
        ->and(Storage::disk('local')->exists($evidence->path))->toBeTrue()
        ->and(Storage::disk('public')->exists($evidence->path))->toBeFalse();

    test()->actingAs($fixture['officer'])
        ->get(route('user.disbursements.transactions.evidence.show', [$fixture['department'], $batch, $transaction, $evidence]))
        ->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'disbursement',
        'event' => 'evidence_viewed',
    ]);

    test()->actingAs($fixture['officer'])
        ->get(route('user.disbursements.venue', [
            'department' => $fixture['department'],
            'disbursementBatch' => $batch,
            'item' => $item->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('user/disbursements/venue'));
});

test('finalizing the same transaction again does not release the assistance twice', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $item = $batch->items()->firstOrFail();
    $transaction = finalizeVenueRelease($fixture, $batch, $item->id);

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.finalize', [$fixture['department'], $batch, $transaction]), [
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

    expect(DisbursementReleaseRecord::query()->count())->toBe(1)
        ->and(DisbursementAcknowledgmentReceipt::query()->count())->toBe(1);
});

test('a release without live photo evidence stays a draft', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $item = $batch->items()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect();

    $transaction = DisbursementTransaction::query()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.finalize', [$fixture['department'], $batch, $transaction]), [
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

    expect($transaction->refresh()->status)->toBe(DisbursementTransactionStatus::Draft)
        ->and(DisbursementReleaseRecord::query()->count())->toBe(0);
});

test('an officer cannot waive the live photo', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $item = $batch->items()->firstOrFail();
    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => (string) Str::uuid(),
        ]);
    $transaction = DisbursementTransaction::query()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.finalize', [$fixture['department'], $batch, $transaction]), [
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
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $item = $batch->items()->firstOrFail();
    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => (string) Str::uuid(),
        ]);
    $transaction = DisbursementTransaction::query()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
            'evidence_type' => 'live_photo',
            'capture_source' => 'camera',
        ])
        ->assertSessionHasErrors('file');

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->image('huge.jpg')->size(6000),
            'evidence_type' => 'live_photo',
            'capture_source' => 'camera',
        ])
        ->assertSessionHasErrors('file');

    expect(DisbursementEvidence::query()->count())->toBe(0);
});

test('a gallery upload is not accepted as a live photo', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $item = $batch->items()->firstOrFail();
    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => (string) Str::uuid(),
        ]);
    $transaction = DisbursementTransaction::query()->firstOrFail();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.evidence.store', [$fixture['department'], $batch, $transaction]), [
            'file' => UploadedFile::fake()->image('gallery.jpg'),
            'evidence_type' => 'live_photo',
            'capture_source' => 'gallery',
        ])
        ->assertSessionHasErrors('capture_source');
});

test('an excessive release is rejected and a partial release keeps the balance', function () {
    Storage::fake('local');
    $fixture = disbursementFixture(kind: 'goods');
    seedProgramStock($fixture['program'], $fixture['item'], 20, $fixture['officer']);
    $batch = openCashDisbursement($fixture);
    $item = $batch->items()->with('lines')->firstOrFail();

    finalizeVenueRelease($fixture, $batch, $item->id, [
        'notes' => 'Only part of the allocation was on hand.',
        'lines' => [[
            'id' => $item->lines->first()->id,
            'actual_quantity' => '1',
        ]],
    ]);

    $summary = app(DisbursementReconciliation::class)->summarize($batch->refresh());
    expect($summary['partial_beneficiaries'])->toBe(1)
        ->and($summary['served_beneficiaries'])->toBe(0);

    $secondKey = (string) Str::uuid();
    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.store', [$fixture['department'], $batch, $item]), [
            'idempotency_key' => $secondKey,
        ])
        ->assertRedirect();
    $second = DisbursementTransaction::query()->where('idempotency_key', $secondKey)->firstOrFail();
    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.evidence.store', [$fixture['department'], $batch, $second]), [
            'file' => UploadedFile::fake()->image('live.jpg'),
            'evidence_type' => 'live_photo',
            'capture_source' => 'camera',
        ]);
    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.evidence.store', [$fixture['department'], $batch, $second]), [
            'file' => UploadedFile::fake()->image('sign.png'),
            'evidence_type' => 'signature',
        ]);

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.finalize', [$fixture['department'], $batch, $second]), [
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

    expect(DisbursementReleaseRecord::query()->count())->toBe(1);
});

test('unauthorized users cannot view evidence', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);
    $evidence = DisbursementEvidence::query()->firstOrFail();
    $stranger = User::factory()->create(['department_id' => $fixture['department']->id]);
    $stranger->syncRoles([RoleName::Beneficiary->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->actingAs($stranger)
        ->get(route('user.disbursements.transactions.evidence.show', [$fixture['department'], $batch, $transaction, $evidence]))
        ->assertForbidden();
});

test('a receipt can be reprinted and superseded without another release', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);
    $receipt = $transaction->currentReceipt;
    expect($receipt)->not->toBeNull();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.receipts.reprint', [$fixture['department'], $batch, $receipt]))
        ->assertRedirect();

    expect($receipt->refresh()->reprint_count)->toBe(1)
        ->and(DisbursementReleaseRecord::query()->count())->toBe(1);

    test()->actingAs($fixture['head'])
        ->post(route('user.disbursements.receipts.supersede', [$fixture['department'], $batch, $receipt]), [
            'reason' => 'Correct the printed recipient name.',
            'recipient_name' => 'Corrected Recipient',
        ])
        ->assertRedirect();

    $receipt->refresh();
    $replacement = DisbursementAcknowledgmentReceipt::query()->where('status', DisbursementReceiptStatus::Issued)->firstOrFail();

    expect($receipt->status)->toBe(DisbursementReceiptStatus::Superseded)
        ->and($replacement->version)->toBe(2)
        ->and($replacement->snapshot['recipient_name'])->toBe('Corrected Recipient')
        ->and(DisbursementReleaseRecord::query()->count())->toBe(1);

    test()->actingAs($fixture['officer'])
        ->get(route('user.disbursements.transactions.receipt.pdf', [$fixture['department'], $batch, $transaction]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('public receipt verification hides personal evidence', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $item = $batch->items()->with('beneficiary')->firstOrFail();
    $transaction = finalizeVenueRelease($fixture, $batch, $item->id);
    $receipt = $transaction->currentReceipt;

    $this->get(route('public.disbursement-receipts.verify', ['token' => $receipt->verification_token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/disbursements/verify')
            ->where('receipt.receipt_number', $receipt->receipt_number)
            ->missing('receipt.snapshot')
            ->missing('receipt.beneficiary_reference'))
        ->assertDontSee($item->beneficiary->cais_number);
});

test('voiding a release requires authority and keeps the audit record', function () {
    Storage::fake('local');
    $fixture = disbursementFixture();
    $batch = openCashDisbursement($fixture);
    $transaction = finalizeVenueRelease($fixture, $batch, $batch->items()->firstOrFail()->id);

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.void', [$fixture['department'], $batch, $transaction]), [
            'reason' => 'Wrong person.',
        ])
        ->assertForbidden();

    test()->actingAs($fixture['head'])
        ->post(route('user.disbursements.transactions.void', [$fixture['department'], $batch, $transaction]), [
            'reason' => 'Wrong person was recorded.',
        ])
        ->assertRedirect();

    expect($transaction->refresh()->status)->toBe(DisbursementTransactionStatus::Voided)
        ->and($transaction->currentReceipt->status)->toBe(DisbursementReceiptStatus::Voided)
        ->and(DisbursementReleaseRecord::query()->count())->toBe(1);

    $this->assertDatabaseHas('activity_log', [
        'event' => 'transaction_voided',
        'subject_id' => $batch->id,
    ]);
});

test('a cancelled batch cannot start a venue release', function () {
    $fixture = disbursementFixture();
    $batch = storeDisbursement($fixture);

    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.cancel', [$fixture['department'], $batch]), [
            'reason' => 'Schedule was withdrawn.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.transactions.store', [$fixture['department'], $batch->refresh(), $batch->items()->firstOrFail()]), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('status');
});

test('reconciliation separates excluded beneficiaries from releases', function () {
    Storage::fake('local');
    $fixture = disbursementFixture(requests: 2);
    $batch = storeDisbursement($fixture, 'beneficiary');
    submitDisbursement($fixture, $batch);
    $items = $batch->items()->orderBy('id')->get();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch, $items[0]]), [
            'decision' => 'excluded',
            'remarks' => 'Did not meet the program rule.',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.items.decide', [$batch->refresh(), $items[1]]), [
            'decision' => 'approved',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['governor'])
        ->post(route('executive.disbursements.finalize', $batch->refresh()))
        ->assertRedirect();

    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.schedule', [$fixture['department'], $batch->refresh()]), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'venue' => 'Covered court',
        ])
        ->assertRedirect();

    test()->actingAs($fixture['officer'])
        ->post(route('user.disbursements.start', [$fixture['department'], $batch->refresh()]))
        ->assertRedirect();

    finalizeVenueRelease($fixture, $batch->refresh(), $items[1]->id, [
        'lines' => [[
            'id' => $items[1]->lines()->firstOrFail()->id,
            'actual_amount' => '600',
        ]],
    ]);

    $summary = app(DisbursementReconciliation::class)->summarize($batch->refresh());

    expect($summary['excluded_beneficiaries'])->toBe(1)
        ->and($summary['served_beneficiaries'])->toBe(1)
        ->and($summary['approved_cash'])->toBe('600.00')
        ->and($summary['actual_cash'])->toBe('600.00');
});
