<?php

namespace App\Http\Requests\User\Program;

use App\Http\Requests\User\Concerns\ValidatesProgramDocumentRequirements;
use App\Http\Requests\User\Concerns\ValidatesProgramEligibilityRules;
use App\Http\Requests\User\Concerns\ValidatesProgramFields;
use App\Http\Requests\User\Concerns\ValidatesPublicIntake;
use App\Models\Department;
use App\Models\Program;
use App\Support\ProgramKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
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
        return Gate::allows('create', [Program::class, $this->route('department')]);
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
        $isScheme = $this->input('kind', ProgramKind::Standalone) === ProgramKind::Scheme;

        return [
            'name' => ['required', 'string', 'max:255'],
            'descriptions' => ['required', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_organization' => ['nullable', 'boolean'],
            'kind' => ['nullable', 'string', Rule::in(ProgramKind::creatableValues())],
            ...$this->publicIntakeRules($isScheme),
            'fund_ids' => $isScheme ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
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
            'first_batch' => ['nullable', 'array'],
            'first_batch.batch_name' => ['required_with:first_batch', 'string', 'max:255'],
            'first_batch.start_at' => ['required_with:first_batch', 'date'],
            'first_batch.end_at' => ['nullable', 'date', 'after_or_equal:first_batch.start_at'],
            'first_batch.fund_ids' => ['required_with:first_batch', 'array', 'min:1'],
            'first_batch.fund_ids.*' => [
                'integer',
                Rule::exists('funds', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
            ...$this->firstBatchPublicIntakeRules(),
            ...$this->programEligibilityRules($this->input('item_ids', [])),
            ...$this->programFieldDefinitionRules(),
            ...$this->programDocumentRequirementRules(),
            'workflow_id' => [
                'nullable',
                'integer',
                Rule::exists('workflows', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterProgramFieldDefinitions($validator);
        $this->afterProgramEligibilityRules($validator);
        $this->afterProgramDocumentRequirements($validator);
        $this->afterPublicIntakeValidation($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->filled('first_batch') && $this->input('kind') !== ProgramKind::Scheme) {
                $validator->errors()->add(
                    'first_batch',
                    'A first batch can only be created with a parent program.',
                );
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
            'public_intake' => 'public intake',
            'kind' => 'program type',
            'fund_ids' => 'funds',
            'item_ids' => 'items',
            'first_batch' => 'first batch',
            'first_batch.batch_name' => 'batch name',
            'first_batch.start_at' => 'batch start date',
            'first_batch.end_at' => 'batch end date',
            'first_batch.fund_ids' => 'batch funds',
            ...$this->publicIntakeAttributes(),
            ...$this->programEligibilityAttributes(),
            ...$this->programFieldDefinitionAttributes(),
            ...$this->programDocumentRequirementAttributes(),
        ];
    }
}
