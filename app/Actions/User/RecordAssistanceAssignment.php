<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceAssignment;
use App\Models\User;
use App\Notifications\AssistanceAssignedNotification;

class RecordAssistanceAssignment
{
    public function __invoke(
        Assistance $assistance,
        User $actor,
        ?User $assignee,
        ?string $remark = null,
        bool $notify = true,
    ): Assistance {
        $previousId = $assistance->assigned_to_id;
        $nextId = $assignee?->id;

        if ($previousId === $nextId) {
            return $assistance;
        }

        AssistanceAssignment::query()->create([
            'assistance_id' => $assistance->id,
            'assigned_from_id' => $previousId,
            'assigned_to_id' => $nextId,
            'assigned_by_id' => $actor->id,
            'remark' => $remark,
        ]);

        $assistance->forceFill([
            'assigned_to_id' => $nextId,
            'assigned_at' => $nextId === null ? null : now(),
        ])->save();

        if ($notify && $assignee instanceof User && $assignee->id !== $actor->id) {
            $assignee->notify(new AssistanceAssignedNotification($assistance->fresh() ?? $assistance, $actor));
        }

        return $assistance->refresh();
    }
}
