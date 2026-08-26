<?php

namespace App\Http\Requests\User\Program;

use App\Http\Requests\User\Concerns\ValidatesProgramDocumentRequirements;
use App\Http\Requests\User\Concerns\ValidatesProgramEligibilityRules;
use App\Http\Requests\User\Concerns\ValidatesProgramFields;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRequest extends FormRequest
{
    use ValidatesProgramDocumentRequirements;
    use ValidatesProgramEligibilityRules;
    use ValidatesProgramFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->program);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'descriptions' => ['required', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_organization' => ['nullable', 'boolean'],
            'is_closed' => ['nullable', 'boolean'],
            'fund_ids' => ['required', 'array', 'min:1'],
            'fund_ids.*' => [
                'integer',
                Rule::exists('funds', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => [
                'integer',
                Rule::exists('items', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
            ...$this->programEligibilityRules($this->input('item_ids', [])),
            ...$this->programFieldDefinitionRules(),
            ...$this->programDocumentRequirementRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterProgramFieldDefinitions($validator);
        $this->afterProgramEligibilityRules($validator);
        $this->afterProgramDocumentRequirements($validator);

        $validator->after(function (Validator $validator): void {
            $program = $this->program;
            $fields = $this->input('fields', []);

            if (! is_array($fields) || $program === null) {
                return;
            }

            $ownedIds = $program->fields()->pluck('id')->all();

            foreach ($fields as $index => $field) {
                if (! is_array($field) || ! isset($field['id'])) {
                    continue;
                }

                if (! in_array((int) $field['id'], $ownedIds, true)) {
                    $validator->errors()->add(
                        "fields.{$index}.id",
                        'The selected custom field is invalid for this program.',
                    );
                }
            }

            $requirements = $this->input('document_requirements', []);

            if (! is_array($requirements)) {
                return;
            }

            $ownedRequirementIds = $program->documentRequirements()->pluck('id')->all();

            foreach ($requirements as $index => $requirement) {
                if (! is_array($requirement) || ! isset($requirement['id'])) {
                    continue;
                }

                if (! in_array((int) $requirement['id'], $ownedRequirementIds, true)) {
                    $validator->errors()->add(
                        "document_requirements.{$index}.id",
                        'The selected document requirement is invalid for this program.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'program name',
            'descriptions' => 'description',
            'start_at' => 'start date',
            'end_at' => 'end date',
            'is_organization' => 'organization program',
            'is_closed' => 'closed program',
            'fund_ids' => 'funds',
            'item_ids' => 'items',
            ...$this->programEligibilityAttributes(),
            ...$this->programFieldDefinitionAttributes(),
            ...$this->programDocumentRequirementAttributes(),
        ];
    }
}
