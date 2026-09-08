<?php

namespace App\Http\Requests\Public\Intake;

use App\Http\Requests\User\Concerns\ValidatesProgramFields;
use App\Models\Program;
use App\Services\Public\PublicIntakeService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
{
    use ValidatesProgramFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $program = $this->route('program');
        $programItemIds = $program instanceof Program
            ? $program->item()->pluck('items.id')->all()
            : [];

        return [
            'intent' => ['required', 'string', Rule::in([
                PublicIntakeService::IntentSave,
                PublicIntakeService::IntentSubmit,
            ])],
            'consent' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('intent') === PublicIntakeService::IntentSubmit,
                ),
                Rule::when(
                    $this->input('intent') === PublicIntakeService::IntentSubmit,
                    ['accepted'],
                ),
            ],
            'confirmed_beneficiary_id' => ['nullable', 'integer', Rule::exists('beneficiaries', 'id')],
            'create_new' => ['nullable', 'boolean'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'birthday' => ['required', 'date'],
            'sex' => ['required', 'string', Rule::in(['Male', 'Female'])],
            'other_address' => ['nullable', 'string', 'max:500'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'indigenous' => ['nullable', 'boolean'],
            'ethnicity' => ['nullable', 'string', 'max:255'],
            'pwd' => ['nullable', 'boolean'],
            'is_4ps_beneficiary' => ['nullable', 'boolean'],
            'is_solo_parent' => ['nullable', 'boolean'],
            'address_barangay_id' => ['required', 'integer', Rule::exists('address_barangays', 'id')],
            'remark' => ['nullable', 'string'],
            'item_details' => ['required', 'array', 'min:1'],
            'item_details.*.item_id' => ['required', 'integer', Rule::in($programItemIds)],
            'item_details.*.quantity' => ['required', 'integer', 'min:1'],
            'item_details.*.specification' => ['nullable', 'string', 'max:255'],
            ...($program instanceof Program ? $this->assistanceFieldValueRules($program) : []),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $program = $this->route('program');

        if ($program instanceof Program) {
            $this->afterAssistanceFieldValues($validator, $program);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'address_barangay_id' => 'barangay',
            'confirmed_beneficiary_id' => 'matching profile',
            'item_details' => 'requested items',
            ...$this->assistanceFieldValueAttributes(),
        ];
    }
}
