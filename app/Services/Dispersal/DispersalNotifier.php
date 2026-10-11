<?php

namespace App\Services\Dispersal;

use App\Models\DispersalBatch;
use App\Models\User;
use App\Notifications\DispersalBatchNotification;
use Illuminate\Support\Collection;

class DispersalNotifier
{
    /**
     * @param  Collection<int, User>  $users
     */
    public function send(Collection $users, DispersalBatch $batch, string $dedupeKey, string $title, string $message): void
    {
        $batch->loadMissing('department');

        $url = $batch->department !== null
            ? route('user.dispersals.show', [
                'department' => $batch->department->slug,
                'dispersalBatch' => $batch,
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

            $user->notify(new DispersalBatchNotification(
                dedupeKey: $dedupeKey,
                title: $title,
                message: $message,
                url: $url,
                dispersalBatchId: $batch->id,
            ));
        }
    }
}
