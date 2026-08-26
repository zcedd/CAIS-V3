<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assistance = $this->assistance();
        $program = $this->route('program');

        return Gate::allows('view', $assistance)
            && $program instanceof Program
            && $assistance->program_id === $program->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function assistance(): Assistance
    {
        /** @var Assistance $assistance */
        $assistance = $this->route('assistance');

        return $assistance;
    }
}
