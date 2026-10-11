<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DisbursementAcknowledgmentReceipt;
use App\Services\Disbursement\DisbursementReceiptService;
use Inertia\Inertia;
use Inertia\Response;

class DisbursementReceiptVerificationController extends Controller
{
    public function __invoke(string $token, DisbursementReceiptService $receipts): Response
    {
        $receipt = DisbursementAcknowledgmentReceipt::query()
            ->where('verification_token', $token)
            ->firstOrFail();

        return Inertia::render('public/disbursements/verify', [
            'receipt' => $receipts->publicView($receipt->snapshot ?? [], $receipt->status),
        ]);
    }
}
