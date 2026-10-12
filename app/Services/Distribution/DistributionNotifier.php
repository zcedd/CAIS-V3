<?php

namespace App\Services\Distribution;

use App\Models\DistributionBatch;
use App\Models\User;
use App\Notifications\DistributionBatchNotification;
use Illuminate\Support\Collection;

class DistributionNotifier
{
    /**
     * @param  Collection<int, User>  $users
     */
    public function send(Collection $users, DistributionBatch $batch, string $dedupeKey, string $title, string $message): void
    {
        $batch->loadMissing('department');

        $url = $batch->department !== null
            ? route('user.distributions.show', [
                'department' => $batch->department->slug,
                'distributionBatch' => $batch,
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

            $user->notify(new DistributionBatchNotification(
                dedupeKey: $dedupeKey,
                title: $title,
                message: $message,
                url: $url,
                distributionBatchId: $batch->id,
            ));
        }
    }
}
