<?php

namespace App\Notifications;

use App\Models\Assistance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class StaleAssistanceReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Assistance $assistance,
        private string $monthKey,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $assistance = Assistance::query()
            ->with([
                'beneficiary:id,name,cais_number',
                'program:id,name,department_id',
                'program.department:id,slug',
                'latestAssistanceRequestSubStatus.requestSubStatus.requestStatus',
                'currentRequestSubStatus.requestStatus',
            ])
            ->findOrFail($this->assistance->id);

        $programName = $assistance->program?->name ?? 'Unknown program';
        $beneficiaryName = $assistance->beneficiary?->name ?? 'Unknown beneficiary';
        $caisNumber = $assistance->beneficiary?->cais_number ?? '—';

        $latestStatus = $assistance->latestAssistanceRequestSubStatus;
        $requestSubStatus = $latestStatus?->requestSubStatus?->name;
        $requestStatus = $latestStatus?->requestSubStatus?->requestStatus?->name;

        $statusLabel = $requestSubStatus !== null
            ? ($requestStatus !== null ? "{$requestStatus} — {$requestSubStatus}" : $requestSubStatus)
            : ($assistance->currentRequestSubStatus?->requestStatus?->name
                ?? $assistance->currentRequestSubStatus?->name
                ?? 'Unrequested');

        $lastUpdatedAt = $latestStatus?->recorded_at ?? $assistance->updated_at;
        $lastUpdatedLabel = $lastUpdatedAt !== null
            ? Carbon::parse($lastUpdatedAt)->toFormattedDateString()
            : 'unknown date';
        $daysSinceUpdate = $lastUpdatedAt !== null
            ? (int) Carbon::parse($lastUpdatedAt)->startOfDay()->diffInDays(now()->startOfDay())
            : null;

        $url = route('user.assistances.show', [
            'department' => $assistance->program?->department?->slug,
            'program' => $assistance->program_id,
            'assistance' => $assistance->id,
        ]);

        $daysText = $daysSinceUpdate !== null
            ? (string) $daysSinceUpdate
            : 'several';

        $message = sprintf(
            '<p>Assistance <strong>#%d</strong> for <strong>%s</strong> (%s) under <strong>%s</strong> is still open and has not been updated for at least 7 days.</p><p>Current status: <strong>%s</strong>. Last updated on <strong>%s</strong> (%s days ago).</p><p><a href="%s">View request profile</a></p>',
            $assistance->id,
            e($beneficiaryName),
            e($caisNumber),
            e($programName),
            e($statusLabel),
            e($lastUpdatedLabel),
            $daysText,
            e($url),
        );

        return [
            'category' => 'system',
            'title' => 'Stale assistance reminder',
            'message' => $message,
            'url' => $url,
            'assistance_id' => $assistance->id,
            'program_id' => $assistance->program_id,
            'month_key' => $this->monthKey,
        ];
    }
}
