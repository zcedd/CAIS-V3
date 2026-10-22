<?php

namespace App\Services\Distribution;

use App\Enums\AssistanceItemOrigin;
use App\Enums\DistributionEvidenceType;
use App\Enums\DistributionReceiptStatus;
use App\Enums\ItemKind;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\DistributionAcknowledgmentReceipt;
use App\Models\DistributionBatch;
use App\Models\DistributionEvidence;
use App\Models\DistributionTransaction;
use App\Models\Program;
use App\Models\User;
use App\Services\User\AssistanceReceiptPage;
use App\Support\EmptyCell;
use App\Support\QrCodeSvg;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionReceiptService
{
    public function __construct(
        private DistributionReferenceNumbers $numbers,
        private DistributionAudit $audit,
        private DistributionEvidenceService $evidence,
        private AssistanceReceiptPage $receiptPage,
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
        $receipt->loadMissing([
            'transaction.evidence',
            'transaction.batch.program',
            'transaction.batch.department',
            'transaction.batchItem.assistance',
        ]);
        $snapshot = $receipt->snapshot ?? [];
        $verifyUrl = route('public.distribution-receipts.verify', ['token' => $receipt->verification_token]);
        $signatureSrc = $this->evidenceDataUri($receipt, DistributionEvidenceType::Signature);
        $photoSrc = $this->evidenceDataUri($receipt, DistributionEvidenceType::LivePhoto);
        $page = $this->onlineReceipt($receipt);
        $matches = is_array($page)
            && (string) ($page['distribution']['receipt_number'] ?? '') === (string) $receipt->receipt_number;

        return view('pdf.distribution-receipt', [
            'document' => $matches
                ? $this->documentFromPage($page, $signatureSrc, $photoSrc)
                : $this->documentFromSnapshot($receipt, $snapshot, $signatureSrc, $photoSrc, QrCodeSvg::fromString($verifyUrl)),
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
     * The printable page staff open in the browser. The PDF uses it when this receipt is the issued copy.
     *
     * @return array<string, mixed>|null
     */
    private function onlineReceipt(DistributionAcknowledgmentReceipt $receipt): ?array
    {
        $transaction = $receipt->transaction;
        $batch = $transaction?->batch;
        $department = $batch?->department;
        $program = $batch?->program;
        $assistance = $transaction?->batchItem?->assistance;

        if (! $transaction instanceof DistributionTransaction
            || ! $batch instanceof DistributionBatch
            || ! $department instanceof Department
            || ! $program instanceof Program
            || ! $assistance instanceof Assistance) {
            return null;
        }

        $page = $this->receiptPage->forTransaction($department, $batch, $transaction);
        $payload = $page['receipt'] ?? null;

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function documentFromPage(array $page, ?string $signatureSrc, ?string $photoSrc): array
    {
        $distribution = is_array($page['distribution'] ?? null) ? $page['distribution'] : [];
        $department = $this->text($page['department_name']) ?? (string) config('app.name');
        $beneficiaryName = $this->text($page['beneficiary_name']) ?? EmptyCell::VALUE;
        $recipientName = $this->text($distribution['recipient_name'] ?? null) ?? $beneficiaryName;
        $recipientType = $this->text($distribution['recipient_type'] ?? null);

        $receiptNumber = $this->text($distribution['receipt_number'] ?? null) ?? $this->text($page['cais_number']) ?? EmptyCell::VALUE;

        return [
            'receipt_number' => $receiptNumber,
            'department_name' => $department,
            'facts' => $this->factRows([
                $this->fact('Receipt number', $receiptNumber, mono: true),
                $this->fact('Date released', $this->formatDate($page['date_delivered'] ?? null)),
                $this->fact('Program', $this->text($page['program_name']) ?? EmptyCell::VALUE),
                $this->fact('Department', $department),
                $this->optionalFact('Distribution reference', $distribution['transaction_number'] ?? null, mono: true),
                $this->optionalFact('Batch', $distribution['batch_code'] ?? null, mono: true),
                $this->optionalFact('Venue', $distribution['venue'] ?? null),
                $this->fact('Assistance request', '#'.(string) ($page['assistance_id'] ?? ''), mono: true),
            ]),
            'beneficiary_name' => $beneficiaryName,
            'beneficiary_type' => $this->text($page['beneficiary_type'] ?? null),
            'beneficiary_id_number' => $this->text($page['beneficiary_id_number'] ?? null) ?? $this->text($page['cais_number']) ?? EmptyCell::VALUE,
            'beneficiary_address' => $this->text($page['beneficiary_address'] ?? null) ?? 'No address on file',
            'qr_svg' => is_string($page['qr_svg'] ?? null) ? $page['qr_svg'] : '',
            'released_items' => $this->releasedRows($page['released_items'] ?? []),
            'cash_total' => $this->formatMoney($page['cash_total'] ?? 0),
            'requested_items' => $this->requestedRows($page['requested_items'] ?? []),
            'variance' => $this->varianceText($page['item_variance'] ?? null),
            'acknowledgment' => 'I acknowledge that I received the assistance listed above from '.$department.'.',
            'acknowledgment_facts' => $this->factRows([
                $this->fact('Received by', $recipientType !== null ? $recipientName.' ('.$recipientType.')' : $recipientName),
                $this->fact('Acknowledgment', $this->text($distribution['acknowledgment_mode'] ?? null) ?? EmptyCell::VALUE),
                $this->optionalFact('Alternative acknowledgment', $distribution['alternative_reason'] ?? null, wide: true),
                $this->fact('Acknowledged', $this->formatDate($distribution['issued_at'] ?? null)),
                $this->optionalFact('Witness', $distribution['witness'] ?? null),
                $this->optionalFact('Verification code', $distribution['verification_token'] ?? null, mono: true, wide: true),
            ]),
            'recipient_name' => $recipientName,
            'released_by' => $this->text($page['released_by'] ?? null) ?? 'Releasing officer',
            'signature_src' => $signatureSrc,
            'photo_src' => $photoSrc,
            'profile_url' => $this->text($page['profile_url'] ?? null),
            'printed_at' => $this->formatDate($page['printed_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function documentFromSnapshot(
        DistributionAcknowledgmentReceipt $receipt,
        array $snapshot,
        ?string $signatureSrc,
        ?string $photoSrc,
        string $qrSvg,
    ): array {
        $department = $this->text($snapshot['department'] ?? null) ?? (string) config('app.name');
        $beneficiaryName = $this->text($snapshot['beneficiary_name'] ?? null) ?? $this->text($snapshot['recipient_name'] ?? null) ?? EmptyCell::VALUE;
        $recipientName = $this->text($snapshot['recipient_name'] ?? null) ?? $beneficiaryName;
        $recipientType = $this->text($snapshot['recipient_type'] ?? null);

        $receiptNumber = $this->text($snapshot['receipt_number'] ?? null) ?? $receipt->receipt_number;

        return [
            'receipt_number' => $receiptNumber,
            'department_name' => $department,
            'facts' => $this->factRows([
                $this->fact('Receipt number', $receiptNumber, mono: true),
                $this->fact('Date released', $this->formatDate($snapshot['distribution_date'] ?? null)),
                $this->fact('Program', $this->text($snapshot['program'] ?? null) ?? EmptyCell::VALUE),
                $this->fact('Department', $department),
                $this->optionalFact('Distribution reference', $snapshot['transaction_number'] ?? null, mono: true),
                $this->optionalFact('Batch', $snapshot['batch_code'] ?? null, mono: true),
                $this->optionalFact('Venue', $snapshot['venue'] ?? null),
                $this->fact('Assistance request', '#'.(string) ($snapshot['assistance_id'] ?? ''), mono: true),
            ]),
            'beneficiary_name' => $beneficiaryName,
            'beneficiary_type' => $this->text($snapshot['beneficiary_type'] ?? null),
            'beneficiary_id_number' => $this->text($snapshot['beneficiary_id_number'] ?? null) ?? $this->text($snapshot['beneficiary_reference'] ?? null) ?? EmptyCell::VALUE,
            'beneficiary_address' => $this->text($snapshot['beneficiary_address'] ?? null) ?? 'No address on file',
            'qr_svg' => $qrSvg,
            'released_items' => $this->snapshotRows($snapshot['items'] ?? []),
            'cash_total' => $this->formatMoney($snapshot['total_amount'] ?? 0),
            'requested_items' => [],
            'variance' => null,
            'acknowledgment' => 'I acknowledge that I received the assistance listed above from '.$department.'.',
            'acknowledgment_facts' => $this->factRows([
                $this->fact('Received by', $recipientType !== null ? $recipientName.' ('.$recipientType.')' : $recipientName),
                $this->fact('Acknowledgment', $this->text($snapshot['acknowledgment_mode'] ?? null) ?? EmptyCell::VALUE),
                $this->optionalFact('Alternative acknowledgment', $snapshot['alternative_reason'] ?? null, wide: true),
                $this->fact('Acknowledged', $this->formatDate($snapshot['issued_at'] ?? null)),
                $this->optionalFact('Witness', $snapshot['witness'] ?? null),
                $this->optionalFact('Verification code', $receipt->verification_token, mono: true, wide: true),
            ]),
            'recipient_name' => $recipientName,
            'released_by' => $this->text($snapshot['released_by'] ?? null) ?? 'Releasing officer',
            'signature_src' => $signatureSrc,
            'photo_src' => $photoSrc,
            'profile_url' => null,
            'printed_at' => $this->formatDate(now()->toDateTimeString()),
        ];
    }

    /**
     * @param  list<array{label: string, value: string, mono: bool, wide: bool}|null>  $facts
     * @return list<list<array{label: string, value: string, mono: bool, wide: bool}>>
     */
    private function factRows(array $facts): array
    {
        $rows = [];
        $pending = null;

        foreach ($facts as $fact) {
            if (! is_array($fact)) {
                continue;
            }

            if ($fact['wide']) {
                if ($pending !== null) {
                    $rows[] = [$pending];
                    $pending = null;
                }

                $rows[] = [$fact];

                continue;
            }

            if ($pending === null) {
                $pending = $fact;

                continue;
            }

            $rows[] = [$pending, $fact];
            $pending = null;
        }

        if ($pending !== null) {
            $rows[] = [$pending];
        }

        return $rows;
    }

    /**
     * @return array{label: string, value: string, mono: bool, wide: bool}
     */
    private function fact(string $label, string $value, bool $mono = false, bool $wide = false): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'mono' => $mono,
            'wide' => $wide,
        ];
    }

    /**
     * @return array{label: string, value: string, mono: bool, wide: bool}|null
     */
    private function optionalFact(string $label, mixed $value, bool $mono = false, bool $wide = false): ?array
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        return $this->fact($label, $text, $mono, $wide);
    }

    /**
     * @return list<array{name: string, amount: string, type: string, detail: string}>
     */
    private function releasedRows(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $detail = collect([
                $this->text($item['fulfillment_reason'] ?? null),
                $this->text($item['specification'] ?? null),
            ])->filter()->implode(' · ');

            $rows[] = [
                'name' => $this->text($item['name'] ?? null) ?? EmptyCell::VALUE,
                'amount' => $this->formatQuantity($item['quantity'] ?? null, $item['unit'] ?? null, $item['kind'] ?? null),
                'type' => $this->originLabel($item['origin'] ?? null, $this->text($item['substituted_for_name'] ?? null)),
                'detail' => $detail !== '' ? $detail : EmptyCell::VALUE,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, requested: string, released: string, specification: string}>
     */
    private function requestedRows(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $released = $this->formatQuantity($item['released_quantity'] ?? null, $item['unit'] ?? null, $item['kind'] ?? null);

            if ((int) ($item['substituted_quantity'] ?? 0) > 0) {
                $released .= ' (substituted)';
            }

            $rows[] = [
                'name' => $this->text($item['name'] ?? null) ?? EmptyCell::VALUE,
                'requested' => $this->formatQuantity($item['requested_quantity'] ?? null, $item['unit'] ?? null, $item['kind'] ?? null),
                'released' => $released,
                'specification' => $this->text($item['specification'] ?? null) ?? EmptyCell::VALUE,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, amount: string, type: string, detail: string}>
     */
    private function snapshotRows(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $amount = $item['amount'] ?? null;
            $display = is_numeric($amount) && (float) $amount > 0
                ? $this->formatMoney($amount)
                : $this->formatQuantity($item['quantity'] ?? null, $item['unit'] ?? null, null);
            $outcome = $this->text($item['outcome'] ?? null);

            $rows[] = [
                'name' => $this->text($item['description'] ?? null) ?? 'Assistance',
                'amount' => $display,
                'type' => $outcome !== null ? ucfirst($outcome) : EmptyCell::VALUE,
                'detail' => EmptyCell::VALUE,
            ];
        }

        return $rows;
    }

    private function formatQuantity(mixed $quantity, mixed $unit, mixed $kind): string
    {
        $resolvedKind = $kind instanceof ItemKind
            ? $kind
            : ItemKind::tryFrom(is_string($kind) ? $kind : '');

        return ItemKind::formatQuantity(
            is_numeric($quantity) ? (int) $quantity : null,
            $this->text($unit),
            $resolvedKind,
        );
    }

    private function formatMoney(mixed $amount): string
    {
        if (! is_numeric($amount)) {
            return '₱0.00';
        }

        return '₱'.number_format((float) $amount, 2);
    }

    private function originLabel(mixed $origin, ?string $substitutedFor): string
    {
        $value = $origin instanceof AssistanceItemOrigin
            ? $origin
            : AssistanceItemOrigin::tryFrom(is_string($origin) ? $origin : '');

        $label = match ($value) {
            AssistanceItemOrigin::Additional => 'Additional',
            AssistanceItemOrigin::Substitute => 'Substitute',
            default => 'Requested',
        };

        if ($substitutedFor !== null) {
            $label .= ' for '.$substitutedFor;
        }

        return $label;
    }

    private function varianceText(mixed $variance): ?string
    {
        if (! is_array($variance) || ! ($variance['has_variance'] ?? false)) {
            return null;
        }

        $parts = array_values(array_filter([
            ((int) ($variance['additional_quantity'] ?? 0)) > 0 ? ((int) $variance['additional_quantity']).' additional' : null,
            ((int) ($variance['substitute_quantity'] ?? 0)) > 0 ? ((int) $variance['substitute_quantity']).' substitute' : null,
            ((int) ($variance['shortfall_quantity'] ?? 0)) > 0 ? ((int) $variance['shortfall_quantity']).' not yet released' : null,
        ]));

        if ($parts === []) {
            return null;
        }

        return 'Variance against the request: '.implode(', ', $parts).'.';
    }

    private function formatDate(mixed $value): string
    {
        $text = $this->text($value);

        if ($text === null) {
            return EmptyCell::VALUE;
        }

        try {
            return Carbon::parse($text)->format('M j, Y');
        } catch (\Throwable) {
            return EmptyCell::VALUE;
        }
    }

    private function text(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
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
