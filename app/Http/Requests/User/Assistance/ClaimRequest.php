<?php

namespace App\Http\Requests\User\Assistance;

use App\Http\Requests\User\Concerns\EnsuresAssistanceBelongsToProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ClaimRequest extends FormRequest
{
    use EnsuresAssistanceBelongsToProgram;

    public function authorize(): bool
    {
        $this->ensureAssistanceBelongsToProgram();

        return Gate::allows('claim', $this->route('assistance'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
