<?php

namespace App\Services\Disbursement;

use App\Models\DisbursementBatch;
use App\Models\User;
use App\Notifications\DisbursementBatchNotification;
use Illuminate\Support\Collection;

class DisbursementNotifier
{
    /**
     * @param  Collection<int, User>  $users
     */
    public function send(Collection $users, DisbursementBatch $batch, string $dedupeKey, string $title, string $message): void
    {
        $batch->loadMissing('department');

        $url = $batch->department !== null
            ? route('user.disbursements.show', [
                'department' => $batch->department->slug,
                'disbursementBatch' => $batch,
            ])
            : null;

        foreach ($users->unique('id') as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $alreadySent = $user->notifications()
                ->where('data->dedupe_key', $dedupeKey)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $user->notify(new DisbursementBatchNotification(
                dedupeKey: $dedupeKey,
                title: $title,
                message: $message,
                url: $url,
                disbursementBatchId: $batch->id,
            ));
        }
    }
}
