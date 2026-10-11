<?php

namespace App\Http\Requests\User\Disbursement;

use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleDisbursementBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('disbursementBatch');

        return $batch instanceof DisbursementBatch
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
