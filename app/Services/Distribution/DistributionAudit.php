<?php

namespace App\Services\Distribution;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

class DistributionAudit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(User $actor, Model $subject, string $event, array $properties = []): void
    {
        $source = Context::get('distribution_audit_source');

        if (! array_key_exists('source', $properties) && is_string($source) && $source !== '') {
            $properties['source'] = $source;
        }

        activity('distribution')
            ->causedBy($actor)
            ->performedOn($subject)
            ->event($event)
            ->withProperties($properties)
            ->log($event);
    }
}
