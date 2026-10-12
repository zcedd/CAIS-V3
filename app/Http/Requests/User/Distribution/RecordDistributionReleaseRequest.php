<?php

namespace App\Http\Requests\User\Distribution;

use App\Enums\DistributionReleaseOutcome;
use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDistributionReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
            && ($this->user()?->can('execute', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'distribution_batch_item_id' => ['required', 'integer'],
            'distribution_batch_item_line_id' => ['nullable', 'integer'],
            'outcome' => ['required', Rule::enum(DistributionReleaseOutcome::class)],
            'actual_quantity' => ['nullable', 'numeric', 'min:0'],
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'receipt_reference' => ['nullable', 'string', 'max:255'],
            'identity_verified' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'released_at' => ['nullable', 'date'],
        ];
    }
}
