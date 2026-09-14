<?php

namespace App\Http\Controllers\User;

use App\Actions\User\CreateProgramBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Program\StoreBatchRequest;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;

class ProgramBatchController extends Controller
{
    public function __construct(
        private CreateProgramBatch $createProgramBatch,
    ) {}

    /**
     * Store a new batch under a parent program.
     */
    public function store(
        StoreBatchRequest $request,
        Department $department,
        Program $program,
    ): RedirectResponse {
        ($this->createProgramBatch)($program, $request->validated());

        return redirect()
            ->route('user.programs.show', [
                'department' => $department,
                'program' => $program,
            ])
            ->with('success', 'Batch created successfully.');
    }
}
