<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\SyncOfflineRelease;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\SyncOfflineAbsenceRequest;
use App\Http\Requests\Api\Mobile\SyncOfflineReleaseRequest;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class DistributionSyncController extends Controller
{
    public function release(
        SyncOfflineReleaseRequest $request,
        DistributionBatch $distributionBatch,
        DistributionBatchItem $item,
        SyncOfflineRelease $sync,
    ): JsonResponse {
        $this->assertItem($distributionBatch, $item);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json($sync->release(
            $distributionBatch,
            $item,
            $user,
            $request->validated(),
            $request->file('live_photo') instanceof UploadedFile ? $request->file('live_photo') : null,
            $request->file('signature') instanceof UploadedFile ? $request->file('signature') : null,
        ));
    }

    public function absent(
        SyncOfflineAbsenceRequest $request,
        DistributionBatch $distributionBatch,
        DistributionBatchItem $item,
        SyncOfflineRelease $sync,
    ): JsonResponse {
        $this->assertItem($distributionBatch, $item);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json($sync->absent(
            $distributionBatch,
            $item,
            $user,
            $request->validated(),
        ));
    }

    private function assertItem(DistributionBatch $batch, DistributionBatchItem $item): void
    {
        abort_unless((int) $item->distribution_batch_id === (int) $batch->id, 404);
    }
}
