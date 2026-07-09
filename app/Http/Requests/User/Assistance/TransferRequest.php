<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->assistance);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Program $program */
        $program = $this->route('program');

        return [
            'target_program_id' => [
                'required',
                'integer',
                Rule::exists('programs', 'id')
                    ->where('department_id', $program->department_id)
                    ->where('is_closed', false)
                    ->where('is_organization', $program->is_organization)
                    ->whereNot('id', $program->id),
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
                /** @var Program $program */
                $program = $this->route('program');

                if ($program->is_closed) {
                    $validator->errors()->add(
                        'program',
                        'This program is closed and assistances cannot be transferred.',
                    );
                }

                $targetProgram = $this->targetProgram();

                if ($targetProgram === null) {
                    return;
                }

                /** @var Assistance $assistance */
                $assistance = $this->route('assistance');

                $assistanceItemIds = AssistanceItem::query()
                    ->where('assistance_id', $assistance->id)
                    ->pluck('item_id')
                    ->all();

                if ($assistanceItemIds === []) {
                    return;
                }

                $targetProgramItemIds = $targetProgram->item()->pluck('items.id')->all();
                $missingItemIds = array_diff($assistanceItemIds, $targetProgramItemIds);

                if ($missingItemIds !== []) {
                    $validator->errors()->add(
                        'target_program_id',
                        'The selected program does not include all items on this assistance record.',
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
            'target_program_id' => 'target program',
        ];
    }

    public function targetProgram(): ?Program
    {
        $targetProgramId = $this->integer('target_program_id');

        if ($targetProgramId === 0) {
            return null;
        }

        return Program::query()->find($targetProgramId);
    }
}
