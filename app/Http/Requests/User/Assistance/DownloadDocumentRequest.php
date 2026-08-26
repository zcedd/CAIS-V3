<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\AssistanceDocument;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DownloadDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('view', $this->assistance())
            && $this->assistanceBelongsToProgram()
            && $this->documentBelongsToAssistance();
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

    public function document(): AssistanceDocument
    {
        /** @var AssistanceDocument $document */
        $document = $this->route('document');

        return $document;
    }

    private function assistanceBelongsToProgram(): bool
    {
        $program = $this->route('program');

        return $program instanceof Program
            && $this->assistance()->program_id === $program->id;
    }

    private function documentBelongsToAssistance(): bool
    {
        return $this->document()->assistance_id === $this->assistance()->id;
    }
}
