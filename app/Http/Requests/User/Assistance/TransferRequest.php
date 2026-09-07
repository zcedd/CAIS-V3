<?php

namespace App\Http\Requests\User\Assistance;

use App\Http\Requests\User\Concerns\ValidatesAssistanceEligibility;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Program;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransferRequest extends FormRequest
{
    use ValidatesAssistanceEligibility;

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
                Rule::exists('programs', 'id')->where(function (Builder $query) use ($program): void {
                    $query->whereIn('id', Program::query()->transferTargetsFor($program)->select('id'));
                }),
            ],
            'reason' => ['required', 'string', 'max:255'],
            ...$this->eligibilityOverrideRules(),
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
            function (Validator $validator): void {
                $targetProgram = $this->targetProgram();
                $assistance = $this->route('assistance');

                if (! $targetProgram instanceof Program || ! $assistance instanceof Assistance) {
                    return;
                }

                $assistance->loadMissing(['beneficiary', 'assistanceItem']);

                $this->applyTransferEligibility($validator, $targetProgram, $assistance);
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
            'reason' => 'reason',
            ...$this->eligibilityOverrideAttributes(),
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
