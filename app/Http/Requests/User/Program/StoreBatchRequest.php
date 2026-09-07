<?php

namespace App\Http\Requests\User\Program;

use App\Models\Department;
use App\Models\Program;
use App\Support\ProgramKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $program = $this->route('program');

        return $program instanceof Program && Gate::allows('update', $program);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : null;

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
        ];
    }
}
