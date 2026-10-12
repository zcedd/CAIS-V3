<?php

namespace App\Services\Distribution;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DistributionAudit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(User $actor, Model $subject, string $event, array $properties = []): void
    {
        activity('distribution')
            ->causedBy($actor)
            ->performedOn($subject)
            ->event($event)
            ->withProperties($properties)
            ->log($event);
    }
}
