<?php

namespace App\Http\Requests\User\Assistance;

use App\Http\Requests\User\Concerns\EnsuresAssistanceBelongsToProgram;
use App\Models\Assistance;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignRequest extends FormRequest
{
    use EnsuresAssistanceBelongsToProgram;

    public function authorize(): bool
    {
        $this->ensureAssistanceBelongsToProgram();

        return Gate::allows('assign', $this->route('assistance'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Assistance $assistance */
        $assistance = $this->route('assistance');
        $departmentId = $assistance->program?->department_id;

        return [
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

    public function assignee(): ?User
    {
        $id = $this->integer('assigned_to_id');

        if ($id === 0) {
            return null;
        }

        return User::query()->find($id);
    }
}
