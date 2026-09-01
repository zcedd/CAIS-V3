<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\ShowReceiptRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Support\QrCodeSvg;
use Illuminate\Support\Carbon;
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
    ): Response {
        $assistance->load([
            'beneficiary:id,name,cais_number,beneficiable_type',
            'program:id,name,department_id',
            'program.department:id,name,slug',
            'assistanceItem:id,assistance_id,item_id,quantity,specification,is_received',
            'assistanceItem.item:id,name,item_unit_measurement_id',
            'assistanceItem.item.unitMeasurement:id,name',
        ]);

        $profileUrl = route('user.assistances.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]);

        $items = $assistance->assistanceItem
            ->map(static fn ($assistanceItem): array => [
                'name' => $assistanceItem->item?->name ?? '—',
                'quantity' => $assistanceItem->quantity,
                'unit' => $assistanceItem->item?->unitMeasurement?->name,
                'specification' => $assistanceItem->specification,
                'is_received' => (bool) $assistanceItem->is_received,
            ])
            ->values();

        return Inertia::render('user/assistances/receipt', [
            'department' => $department->only(['id', 'name', 'slug']),
            'program' => $program->only(['id', 'name']),
            'receipt' => [
                'assistance_id' => $assistance->id,
                'cais_number' => $assistance->beneficiary?->cais_number ?? '—',
                'beneficiary_name' => $assistance->beneficiary?->name ?? '—',
                'date_requested' => $assistance->date_requested
                    ? Carbon::parse($assistance->date_requested)->toDateString()
                    : null,
                'date_delivered' => $assistance->date_delivered
                    ? Carbon::parse($assistance->date_delivered)->toDateString()
                    : null,
                'printed_at' => now()->toDateTimeString(),
                'profile_url' => $profileUrl,
                'qr_svg' => QrCodeSvg::fromString($profileUrl),
                'requested_items' => $items->all(),
                'released_items' => $items
                    ->where('is_received', true)
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
