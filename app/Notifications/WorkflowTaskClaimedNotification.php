<?php

namespace App\Notifications;

use App\Models\Assistance;
use App\Models\User;
use App\Models\WorkflowTask;
use App\Support\EmptyCell;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WorkflowTaskClaimedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private WorkflowTask $task,
        private User $claimedBy,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->task->loadMissing([
            'step',
            'workflowInstance.assistance.beneficiary:id,name,cais_number',
            'workflowInstance.assistance.program.department:id,slug',
        ]);

        $assistance = $this->task->workflowInstance?->assistance;
        $actorName = trim($this->claimedBy->firstName.' '.$this->claimedBy->lastName);

        return [
            'category' => 'system',
            'title' => 'Workflow task claimed',
            'message' => sprintf(
                '%s claimed the %s task for assistance #%s (%s).',
                $actorName !== '' ? $actorName : 'A teammate',
                $this->task->step?->displayName() ?? 'workflow',
                $assistance?->id ?? EmptyCell::VALUE,
                $assistance?->beneficiary?->cais_number ?? EmptyCell::VALUE,
            ),
            'url' => $assistance instanceof Assistance
                ? route('user.assistances.show', [
                    'department' => $assistance->program?->department?->slug,
                    'program' => $assistance->program_id,
                    'assistance' => $assistance->id,
                ])
                : null,
            'assistance_id' => $assistance?->id,
            'workflow_task_id' => $this->task->id,
        ];
    }
}
