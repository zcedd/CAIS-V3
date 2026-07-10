<?php

namespace App\Console\Commands;

use App\Actions\User\BuildLatestAssistanceRequestSubStatusSubquery;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\User;
use App\Notifications\StaleAssistanceReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('assistances:notify-stale')]
#[Description('Send database reminders for stale open assistances')]
class NotifyStaleAssistances extends Command
{
    public function __construct(
        private BuildLatestAssistanceRequestSubStatusSubquery $buildLatestAssistanceRequestSubStatusSubquery,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monthKey = now()->startOfMonth()->format('Y-m');
        $staleBeforeDate = Carbon::now()->startOfDay()->subDays(7);
        $notificationsTable = 'notifications';
        $pivotTable = (new AssistanceRequestSubStatus)->getTable();
        $sentCount = 0;
        $latestArssLookup = ($this->buildLatestAssistanceRequestSubStatusSubquery)();

        Assistance::query()
            ->select(['assistances.id', 'assistances.program_id', 'assistances.user_id'])
            ->leftJoinSub(
                $latestArssLookup,
                'latest_arss_lookup',
                function ($join): void {
                    $join->on('latest_arss_lookup.assistance_id', '=', 'assistances.id');
                },
            )
            ->leftJoin("{$pivotTable} as arss", 'arss.id', '=', 'latest_arss_lookup.latest_arss_id')
            ->leftJoin('request_sub_statuses as rss', 'rss.id', '=', 'arss.request_sub_status_id')
            ->leftJoin('request_statuses as rs', 'rs.id', '=', 'rss.request_status_id')
            ->where(function ($query): void {
                $query->whereNull('rs.name')
                    ->orWhereNotIn('rs.name', ['Delivered', 'Denied', 'Closed']);
            })
            ->where(function ($query) use ($staleBeforeDate): void {
                $query->where('arss.recorded_at', '<', $staleBeforeDate)
                    ->orWhere(function ($nestedQuery) use ($staleBeforeDate): void {
                        $nestedQuery->whereNull('arss.recorded_at')
                            ->where('assistances.updated_at', '<', $staleBeforeDate);
                    });
            })
            ->whereNotNull('assistances.user_id')
            ->orderBy('assistances.id')
            ->chunkById(200, function ($assistances) use (
                $monthKey,
                $notificationsTable,
                &$sentCount
            ): void {
                foreach ($assistances as $assistance) {
                    $alreadySent = DB::table($notificationsTable)
                        ->where('type', StaleAssistanceReminderNotification::class)
                        ->where('notifiable_type', User::class)
                        ->where('notifiable_id', $assistance->user_id)
                        ->where('data->assistance_id', $assistance->id)
                        ->where('data->month_key', $monthKey)
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    $user = User::query()->find($assistance->user_id);

                    if (! $user instanceof User) {
                        continue;
                    }

                    $user->notify(new StaleAssistanceReminderNotification($assistance, $monthKey));
                    $sentCount++;
                }
            }, 'assistances.id', 'id');

        $this->info("Dispatched {$sentCount} stale assistance notification(s).");

        return self::SUCCESS;
    }
}
