<?php

namespace App\Http\Controllers\Executive;

use App\Actions\Distribution\DecideDistributionBatch;
use App\Actions\Distribution\DecideDistributionBatchItem;
use App\Actions\Distribution\FinalizeDistributionBatch;
use App\Enums\DistributionApprovalTaskStatus;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Distribution\BulkDecideDistributionBatchItemsRequest;
use App\Http\Requests\User\Distribution\DecideDistributionBatchItemRequest;
use App\Http\Requests\User\Distribution\DecideDistributionBatchRequest;
use App\Models\DistributionApprovalTask;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Services\Distribution\DistributionAuthorizer;
use App\Services\Distribution\DistributionBatchDetails;
use App\Services\Distribution\DistributionReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DistributionApprovalController extends Controller
{
    public function index(Request $request, DistributionAuthorizer $authorizer, DistributionReconciliation $reconciliation): Response
    {
        abort_unless($request->user()?->can(PermissionName::DistributionApprove->value), 403);

        $tasks = DistributionApprovalTask::query()
            ->with([
                'stage',
                'batch.program:id,name',
                'batch.department:id,name,slug',
                'batch.items.lines.item.unitMeasurement',
            ])
            ->where('status', DistributionApprovalTaskStatus::Pending)
            ->whereHas('batch', fn ($query) => $query->whereColumn('distribution_batches.revision', 'distribution_approval_tasks.revision'))
            ->latest('id')
            ->get()
            ->filter(function (DistributionApprovalTask $task) use ($request, $authorizer): bool {
                return $task->stage !== null && $authorizer->isAssigned($request->user(), $task->stage);
            })
            ->map(fn (DistributionApprovalTask $task): array => [
                'task_id' => $task->id,
                'stage' => $task->stage?->name,
                'batch_id' => $task->batch?->id,
                'batch_code' => $task->batch?->batch_code,
                'title' => $task->batch?->title,
                'program' => $task->batch?->program?->name,
                'department' => $task->batch?->department?->name,
                'department_slug' => $task->batch?->department?->slug,
                'scheduled_at' => $task->batch?->scheduled_at?->toIso8601String(),
                'beneficiary_count' => $task->batch?->beneficiary_count,
                'proposed_cash_total' => $task->batch?->proposed_cash_total,
                'offer' => $task->batch === null ? null : $reconciliation->offer($task->batch),
            ])
            ->values();

        return Inertia::render('executive/distributions/index', [
            'tasks' => $tasks,
        ]);
    }

    public function show(DistributionBatch $distributionBatch, DistributionBatchDetails $details): Response
    {
        Gate::authorize('approve', $distributionBatch);

        return Inertia::render('executive/distributions/show', [
            'batch' => $details->payload($distributionBatch),
        ]);
    }

    public function decide(DecideDistributionBatchRequest $request, DistributionBatch $distributionBatch, DecideDistributionBatch $decide): RedirectResponse
    {
        $decide($distributionBatch, $request->user(), $request->validated());

        return back()->with('success', 'Decision recorded.');
    }

    public function decideItem(
        DecideDistributionBatchItemRequest $request,
        DistributionBatch $distributionBatch,
        DistributionBatchItem $item,
        DecideDistributionBatchItem $decide,
    ): RedirectResponse {
        abort_unless((int) $item->distribution_batch_id === (int) $distributionBatch->id, 404);
        $decide($distributionBatch, $item, $request->user(), $request->validated());

        return back()->with('success', 'Beneficiary decision recorded.');
    }

    public function bulkDecide(
        BulkDecideDistributionBatchItemsRequest $request,
        DistributionBatch $distributionBatch,
        DecideDistributionBatchItem $decide,
    ): RedirectResponse {
        $decide->bulk(
            $distributionBatch,
            $request->user(),
            $request->validated('item_ids'),
            $request->validated('decision'),
            $request->validated('remarks'),
        );

        return back()->with('success', 'Beneficiary decisions recorded.');
    }

    public function finalize(Request $request, DistributionBatch $distributionBatch, FinalizeDistributionBatch $finalize): RedirectResponse
    {
        Gate::authorize('finalize', $distributionBatch);
        $finalize($distributionBatch, $request->user());

        return back()->with('success', 'Distribution review finalized.');
    }
}
