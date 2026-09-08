<?php

namespace App\Http\Controllers\User;

use App\Actions\User\BulkTransferProgramAssistance;
use App\Actions\User\BulkUpdateProgramAssistanceStatus;
use App\Actions\User\TransferProgramAssistance;
use App\Actions\User\UpdateProgramAssistance;
use App\Actions\User\UpdateProgramAssistanceStatus;
use App\Exports\User\ProgramAssistancesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\BulkTransferRequest;
use App\Http\Requests\User\Assistance\BulkUpdateStatusRequest;
use App\Http\Requests\User\Assistance\DestroyRequest;
use App\Http\Requests\User\Assistance\EditRequest;
use App\Http\Requests\User\Assistance\EligibilityPreviewRequest;
use App\Http\Requests\User\Assistance\ExportRequest;
use App\Http\Requests\User\Assistance\ShowRequest;
use App\Http\Requests\User\Assistance\StoreRequest;
use App\Http\Requests\User\Assistance\TransferRequest;
use App\Http\Requests\User\Assistance\UpdateRequest;
use App\Http\Requests\User\Assistance\UpdateStatusRequest;
use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\User\AssistanceDocumentService;
use App\Services\User\AssistanceItemFulfillmentService;
use App\Services\User\AssistanceService;
use App\Services\User\StockLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssistanceController extends Controller
{
    public function __construct(
        private AssistanceService $assistanceService,
        private AssistanceDocumentService $assistanceDocumentService,
        private AssistanceItemFulfillmentService $assistanceItemFulfillmentService,
        private StockLedgerService $stockLedgerService,
    ) {}

    /**
     * Store a newly created assistance record for the program.
     */
    public function store(
        StoreRequest $request,
        Department $department,
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
        EditRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
    ): JsonResponse {
        return response()->json([
            'data' => $this->assistanceService->editPayload($assistance),
        ]);
    }

    public function eligibility(
        EligibilityPreviewRequest $request,
        Department $department,
        Program $program,
    ): JsonResponse {
        $beneficiary = Beneficiary::query()->findOrFail($request->integer('beneficiary_id'));

        $itemDetails = collect($request->input('item_details', []))
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static fn (array $row): array => [
                'item_id' => (int) ($row['item_id'] ?? 0),
                'quantity' => (int) ($row['quantity'] ?? 0),
            ])
            ->all();

        $asOf = $request->filled('recorded_at')
            ? Carbon::parse($request->input('recorded_at'))
            : now();

        $exceptAssistanceId = $request->filled('except_assistance_id')
            ? $request->integer('except_assistance_id')
            : null;

        return response()->json([
            'data' => $this->assistanceService->eligibilityPreview(
                $program,
                $beneficiary,
                $itemDetails,
                $asOf,
                $exceptAssistanceId,
            ),
        ]);
    }

    /**
     * Update an assistance record for the program.
     */
    public function update(
        UpdateRequest $request,
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
        DestroyRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
    ): RedirectResponse {

        $this->assistanceService->ensureProgramIsOpen(
            $program,
            'This program is closed and assistances cannot be deleted.',
        );

        $user = $request->user();

        DB::transaction(function () use ($assistance, $user): void {
            if ($user instanceof User) {
                $this->stockLedgerService->restoreForAssistance($assistance, $user);
            }

            $assistance->delete();
        });

        return redirect()
            ->back()
            ->with('success', 'Assistance deleted successfully.');
    }

    /**
     * Transfer an assistance record to another program in the department.
     */
    public function transfer(
        TransferRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        TransferProgramAssistance $transferProgramAssistance,
    ): RedirectResponse {
        $targetProgram = $request->targetProgram();

        if ($targetProgram === null) {
            abort(422, 'Target program is required.');
        }

        $transferProgramAssistance($assistance, $targetProgram, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Assistance transferred successfully.');
    }

    /**
     * Transfer multiple assistance records to another program in the department.
     */
    public function bulkTransfer(
        BulkTransferRequest $request,
        Department $department,
        Program $program,
        BulkTransferProgramAssistance $bulkTransferProgramAssistance,
    ): RedirectResponse {
        $targetProgram = $request->targetProgram();

        if ($targetProgram === null) {
            abort(422, 'Target program is required.');
        }

        $transferredCount = $bulkTransferProgramAssistance(
            $request->assistances(),
            $targetProgram,
            $request->validated(),
        );

        return redirect()
            ->back()
            ->with('success', "{$transferredCount} assistance record(s) transferred successfully.");
    }

    /**
     * Update the request sub-status for multiple assistance records.
     */
    public function bulkUpdateStatus(
        BulkUpdateStatusRequest $request,
        Department $department,
        Program $program,
        BulkUpdateProgramAssistanceStatus $bulkUpdateProgramAssistanceStatus,
    ): RedirectResponse {
        $updatedCount = $bulkUpdateProgramAssistanceStatus(
            $request->assistances(),
            $request->validated(),
        );

        return redirect()
            ->back()
            ->with('success', "{$updatedCount} assistance record(s) updated successfully.");
    }

    /**
     * Update the request sub-status for an assistance record.
     */
    public function updateStatus(
        UpdateStatusRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        UpdateProgramAssistanceStatus $updateProgramAssistanceStatus,
    ): RedirectResponse {
        $updateProgramAssistanceStatus($assistance, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Assistance status updated successfully.');
    }

    /**
     * Export filtered assistance records for the selected program.
     */
    public function export(
        ExportRequest $request,
        Department $department,
        Program $program,
    ): BinaryFileResponse {
        $format = $request->exportFormat();
        $assistances = $this->assistanceService->exportRowsForProgram(
            $program,
            $request->sort(),
            $request->direction(),
            $request->search(),
            $request->statuses(),
            $request->modes(),
        );

        $filename = sprintf(
            'assistances-%s-%s.%s',
            $department->slug,
            now()->format('Ymd-His'),
            $format,
        );

        return Excel::download(
            new ProgramAssistancesExport($assistances),
            $filename,
            $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX,
        );
    }

    /**
     * Display the specified assistance profile.
     */
    public function show(
        ShowRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
    ): Response {
        $assistance->load([
            'beneficiary:id,name,cais_number,beneficiable_type,beneficiable_id',
            'modeOfRequest:id,name',
            'user:id,firstName,lastName',
            'program:id,name,department_id',
            'program.department:id,name,slug',
            'assistanceItem',
            'assistanceItem.item:id,name,kind,item_unit_measurement_id',
            'assistanceItem.item.unitMeasurement:id,name',
            'requestSubStatus' => function ($query): void {
                $query->with('requestStatus:id,name');
            },
            'currentRequestSubStatus:id,name,request_status_id',
            'currentRequestSubStatus.requestStatus:id,name',
        ]);

        $formatDate = static function ($value): ?string {
            if ($value === null) {
                return null;
            }

            return Carbon::parse($value)->toDateString();
        };

        $beneficiaryName = $assistance->beneficiary?->name ?? '—';
        $caisNumber = $assistance->beneficiary?->cais_number ?? '—';

        $statusHistory = $assistance->requestSubStatus
            ->sortBy(static fn ($subStatus) => $subStatus->pivot->recorded_at)
            ->values();

        $latestSubStatus = $statusHistory->last();

        $status = $latestSubStatus?->requestStatus?->name
            ?? $latestSubStatus?->name
            ?? $assistance->currentRequestSubStatus?->requestStatus?->name
            ?? $assistance->currentRequestSubStatus?->name
            ?? 'Unrequested';

        $itemFulfillment = $this->assistanceItemFulfillmentService->summarize(
            $assistance->assistanceItem,
        );

        return Inertia::render('user/assistances/show', [
            'department' => $department->only(['id', 'name', 'slug']),
            'program' => $program->only(['id', 'name']),
            'assistance' => [
                'id' => $assistance->id,
                'cais_number' => $caisNumber,
                'beneficiary_id' => $assistance->beneficiary_id,
                'beneficiary_name' => $beneficiaryName,
                'beneficiary_type' => $assistance->beneficiary
                    ? class_basename($assistance->beneficiary->beneficiable_type)
                    : null,
                'status' => $status,
                'current_sub_status' => $latestSubStatus?->name,
                'mode_of_request' => $assistance->modeOfRequest?->name ?? '—',
                'encoder_name' => $assistance->user_id === null
                    ? 'Public intake'
                    : (trim(($assistance->user?->firstName ?? '').' '.($assistance->user?->lastName ?? '')) ?: '—'),
                'date_requested' => $formatDate($assistance->date_requested),
                'date_verified' => $formatDate(
                    $statusHistory
                        ->first(static fn ($subStatus): bool => $subStatus->name === 'Verified')
                        ?->pivot
                        ?->recorded_at,
                ),
                'date_delivered' => $formatDate($assistance->date_delivered),
                'date_denied' => $formatDate(
                    $statusHistory
                        ->first(static fn ($subStatus): bool => $subStatus->requestStatus?->name === 'Denied')
                        ?->pivot
                        ?->recorded_at,
                ),
                'remark' => $assistance->remark,
                'requested_items' => $itemFulfillment['requested'],
                'released_items' => $itemFulfillment['released'],
                'item_variance' => $itemFulfillment['variance'],
                'status_history' => $statusHistory
                    ->map(static fn ($subStatus): array => [
                        'id' => (int) $subStatus->pivot->id,
                        'name' => $subStatus->name,
                        'parent_status' => $subStatus->requestStatus?->name,
                        'remark' => $subStatus->pivot->remark,
                        'recorded_at' => Carbon::parse($subStatus->pivot->recorded_at)->toDateTimeString(),
                    ])
                    ->values()
                    ->all(),
            ],
            'documents' => $this->assistanceDocumentService->profilePayload($assistance),
            'document_types' => $this->assistanceDocumentService->documentTypesForSelect(),
        ]);
    }
}
