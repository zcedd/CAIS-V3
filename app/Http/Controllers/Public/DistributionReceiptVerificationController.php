<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DistributionAcknowledgmentReceipt;
use App\Services\Distribution\DistributionReceiptService;
use Inertia\Inertia;
use Inertia\Response;

class DistributionReceiptVerificationController extends Controller
{
    public function __invoke(string $token, DistributionReceiptService $receipts): Response
    {
        $receipt = DistributionAcknowledgmentReceipt::query()
            ->where('verification_token', $token)
            ->firstOrFail();

        return Inertia::render('public/distributions/verify', [
            'receipt' => $receipts->publicView($receipt->snapshot ?? [], $receipt->status),
        ]);
    }
}
