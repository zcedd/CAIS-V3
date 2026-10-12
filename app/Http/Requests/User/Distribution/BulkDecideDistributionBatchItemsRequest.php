<?php

namespace App\Http\Requests\User\Distribution;

use App\Enums\DistributionDecisionAction;
use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDecideDistributionBatchItemsRequest extends FormRequest
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
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'distinct'],
            'decision' => ['required', Rule::in([
                DistributionDecisionAction::Approved->value,
                DistributionDecisionAction::Excluded->value,
            ])],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
