<?php

namespace App\Services\Disbursement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DisbursementAudit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(User $actor, Model $subject, string $event, array $properties = []): void
    {
        activity('disbursement')
            ->causedBy($actor)
            ->performedOn($subject)
            ->event($event)
            ->withProperties($properties)
            ->log($event);
    }
}
