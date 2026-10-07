<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Program\EndorseApprovalRequest;
use App\Http\Requests\User\Program\IndexRequest;
use App\Http\Requests\User\Program\ReviseApprovalRequest;
use App\Http\Requests\User\Program\ShowRequest;
use App\Http\Requests\User\Program\StoreRequest;
use App\Http\Requests\User\Program\SubmitApprovalRequest;
use App\Http\Requests\User\Program\UpdateRequest;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\User\AssistanceDocumentService;
use App\Services\User\AssistanceService;
use App\Services\User\ProgramApprovalService;
use App\Services\User\ProgramService;
use App\Services\User\StockLedgerService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function __construct(
        private ProgramService $programService,
        private AssistanceService $assistanceService,
        private AssistanceDocumentService $assistanceDocumentService,
        private StockLedgerService $stockLedgerService,
        private ProgramApprovalService $programApprovalService,
    ) {}

    /**
     * Display programs belonging to the authenticated user's department.
     */
    public function index(IndexRequest $request, Department $department): Response
    {
        $search = $request->search();
        $types = $request->types();
        $statuses = $request->statuses();
        $perPage = $request->perPage();

        $programs = $this->programService->paginateForDepartment(
            $department,
            $search,
            $types,
            $statuses,
            $perPage,
        );

        return Inertia::render('user/programs/index', [
            'programs' => $programs,
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
            'type' => $types,
            'status' => $statuses,
            'per_page' => $perPage,
            'funds' => Inertia::defer(
                fn () => $this->programService->departmentFundsForSelect($department),
            ),
            'items' => Inertia::defer(
                fn () => $this->programService->departmentItemsForSelect($department),
            ),
            'document_types' => $this->assistanceDocumentService->documentTypesForSelect(),
            'workflow_options' => $this->programService->workflowOptions($department),
        ]);
    }

    /**
     * Store a newly created program for the authenticated user's department.
     */
    public function store(StoreRequest $request, Department $department): RedirectResponse
    {
        $program = $this->programService->create($department, $request->validated());

        if ($program->isScheme() && $program->batches()->doesntExist()) {
            return redirect()
                ->route('user.programs.show', [
                    'department' => $department,
                    'program' => $program,
                ])
                ->with('success', 'Program created successfully.');
        }

        return redirect()
            ->back()
            ->with('success', 'Program created successfully.');
    }

    /**
     * Update a program for the authenticated user's department.
     */
    public function update(
        UpdateRequest $request,
        Department $department,
        Program $program,
    ): RedirectResponse {
        $this->programService->update($program, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Program updated successfully.');
    }

    public function submit(
        SubmitApprovalRequest $request,
        Department $department,
        Program $program,
    ): RedirectResponse {
        $this->transitionApproval($request->user(), $program, 'submit');

        return redirect()
            ->back()
            ->with('success', 'Program submitted for review.');
    }

    public function endorse(
        EndorseApprovalRequest $request,
        Department $department,
        Program $program,
    ): RedirectResponse {
        $this->transitionApproval($request->user(), $program, 'endorse');

        return redirect()
            ->back()
            ->with('success', 'Program endorsed for governor approval.');
    }

    public function revise(
        ReviseApprovalRequest $request,
        Department $department,
        Program $program,
    ): RedirectResponse {
        $this->transitionApproval($request->user(), $program, 'revise');

        return redirect()
            ->back()
            ->with('success', 'Program returned to draft.');
    }

    private function transitionApproval(?User $user, Program $program, string $action): void
    {
        if (! $user instanceof User) {
            abort(403);
        }

        match ($action) {
            'submit' => $this->programApprovalService->submit($user, $program),
            'endorse' => $this->programApprovalService->endorse($user, $program),
            'revise' => $this->programApprovalService->revise($user, $program),
            default => abort(404),
        };
    }

    /**
     * Display a single program for the authenticated user's department.
     */
    public function show(
        ShowRequest $request,
        Department $department,
        Program $program,
    ): Response {
        $sort = $request->sort();
        $direction = $request->direction();
        $perPage = $request->perPage();
        $search = $request->search();
        $statuses = $request->statuses();
        $modes = $request->modes();

        if ($program->isScheme()) {
            return Inertia::render('user/programs/scheme', [
                'program' => $this->programService->showOverviewPayload($program),
                'summary' => Inertia::defer(
                    fn () => $this->programService->summary($program),
                    'kpis',
                ),
                'status_breakdown' => Inertia::defer(
                    fn () => $this->programService->statusBreakdown($program),
                    'kpis',
                ),
                'batches' => $this->programService->schemeBatchesPayload($program),
                'department' => fn () => $department->only(['id', 'name', 'slug']),
                'program_edit' => Inertia::defer(
                    fn () => $this->programService->editRelationsPayload($program),
                    'edit',
                ),
                'funds' => Inertia::defer(
                    fn () => $this->programService->departmentFundsForSelect($department),
                    'edit',
                ),
                'items' => Inertia::defer(
                    fn () => $this->programService->departmentItemsForSelect($department),
                    'edit',
                ),
                'document_types' => $this->assistanceDocumentService->documentTypesForSelect(),
                'workflow_options' => $this->programService->workflowOptions($department),
            ]);
        }

        return Inertia::render('user/programs/show', [
            'program' => $this->programService->showOverviewPayload($program),
            'summary' => Inertia::defer(
                fn () => $this->programService->summary($program),
                'kpis',
            ),
            'status_breakdown' => Inertia::defer(
                fn () => $this->programService->statusBreakdown($program),
                'kpis',
            ),
            'program_funds' => Inertia::defer(
                fn () => $this->programService->programFundsForDisplay($program),
                'kpis',
            ),
            'program_covered_items' => Inertia::defer(
                fn () => $this->programService->programItemsForSelect($program),
                'kpis',
            ),
            'program_stock' => Inertia::defer(
                fn () => $this->stockLedgerService->programStockTable($program),
                'kpis',
            ),
            'department' => fn () => $department->only(['id', 'name', 'slug']),
            'program_edit' => Inertia::defer(
                fn () => $this->programService->editRelationsPayload($program),
                'edit',
            ),
            'funds' => Inertia::defer(
                fn () => $this->programService->departmentFundsForSelect($department),
                'edit',
            ),
            'items' => Inertia::defer(
                fn () => $this->programService->departmentItemsForSelect($department),
                'edit',
            ),
            'assistances' => Inertia::defer(
                fn () => $this->assistanceService->paginatedForProgram(
                    $program,
                    $sort,
                    $direction,
                    $perPage,
                    $search,
                    $statuses,
                    $modes,
                ),
                'table',
            ),
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
            'search' => $search,
            'status' => $statuses,
            'mode' => $modes,
            'mode_options' => Inertia::defer(
                fn () => $this->assistanceService->modeOptions($program),
                'table',
            ),
            'status_options' => Inertia::defer(
                fn () => $this->assistanceService->statusOptions($program),
                'table',
            ),
            'mode_of_request_options' => Inertia::defer(
                fn () => $this->assistanceService->modesOfRequestForSelect(),
                'table',
            ),
            'program_items' => Inertia::defer(
                fn () => $this->programService->programItemsForSelect($program),
                'table',
            ),
            'program_fields' => Inertia::defer(
                fn () => $this->programService->programFieldsForForms($program),
                'table',
            ),
            'request_sub_status_options' => Inertia::defer(
                fn () => $this->assistanceService->requestSubStatusesForSelect($program),
                'table',
            ),
            'staff_options' => Inertia::defer(
                fn () => $this->assistanceService->departmentStaffForSelect($program),
                'table',
            ),
            'transfer_program_options' => Inertia::defer(
                fn () => $this->programService->transferProgramsForSelect($department, $program),
                'table',
            ),
            'document_types' => $this->assistanceDocumentService->documentTypesForSelect(),
            'workflow_options' => $this->programService->workflowOptions($department),
        ]);
    }
}
