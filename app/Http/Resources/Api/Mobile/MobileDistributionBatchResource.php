<?php

namespace App\Http\Resources\Api\Mobile;

use App\Models\DistributionBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DistributionBatch */
class MobileDistributionBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_code' => $this->batch_code,
            'title' => $this->title,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'program' => $this->program?->name,
            'venue' => $this->venue,
            'venue_details' => $this->venue_details,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
        ];
    }
}
