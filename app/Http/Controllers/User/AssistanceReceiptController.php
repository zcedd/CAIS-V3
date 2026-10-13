<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\ShowReceiptRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Services\User\AssistanceReceiptPage;
use Inertia\Inertia;
use Inertia\Response;

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
}
