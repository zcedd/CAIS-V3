<?php

namespace App\Http\Requests\Admin\Workflow;

use App\Models\Assistance;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReassignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('workflow');
        $assistance = $this->route('assistance');

        return $workflow instanceof Workflow
            && $assistance instanceof Assistance
            && Gate::allows('reassignTask', $workflow);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
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
