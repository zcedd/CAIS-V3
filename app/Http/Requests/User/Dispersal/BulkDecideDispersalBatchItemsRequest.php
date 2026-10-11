<?php

namespace App\Http\Requests\User\Dispersal;

use App\Enums\DispersalDecisionAction;
use App\Models\DispersalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDecideDispersalBatchItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('dispersalBatch');

        return $batch instanceof DispersalBatch
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
                DispersalDecisionAction::Approved->value,
                DispersalDecisionAction::Excluded->value,
            ])],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
