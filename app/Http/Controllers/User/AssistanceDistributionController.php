<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\InitiateDistributionRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Services\Distribution\AssistanceDistributionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AssistanceDistributionController extends Controller
{
    public function store(
        InitiateDistributionRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        AssistanceDistributionState $state,
    ): RedirectResponse {
        $mode = $request->validated('mode');
        $distributionMode = $program->resolvedDistributionMode();

        if ($mode === 'individual' && ! $distributionMode->allowsIndividual()) {
            throw ValidationException::withMessages([
                'mode' => 'This program does not authorize individual direct release.',
            ]);
        }

        if ($mode === 'batch' && ! $distributionMode->allowsBatch()) {
            throw ValidationException::withMessages([
                'mode' => 'This program does not authorize batch distribution.',
            ]);
        }

        $active = $state->summarize($department, $assistance)['state'] === 'active';

        return redirect()->to($state->destination(
            $department,
            $assistance,
            $mode,
        ))->with(
            'success',
            $active
                ? 'This request already has a distribution. Continue that transaction.'
                : ($mode === 'individual'
                    ? 'Set the venue and distribution time, then record the release.'
                    : 'Complete the distribution venue before assistance can be marked delivered.'),
        );
    }
}
