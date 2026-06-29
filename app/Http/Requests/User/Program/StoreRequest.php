<?php

namespace App\Http\Requests\User\Program;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', [Program::class, $this->route('department')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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
            'fund_ids' => 'funds',
            'item_ids' => 'items',
        ];
    }
}
