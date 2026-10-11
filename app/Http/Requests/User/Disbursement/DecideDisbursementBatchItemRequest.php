<?php

namespace App\Http\Requests\User\Disbursement;

use App\Enums\DisbursementDecisionAction;
use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDisbursementBatchItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('disbursementBatch');

        return $batch instanceof DisbursementBatch
            && ($this->user()?->can('approve', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([
                DisbursementDecisionAction::Approved->value,
                DisbursementDecisionAction::Returned->value,
                DisbursementDecisionAction::Excluded->value,
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
