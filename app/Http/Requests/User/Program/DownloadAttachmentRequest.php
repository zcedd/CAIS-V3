<?php

namespace App\Http\Requests\User\Program;

use App\Models\Program;
use App\Models\ProgramAttachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DownloadAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $program = $this->route('program');
        $attachment = $this->route('attachment');

        return $program instanceof Program
            && $attachment instanceof ProgramAttachment
            && $attachment->program_id === $program->id
            && Gate::allows('view', $program);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
