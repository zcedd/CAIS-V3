<?php

namespace App\Services\Disbursement;

use App\Enums\DisbursementReceiptStatus;
use App\Models\DisbursementAcknowledgmentReceipt;
use App\Models\DisbursementTransaction;
use App\Models\User;
use App\Support\QrCodeSvg;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisbursementReceiptService
{
    public function __construct(
        private DisbursementReferenceNumbers $numbers,
        private DisbursementAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function issue(DisbursementTransaction $transaction, User $actor, array $snapshot): DisbursementAcknowledgmentReceipt
    {
        $receipt = DisbursementAcknowledgmentReceipt::query()->create([
            'disbursement_transaction_id' => $transaction->id,
            'receipt_number' => $snapshot['receipt_number'],
            'verification_token' => $this->numbers->verificationToken(),
            'issued_at' => now(),
            'issued_by' => $actor->id,
            'version' => 1,
            'status' => DisbursementReceiptStatus::Issued,
            'snapshot' => $snapshot,
        ]);

        $batch = $transaction->batch;

        if ($batch !== null) {
            $this->audit->record($actor, $batch, 'receipt_issued', [
                'disbursement_transaction_id' => $transaction->id,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'version' => $receipt->version,
            ]);
        }

        return $receipt;
    }

    public function reprint(DisbursementAcknowledgmentReceipt $receipt, User $actor): DisbursementAcknowledgmentReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): DisbursementAcknowledgmentReceipt {
            $receipt = DisbursementAcknowledgmentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
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
        DisbursementAcknowledgmentReceipt $receipt,
        User $actor,
        string $reason,
        array $changes = [],
    ): DisbursementAcknowledgmentReceipt {
        return DB::transaction(function () use ($receipt, $actor, $reason, $changes): DisbursementAcknowledgmentReceipt {
            $current = DisbursementAcknowledgmentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== DisbursementReceiptStatus::Issued) {
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

            $current->status = DisbursementReceiptStatus::Superseded;
            $current->save();

            $replacement = DisbursementAcknowledgmentReceipt::query()->create([
                'disbursement_transaction_id' => $current->disbursement_transaction_id,
                'receipt_number' => $snapshot['receipt_number'],
                'verification_token' => $this->numbers->verificationToken(),
                'issued_at' => now(),
                'issued_by' => $actor->id,
                'version' => $snapshot['version'],
                'supersedes_receipt_id' => $current->id,
                'status' => DisbursementReceiptStatus::Issued,
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

    public function pdf(DisbursementAcknowledgmentReceipt $receipt): string
    {
        $snapshot = $receipt->snapshot ?? [];
        $verifyUrl = route('public.disbursement-receipts.verify', ['token' => $receipt->verification_token]);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.disbursement-receipt', [
            'receipt' => $snapshot,
            'status' => $receipt->status->label(),
            'qrSvg' => QrCodeSvg::fromString($verifyUrl),
        ])->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function publicView(array $snapshot, DisbursementReceiptStatus $status): array
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
            'disbursement_date' => $snapshot['disbursement_date'] ?? null,
            'items' => $items,
            'total_amount' => $snapshot['total_amount'] ?? null,
            'issued_at' => $snapshot['issued_at'] ?? null,
        ];
    }
}
