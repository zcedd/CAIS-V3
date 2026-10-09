<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Executive\Program\ApproveRequest;
use App\Http\Requests\Executive\Program\IndexRequest;
use App\Http\Requests\Executive\Program\ReturnRequest;
use App\Http\Requests\Executive\Program\ShowRequest;
use App\Models\Program;
use App\Models\User;
use App\Services\User\ProgramApprovalService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function __construct(private ProgramApprovalService $approvals) {}

    public function index(IndexRequest $request): Response
    {
        return Inertia::render('executive/programs/index', [
            'programs' => $this->approvals->paginateForGovernor(
                $request->search(),
                $request->types(),
                $request->statuses(),
                $request->approvals(),
                $request->departments(),
                $request->perPage(),
            ),
            'search' => $request->search(),
            'type' => $request->types(),
            'status' => $request->statuses(),
            'approval' => $request->approvals(),
            'department' => array_map(strval(...), $request->departments()),
            'departments' => $this->approvals->departmentOptions(),
            'per_page' => $request->perPage(),
            'awaiting_count' => $this->approvals->awaitingGovernorCount(),
        ]);
    }

    public function show(ShowRequest $request, Program $program): Response
    {
        return Inertia::render('executive/programs/show', [
            'program' => $this->approvals->showPayload($program),
            'beneficiaries' => $this->approvals->paginateBeneficiaries($program),
        ]);
    }

    public function approve(ApproveRequest $request, Program $program): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->approvals->approve($program, $actor, $request->remark());

        $message = $program->requiresBeneficiaries()
            ? 'Program approved. Verified requests are ready for release.'
            : 'Program approved. Staff can add beneficiaries to this program.';

        return redirect()
            ->route('executive.programs.show', $program)
            ->with('success', $message);
    }

    public function returnToDepartment(ReturnRequest $request, Program $program): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->approvals->returnToDepartment($program, $actor, $request->remark());

        return redirect()
            ->route('executive.programs.show', $program)
            ->with('success', 'Program returned to the department.');
    }
}
