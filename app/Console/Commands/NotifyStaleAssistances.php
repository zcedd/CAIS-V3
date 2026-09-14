<?php

namespace App\Console\Commands;

use App\Models\Assistance;
use App\Models\User;
use App\Notifications\StaleAssistanceReminderNotification;
use App\Support\RequestStatusCode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('assistances:notify-stale')]
#[Description('Send database reminders for stale or overdue open assistances')]
class NotifyStaleAssistances extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dayKey = now()->toDateString();
        $monthKey = now()->startOfMonth()->format('Y-m');
        $staleBeforeDate = Carbon::now()->startOfDay()->subDays(7);
        $dueSoon = Carbon::now()->addDay();
        $notificationsTable = 'notifications';
        $sentCount = 0;

        Assistance::query()
            ->select([
                'assistances.id',
                'assistances.program_id',
                'assistances.user_id',
                'assistances.assigned_to_id',
            ])
            ->leftJoin('request_sub_statuses as rss', 'rss.id', '=', 'assistances.current_request_sub_status_id')
            ->leftJoin('request_statuses as rs', 'rs.id', '=', 'rss.request_status_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('rs.id')
                    ->orWhere(function ($open): void {
                        $open
                            ->where(function ($codeQuery): void {
                                $codeQuery
                                    ->whereNull('rs.code')
                                    ->orWhereNotIn('rs.code', RequestStatusCode::terminalValues());
                            })
                            ->where(function ($nameQuery): void {
                                $nameQuery
                                    ->whereNull('rs.name')
                                    ->orWhereNotIn('rs.name', ['Delivered', 'Denied', 'Closed']);
                            });
                    });
            })
            ->where(function ($query) use ($staleBeforeDate, $dueSoon): void {
                $query
                    ->where(function ($slaQuery) use ($dueSoon): void {
                        $slaQuery
                            ->whereNotNull('assistances.sla_due_at')
                            ->whereNull('assistances.sla_paused_at')
                            ->where('assistances.sla_due_at', '<=', $dueSoon);
                    })
                    ->orWhere(function ($fallbackQuery) use ($staleBeforeDate): void {
                        $fallbackQuery
                            ->whereNull('assistances.sla_due_at')
                            ->where(function ($staleQuery) use ($staleBeforeDate): void {
                                $staleQuery
                                    ->where('assistances.current_status_recorded_at', '<', $staleBeforeDate)
                                    ->orWhere(function ($updatedQuery) use ($staleBeforeDate): void {
                                        $updatedQuery
                                            ->whereNull('assistances.current_status_recorded_at')
                                            ->where('assistances.updated_at', '<', $staleBeforeDate);
                                    });
                            });
                    });
            })
            ->where(function ($query): void {
                $query
                    ->whereNotNull('assistances.assigned_to_id')
                    ->orWhereNotNull('assistances.user_id');
            })
            ->orderBy('assistances.id')
            ->chunkById(200, function ($assistances) use (
                $dayKey,
                $monthKey,
                $notificationsTable,
                &$sentCount
            ): void {
                foreach ($assistances as $assistance) {
                    $notifiableId = $assistance->assigned_to_id ?? $assistance->user_id;

                    if ($notifiableId === null) {
                        continue;
                    }

                    $alreadySent = DB::table($notificationsTable)
                        ->where('type', StaleAssistanceReminderNotification::class)
                        ->where('notifiable_type', User::class)
                        ->where('notifiable_id', $notifiableId)
                        ->where('data->assistance_id', $assistance->id)
                        ->where(function ($query) use ($dayKey, $monthKey): void {
                            $query
                                ->where('data->day_key', $dayKey)
                                ->orWhere('data->month_key', $monthKey);
                        })
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    $user = User::query()->find($notifiableId);

                    if (! $user instanceof User) {
                        continue;
                    }

                    $user->notify(new StaleAssistanceReminderNotification($assistance, $monthKey, $dayKey));
                    $sentCount++;
                }
            }, 'assistances.id', 'id');

        $this->info("Dispatched {$sentCount} stale assistance notification(s).");

        return self::SUCCESS;
    }
}
