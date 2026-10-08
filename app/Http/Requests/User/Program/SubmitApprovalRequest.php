<?php

namespace App\Http\Requests\User\Program;

use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SubmitApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $program = $this->route('program');

        return $program instanceof Program && Gate::allows('submit', $program);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'remark' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function remark(): ?string
    {
        $remark = $this->validated('remark');

        if (! is_string($remark)) {
            return null;
        }

        $remark = trim($remark);

        return $remark === '' ? null : $remark;
    }
}
