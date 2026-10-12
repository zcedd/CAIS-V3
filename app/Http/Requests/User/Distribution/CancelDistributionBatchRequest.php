<?php

namespace App\Http\Requests\User\Distribution;

use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;

class CancelDistributionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
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
