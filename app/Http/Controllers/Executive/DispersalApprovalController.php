<?php

namespace App\Http\Controllers\Executive;

use App\Actions\Dispersal\DecideDispersalBatch;
use App\Actions\Dispersal\DecideDispersalBatchItem;
use App\Actions\Dispersal\FinalizeDispersalBatch;
use App\Enums\DispersalApprovalTaskStatus;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Dispersal\BulkDecideDispersalBatchItemsRequest;
use App\Http\Requests\User\Dispersal\DecideDispersalBatchItemRequest;
use App\Http\Requests\User\Dispersal\DecideDispersalBatchRequest;
use App\Models\DispersalApprovalTask;
use App\Models\DispersalBatch;
use App\Models\DispersalBatchItem;
use App\Services\Dispersal\DispersalAuthorizer;
use App\Services\Dispersal\DispersalBatchDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DispersalApprovalController extends Controller
{
    public function index(Request $request, DispersalAuthorizer $authorizer): Response
    {
        abort_unless($request->user()?->can(PermissionName::DispersalApprove->value), 403);

        $tasks = DispersalApprovalTask::query()
            ->with(['stage', 'batch.program:id,name', 'batch.department:id,name,slug'])
            ->where('status', DispersalApprovalTaskStatus::Pending)
            ->whereHas('batch', fn ($query) => $query->whereColumn('dispersal_batches.revision', 'dispersal_approval_tasks.revision'))
            ->latest('id')
            ->get()
            ->filter(function (DispersalApprovalTask $task) use ($request, $authorizer): bool {
                return $task->stage !== null && $authorizer->isAssigned($request->user(), $task->stage);
            })
            ->map(fn (DispersalApprovalTask $task): array => [
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

        return Inertia::render('executive/dispersals/index', [
            'tasks' => $tasks,
        ]);
    }

    public function show(DispersalBatch $dispersalBatch, DispersalBatchDetails $details): Response
    {
        Gate::authorize('approve', $dispersalBatch);

        return Inertia::render('executive/dispersals/show', [
            'batch' => $details->payload($dispersalBatch),
        ]);
    }

    public function decide(DecideDispersalBatchRequest $request, DispersalBatch $dispersalBatch, DecideDispersalBatch $decide): RedirectResponse
    {
        $decide($dispersalBatch, $request->user(), $request->validated());

        return back()->with('success', 'Decision recorded.');
    }

    public function decideItem(
        DecideDispersalBatchItemRequest $request,
        DispersalBatch $dispersalBatch,
        DispersalBatchItem $item,
        DecideDispersalBatchItem $decide,
    ): RedirectResponse {
        abort_unless((int) $item->dispersal_batch_id === (int) $dispersalBatch->id, 404);
        $decide($dispersalBatch, $item, $request->user(), $request->validated());

        return back()->with('success', 'Beneficiary decision recorded.');
    }

    public function bulkDecide(
        BulkDecideDispersalBatchItemsRequest $request,
        DispersalBatch $dispersalBatch,
        DecideDispersalBatchItem $decide,
    ): RedirectResponse {
        $decide->bulk(
            $dispersalBatch,
            $request->user(),
            $request->validated('item_ids'),
            $request->validated('decision'),
            $request->validated('remarks'),
        );

        return back()->with('success', 'Beneficiary decisions recorded.');
    }

    public function finalize(Request $request, DispersalBatch $dispersalBatch, FinalizeDispersalBatch $finalize): RedirectResponse
    {
        Gate::authorize('finalize', $dispersalBatch);
        $finalize($dispersalBatch, $request->user());

        return back()->with('success', 'Dispersal review finalized.');
    }
}
