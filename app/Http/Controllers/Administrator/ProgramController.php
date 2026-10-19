<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\ProgramApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administrator\Program\EndorseRequest;
use App\Http\Requests\Administrator\Program\IndexRequest;
use App\Http\Requests\Administrator\Program\ReturnRequest;
use App\Http\Requests\Administrator\Program\ShowRequest;
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
            'administrator.programs.show',
            'administrator.programs.endorse',
            'administrator.programs.return',
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
            'awaiting_count' => $this->approvals->awaitingAdministratorCount(),
            'approval_counts' => $this->approvals->approvalCounts(
                $request->search(),
                $request->types(),
                $request->statuses(),
                $request->departments(),
            ),
            'review_queue_count' => $this->approvals->awaitingAdministratorCount(),
            'review' => [
                'mode' => 'endorse',
                'audience' => 'Administrator',
                'index_url' => route('administrator.programs.index'),
                'awaiting_status' => ProgramApprovalStatus::AwaitingAdministrator->value,
                'positive_action' => 'endorse',
                'positive_label' => 'Recommend for approval',
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
            'review' => [
                'mode' => 'endorse',
                'audience' => 'Administrator',
                'index_url' => route('administrator.programs.index'),
                'show_url' => route('administrator.programs.show', $program),
                'positive_url' => route('administrator.programs.endorse', $program),
                'return_url' => route('administrator.programs.return', $program),
                'awaiting_status' => ProgramApprovalStatus::AwaitingAdministrator->value,
                'positive_action' => 'endorse',
                'positive_label' => 'Recommend for approval',
            ],
        ]);
    }

    public function endorse(EndorseRequest $request, Program $program): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->approvals->endorse($program, $actor, $request->remark());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Program recommended for approval.',
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
