<?php

namespace App\Services\User;

use App\Enums\DistributionReceiptStatus;
use App\Enums\DistributionTransactionStatus;
use App\Enums\RequestStatusCode;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Department;
use App\Models\DistributionAcknowledgmentReceipt;
use App\Models\DistributionBatch;
use App\Models\DistributionTransaction;
use App\Models\Individual;
use App\Models\Program;
use App\Models\User;
use App\Support\EmptyCell;
use App\Support\QrCodeSvg;
use Illuminate\Support\Carbon;

class AssistanceReceiptPage
{
    public function __construct(
        private AssistanceItemFulfillmentService $assistanceItemFulfillmentService,
    ) {}

    /**
     * @return array{department: array<string, mixed>, program: array<string, mixed>, receipt: array<string, mixed>}
     */
    public function forAssistance(Department $department, Program $program, Assistance $assistance): array
    {
        $transaction = DistributionTransaction::query()
            ->where('status', DistributionTransactionStatus::Finalized)
            ->whereHas('batchItem', fn ($query) => $query->where('assistance_id', $assistance->id))
            ->with(['batch.program', 'batch.department', 'currentReceipt', 'releasedBy'])
            ->latest('id')
            ->first();

        return $this->page($department, $program, $assistance, $transaction);
    }

    /**
     * @return array{department: array<string, mixed>, program: array<string, mixed>, receipt: array<string, mixed>}
     */
    public function forTransaction(
        Department $department,
        DistributionBatch $batch,
        DistributionTransaction $transaction,
    ): array {
        $transaction->loadMissing([
            'batch.program',
            'batch.department',
            'batchItem.assistance.beneficiary',
            'currentReceipt',
            'releasedBy',
            'witnessedBy',
        ]);
        $batch->loadMissing('program');
        $assistance = $transaction->batchItem?->assistance;
        $program = $batch->program;

        abort_unless($assistance instanceof Assistance && $program instanceof Program, 404);

        return $this->page($department, $program, $assistance, $transaction, true);
    }

    /**
     * @return array{department: array<string, mixed>, program: array<string, mixed>, receipt: array<string, mixed>}
     */
    private function page(
        Department $department,
        Program $program,
        Assistance $assistance,
        ?DistributionTransaction $transaction,
        bool $includeDistributionActions = false,
    ): array {
        $assistance->loadMissing([
            'beneficiary:id,name,cais_number,beneficiable_type,beneficiable_id',
            'beneficiary.beneficiable' => function ($morphTo): void {
                $morphTo->morphWith([
                    Individual::class => ['address.city', 'beneficiaryIdentification.identification'],
                ]);
            },
            'assistanceItem.item.unitMeasurement',
        ]);

        $itemFulfillment = $this->assistanceItemFulfillmentService->summarize($assistance->assistanceItem);
        $individual = $assistance->beneficiary?->beneficiable instanceof Individual
            ? $assistance->beneficiary->beneficiable
            : null;
        $barangay = $individual?->address;
        $identification = $individual?->beneficiaryIdentification->first();
        $cashTotal = (int) collect($itemFulfillment['released'])->where('kind', 'cash')->sum('quantity');
        $profileUrl = route('user.assistances.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]);
        $receipt = $this->issuedReceipt($transaction);
        $snapshot = is_array($receipt?->snapshot) ? $receipt->snapshot : [];
        $verifyUrl = $receipt instanceof DistributionAcknowledgmentReceipt
            ? route('public.distribution-receipts.verify', ['token' => $receipt->verification_token])
            : null;

        return [
            'department' => $department->only(['id', 'name', 'slug']),
            'program' => $program->only(['id', 'name']),
            'receipt' => [
                'assistance_id' => $assistance->id,
                'cais_number' => $assistance->beneficiary?->cais_number ?? EmptyCell::VALUE,
                'beneficiary_name' => $snapshot['beneficiary_name'] ?? $assistance->beneficiary?->name ?? EmptyCell::VALUE,
                'beneficiary_type' => $snapshot['beneficiary_type']
                    ?? ($assistance->beneficiary ? class_basename((string) $assistance->beneficiary->beneficiable_type) : null),
                'beneficiary_address' => $snapshot['beneficiary_address']
                    ?? ($barangay === null ? null : collect([$barangay->name, $barangay->city?->name])->filter()->implode(', ')),
                'beneficiary_id_number' => $snapshot['beneficiary_id_number'] ?? $identification?->number,
                'program_name' => $snapshot['program'] ?? $program->name,
                'department_name' => $snapshot['department'] ?? $department->name,
                'cash_total' => $cashTotal,
                'released_by' => $snapshot['released_by'] ?? $this->releasedByName($assistance, $transaction),
                'date_requested' => $assistance->date_requested
                    ? Carbon::parse($assistance->date_requested)->toDateString()
                    : null,
                'date_delivered' => $snapshot['distribution_date']
                    ?? ($assistance->date_delivered ? Carbon::parse($assistance->date_delivered)->toDateString() : null),
                'printed_at' => now()->toDateTimeString(),
                'profile_url' => $profileUrl,
                'qr_svg' => QrCodeSvg::fromString($verifyUrl ?? $profileUrl),
                'requested_items' => $itemFulfillment['requested'],
                'released_items' => $itemFulfillment['released'],
                'item_variance' => $itemFulfillment['variance'],
                'distribution' => $receipt instanceof DistributionAcknowledgmentReceipt ? [
                    'receipt_number' => $receipt->receipt_number,
                    'transaction_number' => $snapshot['transaction_number'] ?? $transaction?->transaction_number,
                    'batch_code' => $snapshot['batch_code'] ?? $transaction?->batch?->batch_code,
                    'venue' => $snapshot['venue'] ?? $transaction?->batch?->venue,
                    'distribution_date' => $snapshot['distribution_date'] ?? null,
                    'issued_at' => $receipt->issued_at?->toIso8601String(),
                    'verification_token' => $receipt->verification_token,
                    'verify_url' => $verifyUrl,
                    'recipient_name' => $snapshot['recipient_name'] ?? $transaction?->received_by_name,
                    'recipient_type' => $snapshot['recipient_type'] ?? $transaction?->received_by_type?->label(),
                    'acknowledgment_mode' => $snapshot['acknowledgment_mode'] ?? $transaction?->acknowledgment_mode?->label(),
                    'alternative_reason' => $snapshot['alternative_reason'] ?? $transaction?->alternative_reason,
                    'witness' => $snapshot['witness'] ?? $transaction?->witnessedBy?->name,
                    'pdf_url' => $includeDistributionActions && $transaction?->batch instanceof DistributionBatch
                        ? route('user.distributions.transactions.receipt.pdf', [
                            'department' => $department,
                            'distributionBatch' => $transaction->batch,
                            'transaction' => $transaction,
                        ])
                        : null,
                    'reprint_url' => $includeDistributionActions && $transaction?->batch instanceof DistributionBatch
                        ? route('user.distributions.receipts.reprint', [
                            'department' => $department,
                            'distributionBatch' => $transaction->batch,
                            'receipt' => $receipt,
                        ])
                        : null,
                    'venue_url' => $includeDistributionActions && $transaction?->batch instanceof DistributionBatch
                        ? route('user.distributions.venue', [
                            'department' => $department,
                            'distributionBatch' => $transaction->batch,
                        ])
                        : null,
                ] : null,
            ],
        ];
    }

    private function issuedReceipt(?DistributionTransaction $transaction): ?DistributionAcknowledgmentReceipt
    {
        if (! $transaction instanceof DistributionTransaction) {
            return null;
        }

        $receipt = $transaction->relationLoaded('currentReceipt')
            ? $transaction->currentReceipt
            : $transaction->currentReceipt()->first();

        if (! $receipt instanceof DistributionAcknowledgmentReceipt) {
            return null;
        }

        if ($receipt->status !== DistributionReceiptStatus::Issued) {
            $issued = $transaction->receipts()->where('status', DistributionReceiptStatus::Issued)->latest('id')->first();

            return $issued instanceof DistributionAcknowledgmentReceipt ? $issued : $receipt;
        }

        return $receipt;
    }

    private function releasedByName(Assistance $assistance, ?DistributionTransaction $transaction): ?string
    {
        $fromTransaction = $transaction?->releasedBy?->name;

        if (is_string($fromTransaction) && $fromTransaction !== '') {
            return $fromTransaction;
        }

        $userId = AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->whereHas(
                'requestSubStatus.requestStatus',
                fn ($query) => $query->where('code', RequestStatusCode::Delivered->value),
            )
            ->latest('id')
            ->value('recorded_by');

        if ($userId === null) {
            return null;
        }

        $name = User::query()->find($userId)?->name;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
