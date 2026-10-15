<?php

namespace App\Http\Controllers\Executive;

use App\Actions\Distribution\DecideDistributionBatch;
use App\Actions\Distribution\DecideDistributionBatchItem;
use App\Actions\Distribution\FinalizeDistributionBatch;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Distribution\BulkDecideDistributionBatchItemsRequest;
use App\Http\Requests\User\Distribution\DecideDistributionBatchItemRequest;
use App\Http\Requests\User\Distribution\DecideDistributionBatchRequest;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\User;
use App\Services\Distribution\DistributionApprovalQueue;
use App\Services\Distribution\DistributionBatchDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DistributionApprovalController extends Controller
{
    public function index(Request $request, DistributionApprovalQueue $queue): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        abort_unless($user->can(PermissionName::DistributionApprove->value), 403);

        return Inertia::render('executive/distributions/index', [
            'tasks' => $queue->pendingFor($user, 'executive.distributions.show'),
            'inbox_url' => route('executive.distributions.index'),
        ]);
    }

    public function show(DistributionBatch $distributionBatch, DistributionBatchDetails $details): Response
    {
        Gate::authorize('approve', $distributionBatch);
        $distributionBatch->loadMissing('items');
        $itemDecideUrls = [];

        foreach ($distributionBatch->items as $item) {
            $itemDecideUrls[(string) $item->id] = route('executive.distributions.items.decide', [$distributionBatch, $item]);
        }

        return Inertia::render('executive/distributions/show', [
            'batch' => $details->payload($distributionBatch),
            'actions' => [
                'queue_url' => route('executive.distributions.index'),
                'show_url' => route('executive.distributions.show', $distributionBatch),
                'decide_url' => route('executive.distributions.decide', $distributionBatch),
                'bulk_decide_url' => route('executive.distributions.items.bulk-decide', $distributionBatch),
                'finalize_url' => route('executive.distributions.finalize', $distributionBatch),
                'item_decide_urls' => $itemDecideUrls,
                'can_finalize' => true,
                'can_decide_items' => true,
            ],
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
