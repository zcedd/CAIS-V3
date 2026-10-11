<?php

namespace App\Services\Dispersal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DispersalAudit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(User $actor, Model $subject, string $event, array $properties = []): void
    {
        activity('dispersal')
            ->causedBy($actor)
            ->performedOn($subject)
            ->event($event)
            ->withProperties($properties)
            ->log($event);
    }
}
