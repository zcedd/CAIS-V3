<?php

namespace App\Http\Requests\User\Dispersal;

use App\Models\DispersalBatch;
use Illuminate\Foundation\Http\FormRequest;

class CancelDispersalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('dispersalBatch');

        return $batch instanceof DispersalBatch
            && ($this->user()?->can('cancel', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string'],
            'lock_version' => ['nullable', 'integer'],
        ];
    }
}
