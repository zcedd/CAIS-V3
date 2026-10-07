<?php

namespace App\Services\User;

use App\Enums\ProgramApprovalAction;
use App\Enums\ProgramApprovalStatus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramApprovalService
{
    public function submit(User $actor, Program $program): Program
    {
        return $this->transition(
            $actor,
            $program->approvalSubject(),
            [ProgramApprovalStatus::Draft, ProgramApprovalStatus::Returned],
            ProgramApprovalStatus::Proposed,
            ProgramApprovalAction::Submit,
        );
    }

    public function endorse(User $actor, Program $program): Program
    {
        return $this->transition(
            $actor,
            $program->approvalSubject(),
            [ProgramApprovalStatus::Proposed],
            ProgramApprovalStatus::AwaitingGovernor,
            ProgramApprovalAction::Endorse,
        );
    }

    public function revise(User $actor, Program $program): Program
    {
        return $this->transition(
            $actor,
            $program->approvalSubject(),
            [ProgramApprovalStatus::Proposed],
            ProgramApprovalStatus::Draft,
            ProgramApprovalAction::Revise,
        );
    }

    public function approve(User $actor, Program $program): Program
    {
        return $this->transition(
            $actor,
            $program->approvalSubject(),
            [ProgramApprovalStatus::AwaitingGovernor],
            ProgramApprovalStatus::Approved,
            ProgramApprovalAction::Approve,
        );
    }

    public function returnToDepartment(User $actor, Program $program, string $comment): Program
    {
        return $this->transition(
            $actor,
            $program->approvalSubject(),
            [ProgramApprovalStatus::AwaitingGovernor],
            ProgramApprovalStatus::Returned,
            ProgramApprovalAction::Return,
            $comment,
        );
    }

    public function proposedCount(?int $departmentId): int
    {
        return Program::query()
            ->whereNull('parent_id')
            ->where('approval_status', ProgramApprovalStatus::Proposed)
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->count();
    }

    public function awaitingGovernorCount(): int
    {
        return Program::query()
            ->whereNull('parent_id')
            ->where('approval_status', ProgramApprovalStatus::AwaitingGovernor)
            ->count();
    }

    /**
     * @param  list<ProgramApprovalStatus>  $from
     */
    private function transition(
        User $actor,
        Program $program,
        array $from,
        ProgramApprovalStatus $to,
        ProgramApprovalAction $action,
        ?string $comment = null,
    ): Program {
        if (! in_array($program->approval_status, $from, true)) {
            throw ValidationException::withMessages([
                'approval' => 'This program cannot be moved from its current approval status.',
            ]);
        }

        return DB::transaction(function () use ($actor, $program, $to, $action, $comment): Program {
            $previous = $program->approval_status;

            $program->update([
                'approval_status' => $to,
            ]);

            $program->approvalEvents()->create([
                'user_id' => $actor->id,
                'action' => $action,
                'from_status' => $previous,
                'to_status' => $to,
                'comment' => $comment,
            ]);

            return $program->refresh();
        });
    }
}
