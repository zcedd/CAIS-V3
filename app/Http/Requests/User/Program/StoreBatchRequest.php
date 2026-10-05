<?php

namespace App\Http\Requests\User\Program;

use App\Enums\ProgramKind;
use App\Http\Requests\User\Concerns\ValidatesPublicIntake;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBatchRequest extends FormRequest
{
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $program = $this->route('program');
        $departmentId = $program instanceof Program ? $program->department_id : null;

        return [
            'batch_name' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'fund_ids' => ['required', 'array', 'min:1'],
            'fund_ids.*' => [
                'integer',
                Rule::exists('funds', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
            ...$this->publicIntakeRules(),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $program = $this->route('program');

                if (! $program instanceof Program) {
                    return;
                }

                if (! $program->isScheme()) {
                    $validator->errors()->add(
                        'program',
                        'Batches can only be added to a parent program.',
                    );
                }

                if ($program->kind === ProgramKind::Batch) {
                    $validator->errors()->add(
                        'program',
                        'A batch cannot have child batches.',
                    );
                }

                if ($this->boolean('public_intake') && $program->is_organization) {
                    $validator->errors()->add(
                        'public_intake',
                        'Public intake is only available for individual programs.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'batch_name' => 'batch name',
            'start_at' => 'start date',
            'end_at' => 'end date',
            'fund_ids' => 'funds',
            ...$this->publicIntakeAttributes(),
        ];
    }
}
