<?php

namespace App\Notifications;

use App\Models\Assistance;
use App\Models\User;
use App\Support\EmptyCell;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssistanceAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Assistance $assistance,
        private User $assignedBy,
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
                'currentRequestSubStatus.requestStatus',
            ])
            ->findOrFail($this->assistance->id);

        $url = route('user.assistances.show', [
            'department' => $assistance->program?->department?->slug,
            'program' => $assistance->program_id,
            'assistance' => $assistance->id,
        ]);

        $beneficiaryName = $assistance->beneficiary?->name ?? 'Unknown beneficiary';
        $caisNumber = $assistance->beneficiary?->cais_number ?? EmptyCell::VALUE;
        $programName = $assistance->program?->name ?? 'Unknown program';
        $actorName = trim($this->assignedBy->firstName.' '.$this->assignedBy->lastName);

        return [
            'category' => 'system',
            'title' => 'Assistance assigned to you',
            'message' => sprintf(
                '%s assigned assistance #%d for %s (%s) under %s to you.',
                $actorName !== '' ? $actorName : 'A teammate',
                $assistance->id,
                $beneficiaryName,
                $caisNumber,
                $programName,
            ),
            'url' => $url,
            'assistance_id' => $assistance->id,
            'program_id' => $assistance->program_id,
        ];
    }
}
