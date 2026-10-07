<?php

namespace App\Actions\Admin;

use App\Models\Program;
use App\Models\Workflow;
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
}
