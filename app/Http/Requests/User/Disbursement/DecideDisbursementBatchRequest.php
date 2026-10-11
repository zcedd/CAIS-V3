<?php

namespace App\Http\Requests\User\Disbursement;

use App\Enums\DisbursementDecisionAction;
use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDisbursementBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('disbursementBatch');

        if (! $batch instanceof DisbursementBatch || $this->user() === null) {
            return false;
        }

        return match ($this->input('decision')) {
            DisbursementDecisionAction::Approved->value => $this->user()->can('approve', $batch),
            DisbursementDecisionAction::Returned->value => $this->user()->can('returnBatch', $batch),
            DisbursementDecisionAction::Rejected->value => $this->user()->can('reject', $batch),
            default => false,
        };
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
                DisbursementDecisionAction::Rejected->value,
            ])],
            'remarks' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
        ];
    }
}
