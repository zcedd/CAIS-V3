<?php

namespace App\Http\Requests\User\Distribution;

use App\Models\Department;
use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;

class StoreDistributionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && ($this->user()?->can('create', [DistributionBatch::class, $department]) ?? false);
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
            'notes' => ['nullable', 'string'],
            'assistance_ids' => ['required', 'array', 'min:1'],
            'assistance_ids.*' => ['integer', 'distinct', 'exists:assistances,id'],
        ];
    }
}
