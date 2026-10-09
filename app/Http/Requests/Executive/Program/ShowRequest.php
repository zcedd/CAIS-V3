<?php

namespace App\Http\Requests\Executive\Program;

use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $program = $this->route('program');

        return $program instanceof Program && Gate::allows('view', $program);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
