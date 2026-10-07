<?php

namespace App\Notifications;

use App\Models\Assistance;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\EmptyCell;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WorkflowStepChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Assistance $assistance,
        private WorkflowStep $step,
        private User $actor,
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
        $assistance = Assistance::query()
            ->with([
                'beneficiary:id,name,cais_number',
                'program:id,name,department_id',
                'program.department:id,slug',
            ])
            ->findOrFail($this->assistance->id);

        $url = route('user.assistances.show', [
            'department' => $assistance->program?->department?->slug,
            'program' => $assistance->program_id,
            'assistance' => $assistance->id,
        ]);

        $actorName = trim($this->actor->firstName.' '.$this->actor->lastName);

        return [
            'category' => 'system',
            'title' => 'Request moved to '.$this->step->displayName(),
            'message' => sprintf(
                '%s moved assistance #%d for %s (%s) to %s.',
                $actorName !== '' ? $actorName : 'A teammate',
                $assistance->id,
                $assistance->beneficiary?->name ?? 'Unknown beneficiary',
                $assistance->beneficiary?->cais_number ?? EmptyCell::VALUE,
                $this->step->displayName(),
            ),
            'url' => $url,
            'assistance_id' => $assistance->id,
            'program_id' => $assistance->program_id,
            'workflow_step_id' => $this->step->id,
        ];
    }
}
