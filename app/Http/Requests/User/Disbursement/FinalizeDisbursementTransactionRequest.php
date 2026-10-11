<?php

namespace App\Http\Requests\User\Disbursement;

use App\Enums\DisbursementAcknowledgmentMode;
use App\Enums\DisbursementRecipientType;
use App\Enums\DisbursementVerificationMethod;
use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizeDisbursementTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('disbursementBatch');

        return $batch instanceof DisbursementBatch
            && ($this->user()?->can('issueReceipt', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'received_by_type' => ['required', Rule::enum(DisbursementRecipientType::class)],
            'received_by_name' => ['required', 'string', 'max:255'],
            'representative_relationship' => ['nullable', 'string', 'max:255'],
            'representative_verification' => ['nullable', 'string', 'max:2000'],
            'verification_method' => ['required', Rule::enum(DisbursementVerificationMethod::class)],
            'identity_verified' => ['accepted'],
            'acknowledgment_mode' => ['required', Rule::enum(DisbursementAcknowledgmentMode::class)],
            'alternative_reason' => ['nullable', 'string', 'max:2000'],
            'privacy_acknowledged' => ['accepted'],
            'camera_waiver_reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'witnessed_by' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.actual_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.actual_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
