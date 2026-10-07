<?php

namespace App\Http\Controllers\Governor;

use App\Enums\ProgramApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Governor\Program\ApproveRequest;
use App\Http\Requests\Governor\Program\IndexRequest;
use App\Http\Requests\Governor\Program\ReturnRequest;
use App\Http\Requests\Governor\Program\ShowRequest;
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
        $programs = Program::query()
            ->with('department:id,name,slug')
            ->whereNull('parent_id')
            ->where('approval_status', ProgramApprovalStatus::AwaitingGovernor)
            ->orderBy('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
                'descriptions' => $program->descriptions,
                'department' => $program->department?->only(['id', 'name', 'slug']),
            ]);

        return Inertia::render('governor/programs/index', [
            'programs' => $programs,
            'pending_count' => $this->approvals->awaitingGovernorCount(),
        ]);
    }

    public function show(ShowRequest $request, Program $program): Response
    {
        $subject = $program->approvalSubject()->loadMissing('department:id,name,slug');

        return Inertia::render('governor/programs/show', [
            'program' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'descriptions' => $subject->descriptions,
                'approval_status' => $subject->approval_status?->value,
                'approval_label' => $subject->approval_status?->label(),
                'return_comment' => $subject->latestReturnComment(),
                'department' => $subject->department?->only(['id', 'name', 'slug']),
            ],
        ]);
    }

    public function approve(ApproveRequest $request, Program $program): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->approvals->approve($user, $program);

        return redirect()
            ->route('governor.programs.index')
            ->with('success', 'Program approved.');
    }

    public function returnToDepartment(ReturnRequest $request, Program $program): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->approvals->returnToDepartment($user, $program, $request->string('comment')->toString());

        return redirect()
            ->route('governor.programs.index')
            ->with('success', 'Program returned to the department.');
    }
}
