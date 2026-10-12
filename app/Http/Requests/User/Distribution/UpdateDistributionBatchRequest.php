<?php

namespace App\Http\Requests\User\Distribution;

use App\Enums\DistributionApprovalMode;
use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistributionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
            && ($this->user()?->can('update', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:255'],
            'approval_mode' => ['required', Rule::enum(DistributionApprovalMode::class)],
            'notes' => ['nullable', 'string'],
            'assistance_ids' => ['required', 'array', 'min:1'],
            'assistance_ids.*' => ['integer', 'distinct', 'exists:assistances,id'],
            'lock_version' => ['nullable', 'integer'],
        ];
    }
}
