<?php

namespace App\Http\Requests\User\Distribution;

use App\Enums\DistributionEvidenceType;
use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistributionEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
            && ($this->user()?->can('execute', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
            'evidence_type' => ['required', Rule::enum(DistributionEvidenceType::class)],
            'capture_source' => ['nullable', 'string', 'in:camera'],
        ];
    }
}
