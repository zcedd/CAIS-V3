<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WorkflowTask;
use App\Notifications\WorkflowTaskDueNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('workflow-tasks:notify-due')]
#[Description('Send database reminders for workflow tasks that are due soon or overdue')]
class NotifyWorkflowTaskDue extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dayKey = now()->toDateString();
        $dueSoon = now()->addDay();
        $sentCount = 0;

        WorkflowTask::query()
            ->open()
            ->whereNotNull('assigned_to_id')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $dueSoon)
            ->orderBy('id')
            ->chunkById(200, function ($tasks) use ($dayKey, &$sentCount): void {
                foreach ($tasks as $task) {
                    $kind = $task->due_at !== null && $task->due_at->lessThan(now())
                        ? 'overdue'
                        : 'due_soon';

                    $alreadySent = DB::table('notifications')
                        ->where('type', WorkflowTaskDueNotification::class)
                        ->where('notifiable_type', User::class)
                        ->where('notifiable_id', $task->assigned_to_id)
                        ->where('data->workflow_task_id', $task->id)
                        ->where('data->day_key', $dayKey)
                        ->where('data->kind', $kind)
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    $user = User::query()->find($task->assigned_to_id);

                    if (! $user instanceof User) {
                        continue;
                    }

                    $user->notify(new WorkflowTaskDueNotification($task, $kind, $dayKey));
                    $sentCount++;
                }
            });

        $this->info("Dispatched {$sentCount} workflow task due notification(s).");

        return self::SUCCESS;
    }
}
