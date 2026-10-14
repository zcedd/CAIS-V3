<?php

namespace App\Services\Distribution;

use App\Enums\DistributionEvidenceType;
use App\Enums\DistributionReceiptStatus;
use App\Models\DistributionAcknowledgmentReceipt;
use App\Models\DistributionEvidence;
use App\Models\DistributionTransaction;
use App\Models\User;
use App\Support\QrCodeSvg;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionReceiptService
{
    public function __construct(
        private DistributionReferenceNumbers $numbers,
        private DistributionAudit $audit,
        private DistributionEvidenceService $evidence,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function issue(DistributionTransaction $transaction, User $actor, array $snapshot): DistributionAcknowledgmentReceipt
    {
        $receipt = DistributionAcknowledgmentReceipt::query()->create([
            'distribution_transaction_id' => $transaction->id,
            'receipt_number' => $snapshot['receipt_number'],
            'verification_token' => $this->numbers->verificationToken(),
            'issued_at' => now(),
            'issued_by' => $actor->id,
            'version' => 1,
            'status' => DistributionReceiptStatus::Issued,
            'snapshot' => $snapshot,
        ]);

        $batch = $transaction->batch;

        if ($batch !== null) {
            $this->audit->record($actor, $batch, 'receipt_issued', [
                'distribution_transaction_id' => $transaction->id,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'version' => $receipt->version,
            ]);
        }

        return $receipt;
    }

    public function reprint(DistributionAcknowledgmentReceipt $receipt, User $actor): DistributionAcknowledgmentReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): DistributionAcknowledgmentReceipt {
            $receipt = DistributionAcknowledgmentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $receipt->reprint_count = ((int) $receipt->reprint_count) + 1;
            $receipt->save();

            $batch = $receipt->transaction?->batch;

            if ($batch !== null) {
                $this->audit->record($actor, $batch, 'receipt_reprinted', [
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'reprint_count' => $receipt->reprint_count,
                ]);
            }

            return $receipt;
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function supersede(
        DistributionAcknowledgmentReceipt $receipt,
        User $actor,
        string $reason,
        array $changes = [],
    ): DistributionAcknowledgmentReceipt {
        return DB::transaction(function () use ($receipt, $actor, $reason, $changes): DistributionAcknowledgmentReceipt {
            $current = DistributionAcknowledgmentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== DistributionReceiptStatus::Issued) {
                throw ValidationException::withMessages([
                    'receipt' => 'Only the current issued receipt can be replaced.',
                ]);
            }

            $snapshot = $current->snapshot ?? [];
            foreach ($changes as $key => $value) {
                $snapshot[$key] = $value;
            }

            $snapshot['receipt_number'] = $this->numbers->receiptNumber();
            $snapshot['issued_at'] = now()->toIso8601String();
            $snapshot['version'] = ((int) $current->version) + 1;
            $snapshot['supersedes'] = $current->receipt_number;

            $current->status = DistributionReceiptStatus::Superseded;
            $current->save();

            $replacement = DistributionAcknowledgmentReceipt::query()->create([
                'distribution_transaction_id' => $current->distribution_transaction_id,
                'receipt_number' => $snapshot['receipt_number'],
                'verification_token' => $this->numbers->verificationToken(),
                'issued_at' => now(),
                'issued_by' => $actor->id,
                'version' => $snapshot['version'],
                'supersedes_receipt_id' => $current->id,
                'status' => DistributionReceiptStatus::Issued,
                'notes' => $reason,
                'snapshot' => $snapshot,
            ]);

            $batch = $current->transaction?->batch;

            if ($batch !== null) {
                $this->audit->record($actor, $batch, 'receipt_superseded', [
                    'previous_receipt_id' => $current->id,
                    'previous_receipt_number' => $current->receipt_number,
                    'receipt_id' => $replacement->id,
                    'receipt_number' => $replacement->receipt_number,
                    'reason' => $reason,
                ]);
            }

            return $replacement;
        });
    }

    public function html(DistributionAcknowledgmentReceipt $receipt): string
    {
        $receipt->loadMissing('transaction.evidence');
        $snapshot = $receipt->snapshot ?? [];
        $verifyUrl = route('public.distribution-receipts.verify', ['token' => $receipt->verification_token]);

        return view('pdf.distribution-receipt', [
            'receipt' => $snapshot,
            'status' => $receipt->status->label(),
            'qrSvg' => QrCodeSvg::fromString($verifyUrl),
            'signatureSrc' => $this->evidenceDataUri($receipt, DistributionEvidenceType::Signature),
            'photoSrc' => $this->evidenceDataUri($receipt, DistributionEvidenceType::LivePhoto),
        ])->render();
    }

    public function pdf(DistributionAcknowledgmentReceipt $receipt): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($receipt));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function evidenceDataUri(DistributionAcknowledgmentReceipt $receipt, DistributionEvidenceType $type): ?string
    {
        $evidence = $receipt->transaction?->evidence->first(
            fn (DistributionEvidence $row): bool => $row->evidence_type === $type,
        );

        return $evidence instanceof DistributionEvidence ? $this->evidence->dataUri($evidence) : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function publicView(array $snapshot, DistributionReceiptStatus $status): array
    {
        $items = collect($snapshot['items'] ?? [])
            ->map(fn (mixed $item): array => [
                'description' => is_array($item) ? (string) ($item['description'] ?? '') : '',
                'quantity' => is_array($item) ? ($item['quantity'] ?? null) : null,
                'unit' => is_array($item) ? ($item['unit'] ?? null) : null,
                'amount' => is_array($item) ? ($item['amount'] ?? null) : null,
            ])
            ->all();

        return [
            'receipt_number' => (string) ($snapshot['receipt_number'] ?? ''),
            'status' => $status->value,
            'status_label' => $status->label(),
            'program' => (string) ($snapshot['program'] ?? ''),
            'department' => (string) ($snapshot['department'] ?? ''),
            'venue' => (string) ($snapshot['venue'] ?? ''),
            'distribution_date' => $snapshot['distribution_date'] ?? null,
            'items' => $items,
            'total_amount' => $snapshot['total_amount'] ?? null,
            'issued_at' => $snapshot['issued_at'] ?? null,
        ];
    }
}
