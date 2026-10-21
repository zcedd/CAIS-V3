<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Program\SubmitApprovalRequest;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\User\ProgramApprovalService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProgramApprovalController extends Controller
{
    public function __construct(private ProgramApprovalService $approvals) {}

    public function store(
        SubmitApprovalRequest $request,
        Department $department,
        Program $program,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->approvals->submit($program, $actor, $request->remark());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Program submitted for recommendation.',
        ]);

        return redirect()->back();
    }
}
