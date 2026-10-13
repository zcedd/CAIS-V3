<?php

namespace App\Http\Controllers\User;

use App\Actions\Distribution\OpenIndividualDistribution;
use App\Enums\DistributionApprovalMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\InitiateDistributionRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\Distribution\AssistanceDistributionState;
use Illuminate\Http\RedirectResponse;

class AssistanceDistributionController extends Controller
{
    public function store(
        InitiateDistributionRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        AssistanceDistributionState $state,
        OpenIndividualDistribution $openIndividual,
    ): RedirectResponse {
        $active = $state->summarize($department, $assistance)['state'] === 'active';
        $actor = $request->user();

        if ($request->validated('mode') === 'individual' && ! $active && $actor instanceof User) {
            $state->assertEligible($assistance);
            $batch = $openIndividual($department, $actor, [
                'program_id' => (int) $assistance->program_id,
                'title' => 'Individual distribution',
                'scheduled_at' => now()->toDateTimeString(),
                'venue' => null,
                'approval_mode' => DistributionApprovalMode::Beneficiary->value,
                'assistance_ids' => [$assistance->id],
            ]);

            return redirect()
                ->route('user.distributions.venue', [
                    'department' => $department,
                    'distributionBatch' => $batch,
                    'item' => $batch->items()->value('id'),
                ])
                ->with('success', 'Record the release for this beneficiary.');
        }

        return redirect()->to($state->destination(
            $department,
            $assistance,
            $request->validated('mode'),
        ))->with(
            'success',
            $active
                ? 'This request already has a distribution. Continue that transaction.'
                : 'Complete the distribution venue before assistance can be marked delivered.',
        );
    }
}
