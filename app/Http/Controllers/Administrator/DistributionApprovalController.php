<?php

namespace App\Http\Controllers\Administrator;

use App\Actions\Distribution\DecideDistributionBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Distribution\DecideDistributionBatchRequest;
use App\Models\DistributionBatch;
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

        return Inertia::render('executive/distributions/index', [
            'tasks' => $queue->pendingFor($user, 'administrator.distributions.show'),
            'inbox_url' => route('administrator.distributions.index'),
        ]);
    }

    public function show(DistributionBatch $distributionBatch, DistributionBatchDetails $details): Response
    {
        Gate::authorize('approve', $distributionBatch);

        return Inertia::render('executive/distributions/show', [
            'batch' => $details->payload($distributionBatch),
            'actions' => [
                'queue_url' => route('administrator.distributions.index'),
                'show_url' => route('administrator.distributions.show', $distributionBatch),
                'decide_url' => route('administrator.distributions.decide', $distributionBatch),
                'bulk_decide_url' => null,
                'finalize_url' => null,
                'item_decide_urls' => [],
                'can_finalize' => false,
                'can_decide_items' => false,
            ],
        ]);
    }

    public function decide(DecideDistributionBatchRequest $request, DistributionBatch $distributionBatch, DecideDistributionBatch $decide): RedirectResponse
    {
        $decide($distributionBatch, $request->user(), $request->validated());

        return back()->with('success', 'Decision recorded.');
    }
}
