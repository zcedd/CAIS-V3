<?php

namespace App\Http\Controllers\User;

use App\Enums\RequestStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\ShowReceiptRequest;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Program;
use App\Models\User;
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
            'beneficiary:id,name,cais_number,beneficiable_type,beneficiable_id',
            'beneficiary.beneficiable' => function ($morphTo): void {
                $morphTo->morphWith([
                    Individual::class => ['address.city', 'beneficiaryIdentification.identification'],
                ]);
            },
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
        $individual = $assistance->beneficiary?->beneficiable instanceof Individual
            ? $assistance->beneficiary->beneficiable
            : null;
        $barangay = $individual?->address;
        $identification = $individual?->beneficiaryIdentification->first();
        $cashTotal = (int) collect($itemFulfillment['released'])
            ->where('kind', 'cash')
            ->sum('quantity');

        return Inertia::render('user/assistances/receipt', [
            'department' => $department->only(['id', 'name', 'slug']),
            'program' => $program->only(['id', 'name']),
            'receipt' => [
                'assistance_id' => $assistance->id,
                'cais_number' => $assistance->beneficiary?->cais_number ?? EmptyCell::VALUE,
                'beneficiary_name' => $assistance->beneficiary?->name ?? EmptyCell::VALUE,
                'beneficiary_address' => $barangay === null
                    ? null
                    : collect([$barangay->name, $barangay->city?->name])->filter()->implode(', '),
                'beneficiary_id_number' => $identification?->number,
                'program_name' => $program->name,
                'department_name' => $department->name,
                'cash_total' => $cashTotal,
                'released_by' => $this->releasedByName($assistance),
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

    private function releasedByName(Assistance $assistance): ?string
    {
        $userId = AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->whereHas(
                'requestSubStatus.requestStatus',
                fn ($query) => $query->where('code', RequestStatusCode::Delivered->value),
            )
            ->latest('id')
            ->value('recorded_by');

        if ($userId === null) {
            return null;
        }

        $name = User::query()->find($userId)?->name;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
