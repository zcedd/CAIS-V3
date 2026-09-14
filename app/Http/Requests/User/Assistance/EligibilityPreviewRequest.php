<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EligibilityPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $program = $this->route('program');

        if (! $program instanceof Program) {
            return false;
        }

        return Gate::allows('create', [Assistance::class, $program]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'beneficiary_id' => [
                'required',
                'integer',
                Rule::exists('beneficiaries', 'id'),
            ],
            'recorded_at' => ['nullable', 'date'],
            'item_details' => ['nullable', 'array'],
            'item_details.*.item_id' => ['required', 'integer'],
            'item_details.*.quantity' => ['required', 'integer', 'min:1'],
            'except_assistance_id' => ['nullable', 'integer', Rule::exists('assistances', 'id')],
        ];
    }
}
