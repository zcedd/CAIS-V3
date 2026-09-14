<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : null;

        return [
            'assistance_ids' => ['required', 'array', 'min:1'],
            'assistance_ids.*' => ['integer', 'distinct'],
            'assigned_to_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<Assistance>
     */
    public function assistances(): array
    {
        $department = $this->route('department');

        if (! $department instanceof Department) {
            return [];
        }

        return Assistance::query()
            ->whereIn('id', $this->input('assistance_ids', []))
            ->whereHas('program', fn ($query) => $query->where('department_id', $department->id))
            ->get()
            ->filter(fn (Assistance $assistance): bool => Gate::allows('assign', $assistance))
            ->all();
    }

    public function assignee(): ?User
    {
        $id = $this->integer('assigned_to_id');

        if ($id === 0) {
            return null;
        }

        return User::query()->find($id);
    }
}
