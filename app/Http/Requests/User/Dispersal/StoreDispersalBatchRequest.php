<?php

namespace App\Http\Requests\User\Dispersal;

use App\Enums\DispersalApprovalMode;
use App\Models\Department;
use App\Models\DispersalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDispersalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && ($this->user()?->can('create', [DispersalBatch::class, $department]) ?? false);
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
        ];
    }
}
