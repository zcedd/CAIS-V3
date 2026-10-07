<?php

namespace App\Notifications;

use App\Models\WorkflowTask;
use App\Support\EmptyCell;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WorkflowTaskDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private WorkflowTask $task,
        private string $kind,
        private string $dayKey,
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
        $title = $this->kind === 'overdue' ? 'Workflow task overdue' : 'Workflow task due soon';

        return [
            'category' => 'system',
            'title' => $title,
            'message' => sprintf(
                'The %s task for assistance #%s (%s) is %s.',
                $this->task->step?->displayName() ?? 'workflow',
                $assistance?->id ?? EmptyCell::VALUE,
                $assistance?->beneficiary?->cais_number ?? EmptyCell::VALUE,
                $this->kind === 'overdue' ? 'overdue' : 'due soon',
            ),
            'url' => $assistance !== null
                ? route('user.assistances.show', [
                    'department' => $assistance->program?->department?->slug,
                    'program' => $assistance->program_id,
                    'assistance' => $assistance->id,
                ])
                : null,
            'assistance_id' => $assistance?->id,
            'workflow_task_id' => $this->task->id,
            'day_key' => $this->dayKey,
            'kind' => $this->kind,
        ];
    }
}
