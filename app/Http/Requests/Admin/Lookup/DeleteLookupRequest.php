<?php

namespace App\Http\Requests\Admin\Lookup;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DeleteLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
