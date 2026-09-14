<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignAssistance
{
    public function __construct(
        private RecordAssistanceAssignment $recordAssistanceAssignment,
    ) {}

    public function __invoke(Assistance $assistance, User $actor, ?User $assignee, ?string $remark = null): Assistance
    {
        $assistance->loadMissing('program');

        if ($assignee instanceof User && $assignee->department_id !== $assistance->program?->department_id) {
            throw ValidationException::withMessages([
                'assigned_to_id' => 'The selected staff member does not belong to this department.',
            ]);
        }

        return ($this->recordAssistanceAssignment)($assistance, $actor, $assignee, $remark);
    }
}
