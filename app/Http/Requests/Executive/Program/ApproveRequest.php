<?php

namespace App\Http\Requests\Executive\Program;

use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $program = $this->route('program');

        return $program instanceof Program && Gate::allows('approve', $program);
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
