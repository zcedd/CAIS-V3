<?php

namespace App\Http\Requests\User\Distribution;

use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleDistributionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
            && ($this->user()?->can('schedule', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'venue_details' => ['nullable', 'string', 'max:2000'],
            'venue_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'venue_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'reason' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
        ];
    }
}
