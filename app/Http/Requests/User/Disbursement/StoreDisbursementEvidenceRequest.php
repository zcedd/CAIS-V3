<?php

namespace App\Http\Requests\User\Disbursement;

use App\Enums\DisbursementEvidenceType;
use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisbursementEvidenceRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:5120'],
            'evidence_type' => ['required', Rule::enum(DisbursementEvidenceType::class)],
            'capture_source' => ['nullable', 'string', 'in:camera'],
        ];
    }
}
