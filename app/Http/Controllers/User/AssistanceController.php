<?php

namespace App\Http\Controllers\User;

use App\Actions\User\UpdateProgramAssistance;
use App\Actions\User\UpdateProgramAssistanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\StoreRequest;
use App\Http\Requests\User\EditAssistanceRequest;
use App\Http\Requests\User\UpdateProgramAssistanceRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Services\User\AssistanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AssistanceController extends Controller
{
    public function __construct(
        private AssistanceService $assistanceService,
    ) {}

    /**
     * Store a newly created assistance record for the program.
     */
    public function store(
        StoreRequest $request,
        Program $program,
    ): RedirectResponse {
        $this->assistanceService->create($program, $request->user(), $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Assistance created successfully.');
    }

    /**
     * Return assistance data for editing in the program table drawer.
     */
    public function edit(
        EditAssistanceRequest $request,
        Assistance $assistance,
    ): JsonResponse {

        return response()->json([
            'data' => $this->assistanceService->editPayload($assistance),
        ]);
    }

    /**
     * Update an assistance record for the program.
     */
    public function update(
        UpdateProgramAssistanceRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        UpdateProgramAssistance $updateProgramAssistance,
    ): RedirectResponse {
        $this->assistanceService->ensureProgramIsOpen(
            $program,
            'This program is closed and cannot be updated.',
        );

        $updateProgramAssistance($assistance, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Assistance updated successfully.');
    }

    /**
     * Remove the specified assistance from the program.
     */
    public function destroy(
        DestroyAssistanceRequest $request,
        Assistance $assistance,
    ): RedirectResponse {

        $this->assistanceService->ensureProgramIsOpen(
            $program,
            'This program is closed and assistances cannot be deleted.',
        );

        $assistance->delete();

        return redirect()
            ->back()
            ->with('success', 'Assistance deleted successfully.');
    }

    /**
     * Update the request sub-status for an assistance record.
     */
    public function updateStatus(
        UpdateAssistanceStatusRequest $request,
        Assistance $assistance,
        UpdateProgramAssistanceStatus $updateProgramAssistanceStatus,
    ): RedirectResponse {
        $user = $request->user();

        $updateProgramAssistanceStatus($assistance, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Assistance status updated successfully.');
    }

    /**
     * Display the specified assistance profile.
     */
    public function show(
        ShowAssistanceRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
    ): Response {
        $assistance->load([
            'beneficiary:id,name,cais_number,beneficiable_type,beneficiable_id',
            'modeOfRequest:id,name',
            'program:id,name,department_id',
            'program.department:id,name,slug',
            'assistanceItem:id,assistance_id,item_id,quantity,specification,is_received',
            'assistanceItem.item:id,name,item_unit_measurement_id',
            'assistanceItem.item.unitMeasurement:id,name',
            'requestSubStatus' => function ($query): void {
                $query->with('requestStatus:id,name');
            },
        ]);

        $formatDate = static function ($value): ?string {
            if ($value === null) {
                return null;
            }

            return Carbon::parse($value)->toDateString();
        };

        $beneficiaryName = $assistance->beneficiary?->name ?? '—';
        $caisNumber = $assistance->beneficiary?->cais_number ?? '—';

        return Inertia::render('user/assistances/show', [
            'department' => $department->only(['id', 'name', 'slug']),
            'program' => $program->only(['id', 'name']),
            'assistance' => [
                'id' => $assistance->id,
                'cais_number' => $caisNumber,
                'beneficiary_name' => $beneficiaryName,
                'beneficiary_type' => class_basename($assistance->beneficiary->beneficiable),
                'mode_of_request' => $assistance->modeOfRequest?->name ?? '—',
                'date_requested' => $formatDate($assistance->date_requested),
                'date_verified' => $formatDate($assistance->date_verified),
                'date_delivered' => $formatDate($assistance->date_delivered),
                'date_denied' => $formatDate($assistance->date_denied),
                'remark' => $assistance->remark,
                'items' => $assistance->assistanceItem
                    ->map(static fn($assistanceItem): array => [
                        'name' => $assistanceItem->item?->name ?? '—',
                        'quantity' => $assistanceItem->quantity,
                        'unit' => $assistanceItem->item?->unitMeasurement?->name,
                        'specification' => $assistanceItem->specification,
                        'is_received' => (bool) $assistanceItem->is_received,
                    ])
                    ->values()
                    ->all(),
                'status_history' => $assistance->requestSubStatus
                    ->sortBy(static fn($subStatus) => $subStatus->pivot->recorded_at)
                    ->map(static fn($subStatus): array => [
                        'id' => (int) $subStatus->pivot->id,
                        'name' => $subStatus->name,
                        'parent_status' => $subStatus->requestStatus?->name,
                        'remark' => $subStatus->pivot->remark,
                        'recorded_at' => Carbon::parse($subStatus->pivot->recorded_at)->toDateTimeString(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
