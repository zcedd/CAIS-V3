<?php

namespace App\Http\Requests\User\Notification;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->userBelongsToDepartment();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');
        $user = $this->user();

        if (! $department instanceof Department || $user === null) {
            return false;
        }

        return $user->department_id === $department->id;
    }
}
