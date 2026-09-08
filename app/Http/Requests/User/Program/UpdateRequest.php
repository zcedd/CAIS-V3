<?php

namespace App\Http\Requests\User\Program;

use App\Http\Requests\User\Concerns\ValidatesProgramDocumentRequirements;
use App\Http\Requests\User\Concerns\ValidatesProgramEligibilityRules;
use App\Http\Requests\User\Concerns\ValidatesProgramFields;
use App\Http\Requests\User\Concerns\ValidatesPublicIntake;
use App\Models\Department;
use App\Models\Program;
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
    use ValidatesPublicIntake;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $program = $this->route('program');
        $department = $this->route('department');

        return $program instanceof Program
            && $department instanceof Department
            && $program->department_id === $department->id
            && Gate::allows('update', $program);
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
        $program = $this->route('program');
        $isScheme = $program instanceof Program && $program->isScheme();
        $isBatch = $program instanceof Program && $program->isBatch();

        $rules = [
            'name' => $isBatch ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'batch_name' => $isBatch ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'descriptions' => ['required', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_organization' => $isBatch ? ['prohibited'] : ['nullable', 'boolean'],
            'is_closed' => $isScheme ? ['prohibited'] : ['nullable', 'boolean'],
            ...$this->publicIntakeRules($isScheme),
            'fund_ids' => $isScheme ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'fund_ids.*' => [
                'integer',
                Rule::exists('funds', 'id')->where(
                    fn ($query) => $query->where('department_id', $program instanceof Program ? $program->department_id : $departmentId),
                ),
            ],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => [
                'integer',
                Rule::exists('items', 'id')->where(
                    fn ($query) => $query->where('department_id', $program instanceof Program ? $program->department_id : $departmentId),
                ),
            ],
            ...$this->programFieldDefinitionRules(),
            ...$this->programDocumentRequirementRules(),
            'workflow_id' => [
                'nullable',
                'integer',
                Rule::exists('workflows', 'id')->where(
                    fn ($query) => $query->where('department_id', $program instanceof Program ? $program->department_id : $departmentId),
                ),
            ],
        ];

        if (! $isBatch) {
            $rules = [
                ...$rules,
                ...$this->programEligibilityRules($this->input('item_ids', [])),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterProgramFieldDefinitions($validator);
        $this->afterProgramDocumentRequirements($validator);
        $this->afterPublicIntakeValidation($validator);

        $program = $this->route('program');

        if (! ($program instanceof Program && $program->isBatch())) {
            $this->afterProgramEligibilityRules($validator);
        }

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
            'batch_name' => 'batch name',
            'descriptions' => 'description',
            'start_at' => 'start date',
            'end_at' => 'end date',
            'is_organization' => 'organization program',
            'is_closed' => 'closed program',
            ...$this->publicIntakeAttributes(),
            'fund_ids' => 'funds',
            'item_ids' => 'items',
            ...$this->programEligibilityAttributes(),
            ...$this->programFieldDefinitionAttributes(),
            ...$this->programDocumentRequirementAttributes(),
        ];
    }
}
