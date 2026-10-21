<?php

namespace App\Notifications;

use App\Enums\ProgramApprovalAction;
use App\Models\Program;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProgramDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Program $program,
        private User $actor,
        private ProgramApprovalAction $action,
        private ?string $remark,
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
        $program = $this->program->loadMissing('department:id,slug');
        $approved = $this->action === ProgramApprovalAction::Approved;
        $actorName = trim($this->actor->name);
        $actorName = $actorName !== '' ? $actorName : 'A reviewer';
        $message = $approved
            ? sprintf('%s approved %s.', $actorName, $program->name)
            : sprintf('%s returned %s.', $actorName, $program->name);
        $remark = trim((string) $this->remark);

        if (! $approved && $remark !== '') {
            $message .= ' '.$remark;
        }

        $url = $program->department?->slug !== null
            ? route('user.programs.show', [
                'department' => $program->department->slug,
                'program' => $program,
            ])
            : null;

        return [
            'category' => 'system',
            'title' => $approved ? 'Program approved' : 'Program returned',
            'message' => $message,
            'url' => $url,
            'program_id' => $program->id,
        ];
    }
}
