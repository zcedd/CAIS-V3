<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\ShowReceiptRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Services\User\AssistanceItemFulfillmentService;
use App\Support\EmptyCell;
use App\Support\QrCodeSvg;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AssistanceReceiptController extends Controller
{
    public function __construct(
        private AssistanceItemFulfillmentService $assistanceItemFulfillmentService,
    ) {}

    /**
     * Display a printable acknowledgment receipt for the assistance.
     */
    public function show(
        ShowReceiptRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
    ): Response {
        $assistance->load([
            'beneficiary:id,name,cais_number,beneficiable_type',
            'program:id,name,department_id',
            'program.department:id,name,slug',
            'assistanceItem',
            'assistanceItem.item:id,name,kind,item_unit_measurement_id',
            'assistanceItem.item.unitMeasurement:id,name',
        ]);

        $profileUrl = route('user.assistances.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]);

        $itemFulfillment = $this->assistanceItemFulfillmentService->summarize(
            $assistance->assistanceItem,
        );

        return Inertia::render('user/assistances/receipt', [
            'department' => $department->only(['id', 'name', 'slug']),
            'program' => $program->only(['id', 'name']),
            'receipt' => [
                'assistance_id' => $assistance->id,
                'cais_number' => $assistance->beneficiary?->cais_number ?? EmptyCell::VALUE,
                'beneficiary_name' => $assistance->beneficiary?->name ?? EmptyCell::VALUE,
                'date_requested' => $assistance->date_requested
                    ? Carbon::parse($assistance->date_requested)->toDateString()
                    : null,
                'date_delivered' => $assistance->date_delivered
                    ? Carbon::parse($assistance->date_delivered)->toDateString()
                    : null,
                'printed_at' => now()->toDateTimeString(),
                'profile_url' => $profileUrl,
                'qr_svg' => QrCodeSvg::fromString($profileUrl),
                'requested_items' => $itemFulfillment['requested'],
                'released_items' => $itemFulfillment['released'],
                'item_variance' => $itemFulfillment['variance'],
            ],
        ]);
    }
}
