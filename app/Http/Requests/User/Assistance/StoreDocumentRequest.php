<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->assistance())
            && $this->assistanceBelongsToProgram();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')],
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document_type_id' => 'document type',
            'file' => 'document',
            'notes' => 'notes',
        ];
    }

    public function assistance(): Assistance
    {
        /** @var Assistance $assistance */
        $assistance = $this->route('assistance');

        return $assistance;
    }

    private function assistanceBelongsToProgram(): bool
    {
        $program = $this->route('program');

        return $program instanceof Program
            && $this->assistance()->program_id === $program->id;
    }
}
