<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\DistributionReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\DistributionAcknowledgmentReceipt;
use App\Models\DistributionBatch;
use App\Models\DistributionTransaction;
use App\Services\Distribution\DistributionReceiptService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DistributionReceiptController extends Controller
{
    public function show(
        DistributionBatch $distributionBatch,
        DistributionTransaction $transaction,
        DistributionReceiptService $receipts,
    ): Response {
        abort_unless((int) $transaction->distribution_batch_id === (int) $distributionBatch->id, 404);
        Gate::authorize('view', $distributionBatch);

        $receipt = $transaction->receipts()->where('status', DistributionReceiptStatus::Issued)->latest('id')->first()
            ?? $transaction->receipts()->latest('id')->first();

        abort_unless($receipt instanceof DistributionAcknowledgmentReceipt, 404);

        return response($receipts->pdf($receipt), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipt->receipt_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
