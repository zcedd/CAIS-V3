<?php

namespace App\Http\Controllers\Executive;

use App\Actions\Disbursement\DecideDisbursementBatch;
use App\Actions\Disbursement\DecideDisbursementBatchItem;
use App\Actions\Disbursement\FinalizeDisbursementBatch;
use App\Enums\DisbursementApprovalTaskStatus;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Disbursement\BulkDecideDisbursementBatchItemsRequest;
use App\Http\Requests\User\Disbursement\DecideDisbursementBatchItemRequest;
use App\Http\Requests\User\Disbursement\DecideDisbursementBatchRequest;
use App\Models\DisbursementApprovalTask;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchItem;
use App\Services\Disbursement\DisbursementAuthorizer;
use App\Services\Disbursement\DisbursementBatchDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DisbursementApprovalController extends Controller
{
    public function index(Request $request, DisbursementAuthorizer $authorizer): Response
    {
        abort_unless($request->user()?->can(PermissionName::DisbursementApprove->value), 403);

        $tasks = DisbursementApprovalTask::query()
            ->with(['stage', 'batch.program:id,name', 'batch.department:id,name,slug'])
            ->where('status', DisbursementApprovalTaskStatus::Pending)
            ->whereHas('batch', fn ($query) => $query->whereColumn('disbursement_batches.revision', 'disbursement_approval_tasks.revision'))
            ->latest('id')
            ->get()
            ->filter(function (DisbursementApprovalTask $task) use ($request, $authorizer): bool {
                return $task->stage !== null && $authorizer->isAssigned($request->user(), $task->stage);
            })
            ->map(fn (DisbursementApprovalTask $task): array => [
                'task_id' => $task->id,
                'stage' => $task->stage?->name,
                'batch_id' => $task->batch?->id,
                'batch_code' => $task->batch?->batch_code,
                'title' => $task->batch?->title,
                'program' => $task->batch?->program?->name,
                'department' => $task->batch?->department?->name,
                'department_slug' => $task->batch?->department?->slug,
                'approval_mode' => $task->batch?->approval_mode?->value,
                'scheduled_at' => $task->batch?->scheduled_at?->toIso8601String(),
                'beneficiary_count' => $task->batch?->beneficiary_count,
                'proposed_cash_total' => $task->batch?->proposed_cash_total,
            ])
            ->values();

        return Inertia::render('executive/disbursements/index', [
            'tasks' => $tasks,
        ]);
    }

    public function show(DisbursementBatch $disbursementBatch, DisbursementBatchDetails $details): Response
    {
        Gate::authorize('approve', $disbursementBatch);

        return Inertia::render('executive/disbursements/show', [
            'batch' => $details->payload($disbursementBatch),
        ]);
    }

    public function decide(DecideDisbursementBatchRequest $request, DisbursementBatch $disbursementBatch, DecideDisbursementBatch $decide): RedirectResponse
    {
        $decide($disbursementBatch, $request->user(), $request->validated());

        return back()->with('success', 'Decision recorded.');
    }

    public function decideItem(
        DecideDisbursementBatchItemRequest $request,
        DisbursementBatch $disbursementBatch,
        DisbursementBatchItem $item,
        DecideDisbursementBatchItem $decide,
    ): RedirectResponse {
        abort_unless((int) $item->disbursement_batch_id === (int) $disbursementBatch->id, 404);
        $decide($disbursementBatch, $item, $request->user(), $request->validated());

        return back()->with('success', 'Beneficiary decision recorded.');
    }

    public function bulkDecide(
        BulkDecideDisbursementBatchItemsRequest $request,
        DisbursementBatch $disbursementBatch,
        DecideDisbursementBatchItem $decide,
    ): RedirectResponse {
        $decide->bulk(
            $disbursementBatch,
            $request->user(),
            $request->validated('item_ids'),
            $request->validated('decision'),
            $request->validated('remarks'),
        );

        return back()->with('success', 'Beneficiary decisions recorded.');
    }

    public function finalize(Request $request, DisbursementBatch $disbursementBatch, FinalizeDisbursementBatch $finalize): RedirectResponse
    {
        Gate::authorize('finalize', $disbursementBatch);
        $finalize($disbursementBatch, $request->user());

        return back()->with('success', 'Disbursement review finalized.');
    }
}
