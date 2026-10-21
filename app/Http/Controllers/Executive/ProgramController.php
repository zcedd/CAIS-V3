<?php

namespace App\Http\Controllers\Executive;

use App\Enums\ProgramApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Executive\Program\ApproveRequest;
use App\Http\Requests\Executive\Program\IndexRequest;
use App\Http\Requests\Executive\Program\ReturnRequest;
use App\Http\Requests\Executive\Program\ShowRequest;
use App\Models\Program;
use App\Models\User;
use App\Services\User\ProgramApprovalService;
use App\Services\User\ProgramService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function __construct(
        private ProgramApprovalService $approvals,
        private ProgramService $programService,
    ) {}

    public function index(IndexRequest $request): Response
    {
        $programs = $this->approvals->withDecisionUrls(
            $this->approvals->paginateForGovernor(
                $request->search(),
                $request->types(),
                $request->statuses(),
                $request->approvals(),
                $request->departments(),
                $request->perPage(),
            ),
            'executive.programs.show',
            'executive.programs.approve',
            'executive.programs.return',
        );

        return Inertia::render('executive/programs/index', [
            'programs' => $programs,
            'program_metrics' => $this->programService->cardMetrics(collect($programs->items())),
            'search' => $request->search(),
            'type' => $request->types(),
            'status' => $request->statuses(),
            'approval' => $request->approvals(),
            'department' => array_map(strval(...), $request->departments()),
            'departments' => $this->approvals->departmentOptions(),
            'per_page' => $request->perPage(),
            'awaiting_count' => $this->approvals->awaitingGovernorCount(),
            'approval_counts' => $this->approvals->approvalCounts(
                $request->search(),
                $request->types(),
                $request->statuses(),
                $request->departments(),
            ),
            'review_queue_count' => $this->approvals->awaitingGovernorCount(),
            'review' => [
                'mode' => 'approve',
                'audience' => 'Executive',
                'index_url' => route('executive.programs.index'),
                'awaiting_status' => ProgramApprovalStatus::AwaitingGovernor->value,
                'positive_action' => 'approve',
                'positive_label' => 'Approve',
            ],
        ]);
    }

    public function show(ShowRequest $request, Program $program): Response
    {
        return Inertia::render('executive/programs/show', [
            'program' => [
                ...$this->approvals->showPayload($program),
                ...$this->programService->executiveReviewPayload($program),
            ],
            'summary' => Inertia::defer(
                fn () => $this->programService->summary($program),
                'overview',
            ),
            'status_breakdown' => Inertia::defer(
                fn () => $this->programService->statusBreakdown($program),
                'overview',
            ),
            'program_metrics' => Inertia::defer(
                fn () => $this->programService->cardMetrics(collect([$program]))[$program->id] ?? null,
                'overview',
            ),
            'assistances' => Inertia::defer(
                fn () => $this->programService->reviewAssistances($program, $request->perPage()),
                'assistances',
            ),
            'review' => [
                'mode' => 'approve',
                'audience' => 'Executive',
                'index_url' => route('executive.programs.index'),
                'show_url' => route('executive.programs.show', $program),
                'positive_url' => route('executive.programs.approve', $program),
                'return_url' => route('executive.programs.return', $program),
                'awaiting_status' => ProgramApprovalStatus::AwaitingGovernor->value,
                'positive_action' => 'approve',
                'positive_label' => 'Approve',
            ],
        ]);
    }

    public function approve(ApproveRequest $request, Program $program): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->approvals->approve($program, $actor, $request->remark());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Program approved.',
        ]);

        return redirect()->back();
    }

    public function returnToDepartment(ReturnRequest $request, Program $program): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->approvals->returnToDepartment($program, $actor, $request->remark());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Program returned to the department.',
        ]);

        return redirect()->back();
    }
}
