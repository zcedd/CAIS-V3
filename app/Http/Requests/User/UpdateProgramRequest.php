<?php

namespace App\Http\Requests\User;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'descriptions' => ['required', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_organization' => ['nullable', 'boolean'],
            'is_closed' => ['nullable', 'boolean'],
            'fund_ids' => ['required', 'array', 'min:1'],
            'fund_ids.*' => [
                'integer',
                Rule::exists('funds', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => [
                'integer',
                Rule::exists('items', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'program name',
            'descriptions' => 'description',
            'start_at' => 'start date',
            'end_at' => 'end date',
            'is_organization' => 'organization program',
            'is_closed' => 'closed program',
            'fund_ids' => 'funds',
            'item_ids' => 'items',
        ];
    }
}
