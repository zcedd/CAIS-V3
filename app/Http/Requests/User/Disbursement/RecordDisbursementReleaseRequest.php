<?php

namespace App\Http\Requests\User\Disbursement;

use App\Enums\DisbursementReleaseOutcome;
use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDisbursementReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('disbursementBatch');

        return $batch instanceof DisbursementBatch
            && ($this->user()?->can('execute', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'disbursement_batch_item_id' => ['required', 'integer'],
            'disbursement_batch_item_line_id' => ['nullable', 'integer'],
            'outcome' => ['required', Rule::enum(DisbursementReleaseOutcome::class)],
            'actual_quantity' => ['nullable', 'numeric', 'min:0'],
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'receipt_reference' => ['nullable', 'string', 'max:255'],
            'identity_verified' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'released_at' => ['nullable', 'date'],
        ];
    }
}
