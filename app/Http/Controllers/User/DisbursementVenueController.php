<?php

namespace App\Http\Controllers\User;

use App\Actions\Disbursement\AssignDisbursementPersonnel;
use App\Actions\Disbursement\FinalizeDisbursementTransaction;
use App\Actions\Disbursement\StartDisbursementTransaction;
use App\Actions\Disbursement\VoidDisbursementTransaction;
use App\Enums\DisbursementEvidenceType;
use App\Enums\DisbursementItemApprovalStatus;
use App\Enums\DisbursementReleaseOutcome;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Disbursement\AssignDisbursementPersonnelRequest;
use App\Http\Requests\User\Disbursement\FinalizeDisbursementTransactionRequest;
use App\Http\Requests\User\Disbursement\StartDisbursementTransactionRequest;
use App\Http\Requests\User\Disbursement\StoreDisbursementEvidenceRequest;
use App\Http\Requests\User\Disbursement\SupersedeDisbursementReceiptRequest;
use App\Http\Requests\User\Disbursement\VoidDisbursementTransactionRequest;
use App\Models\Department;
use App\Models\DisbursementAcknowledgmentReceipt;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchItem;
use App\Models\DisbursementEvidence;
use App\Models\DisbursementTransaction;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementEvidenceService;
use App\Services\Disbursement\DisbursementReceiptService;
use App\Services\Disbursement\DisbursementReconciliation;
use App\Support\DecimalAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DisbursementVenueController extends Controller
{
    public function show(
        Request $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementReconciliation $reconciliation,
    ): Response {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('view', $disbursementBatch);

        $search = $request->string('q')->trim()->toString();
        $items = DisbursementBatchItem::query()
            ->with([
                'beneficiary:id,name,cais_number',
                'releases:id,disbursement_batch_item_id,outcome',
            ])
            ->where('disbursement_batch_id', $disbursementBatch->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->whereHas('beneficiary', function ($beneficiary) use ($search): void {
                        $beneficiary->where('name', 'like', '%'.$search.'%')
                            ->orWhere('cais_number', 'like', '%'.$search.'%');
                    });

                    if (ctype_digit($search)) {
                        $inner->orWhere('assistance_id', (int) $search);
                    }
                });
            })
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DisbursementBatchItem $item): array => [
                'id' => $item->id,
                'assistance_id' => $item->assistance_id,
                'name' => $item->beneficiary?->name,
                'cais_number' => $item->beneficiary?->cais_number,
                'approval_status' => $item->approval_status->value,
                'release_status' => $this->releaseStatus($item),
            ]);

        $selected = null;
        $itemId = $request->integer('item');

        if ($itemId > 0) {
            $item = DisbursementBatchItem::query()
                ->with([
                    'beneficiary:id,name,cais_number',
                    'lines.item:id,name,kind,item_unit_measurement_id',
                    'lines.item.unitMeasurement:id,name',
                    'lines.releases',
                    'transactions.evidence',
                    'transactions.currentReceipt',
                ])
                ->where('disbursement_batch_id', $disbursementBatch->id)
                ->whereKey($itemId)
                ->first();

            if ($item instanceof DisbursementBatchItem) {
                $selected = $this->selectedPayload($department, $disbursementBatch, $item);
            }
        }

        $summary = $reconciliation->summarize($disbursementBatch);
        $eligible = (int) $summary['approved_beneficiaries'];
        $completed = (int) $summary['served_beneficiaries'];
        $progress = $eligible === 0 ? 0 : (int) round(($completed / $eligible) * 100);

        return Inertia::render('user/disbursements/venue', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch' => [
                'id' => $disbursementBatch->id,
                'batch_code' => $disbursementBatch->batch_code,
                'title' => $disbursementBatch->title,
                'status' => $disbursementBatch->status->value,
                'status_label' => $disbursementBatch->status->label(),
                'program' => $disbursementBatch->program()->value('name'),
                'venue' => $disbursementBatch->venue,
                'venue_details' => $disbursementBatch->venue_details,
                'scheduled_at' => $disbursementBatch->scheduled_at?->toIso8601String(),
                'progress' => $progress,
            ],
            'summary' => $summary,
            'filters' => ['q' => $search, 'item' => $itemId > 0 ? $itemId : null],
            'beneficiaries' => $items,
            'selected' => $selected,
            'personnel' => $disbursementBatch->assignments()->with('user:id,firstName,lastName')->get()->map(fn ($assignment): array => [
                'id' => $assignment->user_id,
                'name' => $assignment->user?->name,
            ])->all(),
            'officers' => User::query()
                ->where('department_id', $department->id)
                ->orderBy('lastName')
                ->limit(100)
                ->get(['id', 'firstName', 'lastName'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                ->all(),
            'can' => [
                'execute' => $request->user()?->can('execute', $disbursementBatch) ?? false,
                'assign' => $request->user()?->can('assignPersonnel', $disbursementBatch) ?? false,
                'void' => $request->user()?->can('voidTransaction', $disbursementBatch) ?? false,
                'waive' => $request->user()?->can('waiveLivePhoto', $disbursementBatch) ?? false,
                'evidence' => $request->user()?->can('viewEvidence', $disbursementBatch) ?? false,
                'reprint' => $request->user()?->can('reprintReceipt', $disbursementBatch) ?? false,
                'audit' => $request->user()?->can(PermissionName::DisbursementViewAudit->value) ?? false,
            ],
            'audit' => $request->user()?->can(PermissionName::DisbursementViewAudit->value)
                ? Activity::query()
                    ->with('causer')
                    ->where('log_name', 'disbursement')
                    ->where('subject_type', $disbursementBatch->getMorphClass())
                    ->where('subject_id', $disbursementBatch->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Activity $entry): array => [
                        'id' => $entry->id,
                        'event' => $entry->event,
                        'actor' => $entry->causer instanceof User ? $entry->causer->name : null,
                        'created_at' => $entry->created_at?->toIso8601String(),
                    ])
                    ->all()
                : [],
            'privacy_notice' => 'Personal information, a live photograph, and a signature or alternative acknowledgment are collected to document the release of government assistance. The lawful basis is the agency\'s delivery of an approved assistance program and the Data Privacy Act of 2012. A camera image, timestamp, or file hash does not by itself prove identity or that the recipient was present.',
            'retention_notice' => (string) config('disbursement.evidence_retention'),
        ]);
    }

    public function assign(
        AssignDisbursementPersonnelRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        AssignDisbursementPersonnel $assign,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $assign($disbursementBatch, $request->user(), $request->validated('user_ids'));

        return back()->with('success', 'Disbursement personnel assigned.');
    }

    public function start(
        StartDisbursementTransactionRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementBatchItem $item,
        StartDisbursementTransaction $start,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        abort_unless((int) $item->disbursement_batch_id === (int) $disbursementBatch->id, 404);
        $start($disbursementBatch, $item, $request->user(), $request->validated('idempotency_key'));

        return back()->with('success', 'Release started.');
    }

    public function evidence(
        StoreDisbursementEvidenceRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementTransaction $transaction,
        DisbursementEvidenceService $evidence,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $this->transactionBelongs($disbursementBatch, $transaction);
        $evidence->store(
            $transaction,
            $request->user(),
            $request->file('file'),
            DisbursementEvidenceType::from($request->validated('evidence_type')),
            ['capture_source' => $request->validated('capture_source')],
        );

        return back()->with('success', 'Evidence saved.');
    }

    public function finalize(
        FinalizeDisbursementTransactionRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementTransaction $transaction,
        FinalizeDisbursementTransaction $finalize,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $this->transactionBelongs($disbursementBatch, $transaction);
        $saved = $finalize($disbursementBatch, $transaction, $request->user(), $request->validated());

        return back()->with('success', 'Release '.$saved->transaction_number.' finalized.');
    }

    public function void(
        VoidDisbursementTransactionRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementTransaction $transaction,
        VoidDisbursementTransaction $void,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $this->transactionBelongs($disbursementBatch, $transaction);
        $void($disbursementBatch, $transaction, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Release voided.');
    }

    public function showEvidence(
        Request $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementTransaction $transaction,
        DisbursementEvidence $evidence,
        DisbursementAudit $audit,
    ): SymfonyResponse {
        $this->scoped($department, $disbursementBatch);
        $this->transactionBelongs($disbursementBatch, $transaction);
        abort_unless((int) $evidence->disbursement_transaction_id === (int) $transaction->id, 404);
        Gate::authorize('viewEvidence', $disbursementBatch);
        $audit->record($request->user(), $disbursementBatch, 'evidence_viewed', [
            'evidence_id' => $evidence->id,
            'evidence_type' => $evidence->evidence_type->value,
            'disbursement_transaction_id' => $transaction->id,
        ]);

        return Storage::disk($evidence->disk)->response($evidence->path, null, [
            'Content-Type' => $evidence->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function receipt(
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementTransaction $transaction,
    ): Response {
        $this->scoped($department, $disbursementBatch);
        $this->transactionBelongs($disbursementBatch, $transaction);
        Gate::authorize('view', $disbursementBatch);
        $receipt = $this->currentReceipt($transaction);

        return Inertia::render('user/disbursements/receipt', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch_id' => $disbursementBatch->id,
            'transaction_id' => $transaction->id,
            'receipt' => [
                'id' => $receipt->id,
                'number' => $receipt->receipt_number,
                'status' => $receipt->status->value,
                'status_label' => $receipt->status->label(),
                'version' => $receipt->version,
                'reprint_count' => $receipt->reprint_count,
                'snapshot' => $receipt->snapshot,
                'verify_url' => route('public.disbursement-receipts.verify', ['token' => $receipt->verification_token]),
            ],
        ]);
    }

    public function receiptPdf(
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementTransaction $transaction,
        DisbursementReceiptService $receipts,
    ): \Illuminate\Http\Response {
        $this->scoped($department, $disbursementBatch);
        $this->transactionBelongs($disbursementBatch, $transaction);
        Gate::authorize('view', $disbursementBatch);
        $receipt = $this->currentReceipt($transaction);

        return response($receipts->pdf($receipt), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipt->receipt_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function reprint(
        Request $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementAcknowledgmentReceipt $receipt,
        DisbursementReceiptService $receipts,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $this->receiptBelongs($disbursementBatch, $receipt);
        Gate::authorize('reprintReceipt', $disbursementBatch);
        $receipts->reprint($receipt, $request->user());

        return back()->with('success', 'Reprint recorded. This did not create another release.');
    }

    public function supersede(
        SupersedeDisbursementReceiptRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementAcknowledgmentReceipt $receipt,
        DisbursementReceiptService $receipts,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $this->receiptBelongs($disbursementBatch, $receipt);
        $changes = [];

        if ($request->filled('recipient_name')) {
            $changes['recipient_name'] = $request->validated('recipient_name');
        }

        $receipts->supersede($receipt, $request->user(), $request->validated('reason'), $changes);

        return back()->with('success', 'A replacement receipt was issued. The previous receipt remains on file.');
    }

    private function scoped(Department $department, DisbursementBatch $batch): void
    {
        abort_unless((int) $batch->department_id === (int) $department->id, 404);
    }

    private function transactionBelongs(DisbursementBatch $batch, DisbursementTransaction $transaction): void
    {
        abort_unless((int) $transaction->disbursement_batch_id === (int) $batch->id, 404);
    }

    private function receiptBelongs(DisbursementBatch $batch, DisbursementAcknowledgmentReceipt $receipt): void
    {
        $receipt->loadMissing('transaction');
        abort_unless((int) $receipt->transaction?->disbursement_batch_id === (int) $batch->id, 404);
    }

    private function currentReceipt(DisbursementTransaction $transaction): DisbursementAcknowledgmentReceipt
    {
        $receipt = $transaction->receipts()->where('status', 'issued')->latest('id')->first()
            ?? $transaction->receipts()->latest('id')->first();

        abort_unless($receipt instanceof DisbursementAcknowledgmentReceipt, 404);

        return $receipt;
    }

    private function releaseStatus(DisbursementBatchItem $item): string
    {
        if ($item->approval_status === DisbursementItemApprovalStatus::Excluded) {
            return 'excluded';
        }

        if ($item->releases->contains(fn ($release): bool => $release->outcome === DisbursementReleaseOutcome::Absent)) {
            return 'absent';
        }

        if ($item->releases->contains(fn ($release): bool => $release->outcome === DisbursementReleaseOutcome::Served)) {
            return 'served';
        }

        if ($item->releases->isNotEmpty()) {
            return 'partial';
        }

        return 'pending';
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedPayload(Department $department, DisbursementBatch $batch, DisbursementBatchItem $item): array
    {
        $transaction = $item->transactions->sortByDesc('id')->first();

        return [
            'id' => $item->id,
            'assistance_id' => $item->assistance_id,
            'name' => $item->beneficiary?->name,
            'cais_number' => $item->beneficiary?->cais_number,
            'approval_status' => $item->approval_status->value,
            'exclusion_reason' => $item->exclusion_reason,
            'lines' => $item->lines->map(function ($line): array {
                $releasedQuantity = DecimalAmount::of((string) $line->releases->sum('actual_quantity'));
                $releasedAmount = DecimalAmount::of((string) $line->releases->sum('actual_amount'));

                return [
                    'id' => $line->id,
                    'name' => $line->item?->name,
                    'unit' => $line->item?->unitMeasurement?->name,
                    'approved_quantity' => $line->approved_quantity,
                    'approved_amount' => $line->approved_amount,
                    'released_quantity' => $releasedQuantity,
                    'released_amount' => $releasedAmount,
                    'remaining_quantity' => $line->approved_quantity === null ? null : DecimalAmount::sub($line->approved_quantity, $releasedQuantity),
                    'remaining_amount' => $line->approved_amount === null ? null : DecimalAmount::sub($line->approved_amount, $releasedAmount),
                ];
            })->all(),
            'transaction' => $transaction instanceof DisbursementTransaction ? [
                'id' => $transaction->id,
                'number' => $transaction->transaction_number,
                'status' => $transaction->status->value,
                'status_label' => $transaction->status->label(),
                'evidence' => $transaction->evidence->map(fn (DisbursementEvidence $row): array => [
                    'id' => $row->id,
                    'type' => $row->evidence_type->value,
                    'mime_type' => $row->mime_type,
                    'sha256_hash' => $row->sha256_hash,
                    'captured_at' => $row->captured_at?->toIso8601String(),
                    'preview_url' => route('user.disbursements.transactions.evidence.show', [
                        'department' => $department,
                        'disbursementBatch' => $batch,
                        'transaction' => $transaction,
                        'evidence' => $row,
                    ]),
                ])->all(),
                'receipt' => $transaction->currentReceipt === null ? null : [
                    'id' => $transaction->currentReceipt->id,
                    'number' => $transaction->currentReceipt->receipt_number,
                    'status' => $transaction->currentReceipt->status->value,
                ],
            ] : null,
        ];
    }
}
