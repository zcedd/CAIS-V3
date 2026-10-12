<?php

namespace App\Http\Requests\User\Distribution;

use App\Enums\DistributionDecisionAction;
use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDistributionBatchItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
            && ($this->user()?->can('approve', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([
                DistributionDecisionAction::Approved->value,
                DistributionDecisionAction::Returned->value,
                DistributionDecisionAction::Excluded->value,
            ])],
            'remarks' => ['nullable', 'string'],
            'exclusion_reason' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['nullable', 'array'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.approved_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.approved_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
