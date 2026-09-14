<?php

namespace App\Http\Requests\User\Assistance;

use App\Http\Requests\User\Concerns\EnsuresAssistanceBelongsToProgram;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EditRequest extends FormRequest
{
    use EnsuresAssistanceBelongsToProgram;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->ensureAssistanceBelongsToProgram();

        return Gate::allows('update', $this->assistance);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
