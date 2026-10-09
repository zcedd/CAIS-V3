<?php

namespace App\Actions\Admin;

use App\Enums\ProgramApprovalAction;
use App\Enums\ProgramApprovalStatus;
use App\Models\Program;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignWorkflowPrograms
{
    /**
     * @param  list<int>  $programIds
     */
    public function __invoke(Workflow $workflow, array $programIds): Workflow
    {
        return DB::transaction(function () use ($workflow, $programIds): Workflow {
            $ids = array_values(array_unique(array_map('intval', $programIds)));

            $foreign = Program::query()
                ->whereIn('id', $ids)
                ->where('department_id', '!=', $workflow->department_id)
                ->exists();

            if ($foreign) {
                throw ValidationException::withMessages([
                    'program_ids' => 'Programs must belong to the same department as this workflow.',
                ]);
            }

            if ($this->changesApprovedProgram($workflow, $ids)) {
                throw ValidationException::withMessages([
                    'program_ids' => 'Approved programs cannot change workflow.',
                ]);
            }

            Program::query()
                ->where('workflow_id', $workflow->id)
                ->whereNotIn('id', $ids === [] ? [0] : $ids)
                ->update(['workflow_id' => null]);

            if ($ids !== []) {
                Program::query()
                    ->where('department_id', $workflow->department_id)
                    ->whereIn('id', $ids)
                    ->update(['workflow_id' => $workflow->id]);
            }

            return $workflow->refresh();
        });
    }

    /**
     * @param  list<int>  $programIds
     */
    private function changesApprovedProgram(Workflow $workflow, array $programIds): bool
    {
        $locked = Program::query()
            ->where('approval_status', ProgramApprovalStatus::Approved->value)
            ->whereHas('approvalEvents', function (Builder $events): void {
                $events->where('action', ProgramApprovalAction::Approved);
            });

        $assigningLocked = (clone $locked)
            ->where('department_id', $workflow->department_id)
            ->whereIn('id', $programIds === [] ? [0] : $programIds)
            ->where(function (Builder $query) use ($workflow): void {
                $query->whereNull('workflow_id')
                    ->orWhere('workflow_id', '!=', $workflow->id);
            })
            ->exists();

        if ($assigningLocked) {
            return true;
        }

        return (clone $locked)
            ->where('workflow_id', $workflow->id)
            ->when(
                $programIds !== [],
                fn (Builder $query): Builder => $query->whereNotIn('id', $programIds),
            )
            ->exists();
    }
}
