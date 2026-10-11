<?php

namespace App\Http\Requests\User\Dispersal;

use App\Models\DispersalBatch;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleDispersalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('dispersalBatch');

        return $batch instanceof DispersalBatch
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
            'reason' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
        ];
    }
}
