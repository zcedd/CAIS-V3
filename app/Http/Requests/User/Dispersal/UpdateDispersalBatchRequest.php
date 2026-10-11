<?php

namespace App\Http\Requests\User\Dispersal;

use App\Enums\DispersalApprovalMode;
use App\Models\DispersalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDispersalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('dispersalBatch');

        return $batch instanceof DispersalBatch
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
            'approval_mode' => ['required', Rule::enum(DispersalApprovalMode::class)],
            'notes' => ['nullable', 'string'],
            'assistance_ids' => ['required', 'array', 'min:1'],
            'assistance_ids.*' => ['integer', 'distinct', 'exists:assistances,id'],
            'lock_version' => ['nullable', 'integer'],
        ];
    }
}
