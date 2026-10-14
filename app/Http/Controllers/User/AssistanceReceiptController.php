<?php

namespace App\Http\Controllers\User;

use App\Enums\DistributionEvidenceType;
use App\Enums\DistributionTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\ShowReceiptRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\DistributionEvidence;
use App\Models\DistributionTransaction;
use App\Models\Program;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionEvidenceService;
use App\Services\User\AssistanceReceiptPage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistanceReceiptController extends Controller
{
    /**
     * Display a printable acknowledgment receipt for the assistance.
     */
    public function show(
        ShowReceiptRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        AssistanceReceiptPage $receiptPage,
    ): Response {
        return Inertia::render(
            'user/assistances/receipt',
            $receiptPage->forAssistance($department, $program, $assistance),
        );
    }

    /**
     * Stream the signature or delivery photo that belongs on this receipt.
     */
    public function evidence(
        ShowReceiptRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        DistributionEvidence $evidence,
        DistributionEvidenceService $evidenceFiles,
        DistributionAudit $audit,
    ): StreamedResponse {
        abort_unless((int) $program->department_id === (int) $department->id, 404);
        abort_unless((int) $assistance->program_id === (int) $program->id, 404);

        $transaction = DistributionTransaction::query()
            ->where('status', DistributionTransactionStatus::Finalized)
            ->whereHas('batchItem', fn ($query) => $query->where('assistance_id', $assistance->id))
            ->latest('id')
            ->first();

        abort_unless(
            $transaction instanceof DistributionTransaction
            && (int) $evidence->distribution_transaction_id === (int) $transaction->id
            && in_array($evidence->evidence_type, [DistributionEvidenceType::Signature, DistributionEvidenceType::LivePhoto], true),
            404,
        );

        $actor = $request->user();
        $transaction->loadMissing('batch');

        if ($actor instanceof User && $transaction->batch !== null) {
            $audit->record($actor, $transaction->batch, 'evidence_viewed', [
                'evidence_id' => $evidence->id,
                'evidence_type' => $evidence->evidence_type->value,
                'distribution_transaction_id' => $transaction->id,
                'source' => 'acknowledgment_receipt',
            ]);
        }

        return $evidenceFiles->response($evidence);
    }
}
