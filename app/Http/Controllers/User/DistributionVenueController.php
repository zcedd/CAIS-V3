<?php

namespace App\Http\Controllers\User;

use App\Actions\Distribution\AssignDistributionPersonnel;
use App\Actions\Distribution\FinalizeDistributionTransaction;
use App\Actions\Distribution\StartDistributionTransaction;
use App\Actions\Distribution\VoidDistributionTransaction;
use App\Enums\DistributionApprovalMode;
use App\Enums\DistributionEvidenceType;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Distribution\AssignDistributionPersonnelRequest;
use App\Http\Requests\User\Distribution\FinalizeDistributionTransactionRequest;
use App\Http\Requests\User\Distribution\StartDistributionTransactionRequest;
use App\Http\Requests\User\Distribution\StoreDistributionEvidenceRequest;
use App\Http\Requests\User\Distribution\SupersedeDistributionReceiptRequest;
use App\Http\Requests\User\Distribution\VoidDistributionTransactionRequest;
use App\Models\Department;
use App\Models\DistributionAcknowledgmentReceipt;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\DistributionEvidence;
use App\Models\DistributionTransaction;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionEvidenceService;
use App\Services\Distribution\DistributionReceiptService;
use App\Services\Distribution\DistributionReconciliation;
use App\Services\Mobile\DistributionOfflinePackage;
use App\Services\User\AssistanceReceiptPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DistributionVenueController extends Controller
{
    public function __construct(private DistributionOfflinePackage $offlinePackage) {}

    public function show(
        Request $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionReconciliation $reconciliation,
    ): Response {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('view', $distributionBatch);

        $search = $request->string('q')->trim()->toString();
        $items = DistributionBatchItem::query()
            ->with([
                'beneficiary:id,name,cais_number',
                'releases:id,distribution_batch_item_id,outcome',
            ])
            ->where('distribution_batch_id', $distributionBatch->id)
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
            ->through(fn (DistributionBatchItem $item): array => [
                'id' => $item->id,
                'assistance_id' => $item->assistance_id,
                'name' => $item->beneficiary?->name,
                'cais_number' => $item->beneficiary?->cais_number,
                'approval_status' => $item->approval_status->value,
                'release_status' => $this->offlinePackage->releaseStatus($item),
            ]);

        $releaseOnly = $distributionBatch->approval_mode === DistributionApprovalMode::Beneficiary
            && DistributionBatchItem::query()->where('distribution_batch_id', $distributionBatch->id)->count() === 1;
        $selected = null;
        $itemId = $request->integer('item');

        if ($releaseOnly && $itemId === 0) {
            $itemId = (int) DistributionBatchItem::query()
                ->where('distribution_batch_id', $distributionBatch->id)
                ->value('id');
        }

        if ($itemId > 0) {
            $item = DistributionBatchItem::query()
                ->with([
                    'beneficiary:id,name,cais_number',
                    'lines.item:id,name,kind,item_unit_measurement_id',
                    'lines.item.unitMeasurement:id,name',
                    'lines.releases',
                    'transactions.evidence',
                    'transactions.currentReceipt',
                ])
                ->where('distribution_batch_id', $distributionBatch->id)
                ->whereKey($itemId)
                ->first();

            if ($item instanceof DistributionBatchItem) {
                $selected = $this->selectedPayload($department, $distributionBatch, $item);
            }
        }

        $summary = $reconciliation->summarize($distributionBatch);
        $eligible = (int) $summary['approved_beneficiaries'];
        $completed = (int) $summary['served_beneficiaries'];
        $progress = $eligible === 0 ? 0 : (int) round(($completed / $eligible) * 100);

        return Inertia::render('user/distributions/venue', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch' => [
                'id' => $distributionBatch->id,
                'batch_code' => $distributionBatch->batch_code,
                'title' => $distributionBatch->title,
                'status' => $distributionBatch->status->value,
                'status_label' => $distributionBatch->status->label(),
                'program' => $distributionBatch->program()->value('name'),
                'venue' => $distributionBatch->venue,
                'venue_details' => $distributionBatch->venue_details,
                'scheduled_at' => $distributionBatch->scheduled_at?->toIso8601String(),
                'progress' => $progress,
            ],
            'summary' => $summary,
            'filters' => ['q' => $search, 'item' => $itemId > 0 ? $itemId : null],
            'beneficiaries' => $items,
            'selected' => $selected,
            'personnel' => $distributionBatch->assignments()->with('user:id,firstName,lastName')->get()->map(fn ($assignment): array => [
                'id' => $assignment->user_id,
                'name' => $assignment->user?->name,
            ])->all(),
            'officers' => $this->offlinePackage->officers((int) $department->id),
            'can' => [
                'execute' => $request->user()?->can('execute', $distributionBatch) ?? false,
                'assign' => $request->user()?->can('assignPersonnel', $distributionBatch) ?? false,
                'void' => $request->user()?->can('voidTransaction', $distributionBatch) ?? false,
                'waive' => $request->user()?->can('waiveLivePhoto', $distributionBatch) ?? false,
                'evidence' => $request->user()?->can('viewEvidence', $distributionBatch) ?? false,
                'reprint' => $request->user()?->can('reprintReceipt', $distributionBatch) ?? false,
                'audit' => $request->user()?->can(PermissionName::DistributionViewAudit->value) ?? false,
            ],
            'audit' => $request->user()?->can(PermissionName::DistributionViewAudit->value)
                ? Activity::query()
                    ->with('causer')
                    ->where('log_name', 'distribution')
                    ->where('subject_type', $distributionBatch->getMorphClass())
                    ->where('subject_id', $distributionBatch->id)
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
            'release_only' => $releaseOnly,
            'privacy_notice' => $this->offlinePackage->privacyNotice(),
            'retention_notice' => $this->offlinePackage->retentionNotice(),
        ]);
    }

    public function assign(
        AssignDistributionPersonnelRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        AssignDistributionPersonnel $assign,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $assign($distributionBatch, $request->user(), $request->validated('user_ids'));

        return back()->with('success', 'Distribution personnel assigned.');
    }

    public function start(
        StartDistributionTransactionRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionBatchItem $item,
        StartDistributionTransaction $start,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        abort_unless((int) $item->distribution_batch_id === (int) $distributionBatch->id, 404);
        $start($distributionBatch, $item, $request->user(), $request->validated('idempotency_key'));

        return back()->with('success', 'Release started.');
    }

    public function evidence(
        StoreDistributionEvidenceRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionTransaction $transaction,
        DistributionEvidenceService $evidence,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $this->transactionBelongs($distributionBatch, $transaction);
        $evidence->store(
            $transaction,
            $request->user(),
            $request->file('file'),
            DistributionEvidenceType::from($request->validated('evidence_type')),
            ['capture_source' => $request->validated('capture_source')],
        );

        return back()->with('success', 'Evidence saved.');
    }

    public function finalize(
        FinalizeDistributionTransactionRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionTransaction $transaction,
        FinalizeDistributionTransaction $finalize,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $this->transactionBelongs($distributionBatch, $transaction);
        $saved = $finalize($distributionBatch, $transaction, $request->user(), $request->validated());

        return back()->with('success', 'Release '.$saved->transaction_number.' finalized.');
    }

    public function void(
        VoidDistributionTransactionRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionTransaction $transaction,
        VoidDistributionTransaction $void,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $this->transactionBelongs($distributionBatch, $transaction);
        $void($distributionBatch, $transaction, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Release voided.');
    }

    public function showEvidence(
        Request $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionTransaction $transaction,
        DistributionEvidence $evidence,
        DistributionAudit $audit,
        DistributionEvidenceService $evidenceFiles,
    ): SymfonyResponse {
        $this->scoped($department, $distributionBatch);
        $this->transactionBelongs($distributionBatch, $transaction);
        abort_unless((int) $evidence->distribution_transaction_id === (int) $transaction->id, 404);
        Gate::authorize('viewEvidence', $distributionBatch);
        $audit->record($request->user(), $distributionBatch, 'evidence_viewed', [
            'evidence_id' => $evidence->id,
            'evidence_type' => $evidence->evidence_type->value,
            'distribution_transaction_id' => $transaction->id,
        ]);

        return $evidenceFiles->response($evidence);
    }

    public function receipt(
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionTransaction $transaction,
        AssistanceReceiptPage $receiptPage,
    ): Response {
        $this->scoped($department, $distributionBatch);
        $this->transactionBelongs($distributionBatch, $transaction);
        Gate::authorize('view', $distributionBatch);
        $this->currentReceipt($transaction);

        return Inertia::render(
            'user/assistances/receipt',
            $receiptPage->forTransaction($department, $distributionBatch, $transaction),
        );
    }

    public function receiptPdf(
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionTransaction $transaction,
        DistributionReceiptService $receipts,
    ): \Illuminate\Http\Response {
        $this->scoped($department, $distributionBatch);
        $this->transactionBelongs($distributionBatch, $transaction);
        Gate::authorize('view', $distributionBatch);
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
        DistributionBatch $distributionBatch,
        DistributionAcknowledgmentReceipt $receipt,
        DistributionReceiptService $receipts,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $this->receiptBelongs($distributionBatch, $receipt);
        Gate::authorize('reprintReceipt', $distributionBatch);
        $receipts->reprint($receipt, $request->user());

        return back()->with('success', 'Reprint recorded. This did not create another release.');
    }

    public function supersede(
        SupersedeDistributionReceiptRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionAcknowledgmentReceipt $receipt,
        DistributionReceiptService $receipts,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $this->receiptBelongs($distributionBatch, $receipt);
        $changes = [];

        if ($request->filled('recipient_name')) {
            $changes['recipient_name'] = $request->validated('recipient_name');
        }

        $receipts->supersede($receipt, $request->user(), $request->validated('reason'), $changes);

        return back()->with('success', 'A replacement receipt was issued. The previous receipt remains on file.');
    }

    private function scoped(Department $department, DistributionBatch $batch): void
    {
        abort_unless((int) $batch->department_id === (int) $department->id, 404);
    }

    private function transactionBelongs(DistributionBatch $batch, DistributionTransaction $transaction): void
    {
        abort_unless((int) $transaction->distribution_batch_id === (int) $batch->id, 404);
    }

    private function receiptBelongs(DistributionBatch $batch, DistributionAcknowledgmentReceipt $receipt): void
    {
        $receipt->loadMissing('transaction');
        abort_unless((int) $receipt->transaction?->distribution_batch_id === (int) $batch->id, 404);
    }

    private function currentReceipt(DistributionTransaction $transaction): DistributionAcknowledgmentReceipt
    {
        $receipt = $transaction->receipts()->where('status', 'issued')->latest('id')->first()
            ?? $transaction->receipts()->latest('id')->first();

        abort_unless($receipt instanceof DistributionAcknowledgmentReceipt, 404);

        return $receipt;
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedPayload(Department $department, DistributionBatch $batch, DistributionBatchItem $item): array
    {
        $transaction = $item->transactions->sortByDesc('id')->first();

        return [
            'id' => $item->id,
            'assistance_id' => $item->assistance_id,
            'name' => $item->beneficiary?->name,
            'cais_number' => $item->beneficiary?->cais_number,
            'approval_status' => $item->approval_status->value,
            'exclusion_reason' => $item->exclusion_reason,
            'lines' => $this->offlinePackage->lines($item),
            'transaction' => $transaction instanceof DistributionTransaction ? [
                'id' => $transaction->id,
                'number' => $transaction->transaction_number,
                'status' => $transaction->status->value,
                'status_label' => $transaction->status->label(),
                'evidence' => $transaction->evidence->map(fn (DistributionEvidence $row): array => [
                    'id' => $row->id,
                    'type' => $row->evidence_type->value,
                    'mime_type' => $row->mime_type,
                    'sha256_hash' => $row->sha256_hash,
                    'captured_at' => $row->captured_at?->toIso8601String(),
                    'preview_url' => route('user.distributions.transactions.evidence.show', [
                        'department' => $department,
                        'distributionBatch' => $batch,
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
