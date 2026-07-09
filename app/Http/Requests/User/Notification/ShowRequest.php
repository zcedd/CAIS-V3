<?php

namespace App\Http\Requests\User\Notification;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class ShowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->userBelongsToDepartment()) {
            return false;
        }

        $notificationId = $this->route('notification');

        if (! is_string($notificationId)) {
            return false;
        }

        return $this->user()
            ->notifications()
            ->whereKey($notificationId)
            ->exists();
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
