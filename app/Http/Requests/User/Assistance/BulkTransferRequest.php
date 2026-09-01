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

class BulkTransferRequest extends FormRequest
{
    use ValidatesAssistanceEligibility;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Program $program */
        $program = $this->route('program');

        return $this->user()?->department_id === $program->department_id;
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
            'assistance_ids' => ['required', 'array', 'min:1'],
            'assistance_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('assistances', 'id')
                    ->where('program_id', $program->id),
            ],
            'target_program_id' => [
                'required',
                'integer',
                Rule::exists('programs', 'id')->where(function (Builder $query) use ($program): void {
                    $query
                        ->where('department_id', $program->department_id)
                        ->where('is_closed', false)
                        ->where('is_organization', $program->is_organization)
                        ->whereNot('id', $program->id);
                }),
            ],
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

                foreach ($this->assistances() as $assistance) {
                    if (! Gate::allows('update', $assistance)) {
                        $validator->errors()->add(
                            'assistance_ids',
                            'You are not authorized to transfer one or more selected assistance records.',
                        );

                        break;
                    }
                }

                $targetProgramItemIds = $targetProgram->item()->pluck('items.id')->all();

                foreach ($this->assistances() as $assistance) {
                    $assistanceItemIds = AssistanceItem::query()
                        ->where('assistance_id', $assistance->id)
                        ->pluck('item_id')
                        ->all();

                    if ($assistanceItemIds === []) {
                        continue;
                    }

                    $missingItemIds = array_diff($assistanceItemIds, $targetProgramItemIds);

                    if ($missingItemIds !== []) {
                        $validator->errors()->add(
                            'target_program_id',
                            'The selected program does not include all items on one or more assistance records.',
                        );

                        break;
                    }
                }
            },
            function (Validator $validator): void {
                $targetProgram = $this->targetProgram();

                if (! $targetProgram instanceof Program) {
                    return;
                }

                foreach ($this->assistances() as $assistance) {
                    $assistance->loadMissing(['beneficiary', 'assistanceItem']);
                    $this->applyTransferEligibility($validator, $targetProgram, $assistance);
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
            'assistance_ids' => 'selected assistance records',
            'assistance_ids.*' => 'assistance record',
            'target_program_id' => 'target program',
            ...$this->eligibilityOverrideAttributes(),
        ];
    }

    /**
     * @return list<Assistance>
     */
    public function assistances(): array
    {
        /** @var Program $program */
        $program = $this->route('program');

        return Assistance::query()
            ->where('program_id', $program->id)
            ->whereIn('id', $this->input('assistance_ids', []))
            ->get()
            ->all();
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
