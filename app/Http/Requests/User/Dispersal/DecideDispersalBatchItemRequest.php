<?php

namespace App\Http\Requests\User\Dispersal;

use App\Enums\DispersalDecisionAction;
use App\Models\DispersalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDispersalBatchItemRequest extends FormRequest
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
            'decision' => ['required', Rule::in([
                DispersalDecisionAction::Approved->value,
                DispersalDecisionAction::Returned->value,
                DispersalDecisionAction::Excluded->value,
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
