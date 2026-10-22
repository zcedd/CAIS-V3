<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\DistributionBatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\MobileDistributionBatchResource;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Mobile\DistributionOfflinePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DistributionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->department_id === null) {
            return MobileDistributionBatchResource::collection(collect());
        }

        $batches = DistributionBatch::query()
            ->with('program:id,name')
            ->where('department_id', $user->department_id)
            ->whereIn('status', [
                DistributionBatchStatus::Scheduled,
                DistributionBatchStatus::InProgress,
            ])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (DistributionBatch $batch): bool => $user->can('execute', $batch))
            ->values();

        return MobileDistributionBatchResource::collection($batches);
    }

    public function package(
        Request $request,
        DistributionBatch $distributionBatch,
        DistributionOfflinePackage $package,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::authorize('execute', $distributionBatch);

        return response()->json($package->build($distributionBatch, $user));
    }
}
