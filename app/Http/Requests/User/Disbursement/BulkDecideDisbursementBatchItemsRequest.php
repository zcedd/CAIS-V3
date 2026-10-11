<?php

namespace App\Http\Requests\User\Disbursement;

use App\Enums\DisbursementDecisionAction;
use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDecideDisbursementBatchItemsRequest extends FormRequest
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
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'distinct'],
            'decision' => ['required', Rule::in([
                DisbursementDecisionAction::Approved->value,
                DisbursementDecisionAction::Excluded->value,
            ])],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
