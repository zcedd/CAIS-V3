<?php

namespace App\Http\Requests\User\Concerns;

use App\Enums\ProgramKind;
use App\Models\Program;
use Illuminate\Validation\Validator;

trait ValidatesPublicIntake
{
    /**
     * @return array<string, mixed>
     */
    protected function publicIntakeRules(bool $prohibited): array
    {
        return [
            'public_intake' => $prohibited
                ? ['prohibited']
                : ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function firstBatchPublicIntakeRules(): array
    {
        return [
            'first_batch.public_intake' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function publicIntakeAttributes(): array
    {
        return [
            'public_intake' => 'public intake',
            'first_batch.public_intake' => 'batch public intake',
        ];
    }

    protected function afterPublicIntakeValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $program = $this->route('program');
            $kind = $program instanceof Program
                ? $program->kind
                : (ProgramKind::tryFrom((string) $this->input('kind', ProgramKind::Standalone->value)) ?? ProgramKind::Standalone);
            $isOrganization = $program instanceof Program
                ? (bool) $program->is_organization
                : (bool) $this->boolean('is_organization');

            if ($this->boolean('public_intake') && $kind === ProgramKind::Scheme) {
                $validator->errors()->add(
                    'public_intake',
                    'Public intake cannot be enabled on a parent program.',
                );
            }

            if ($this->boolean('public_intake') && $isOrganization) {
                $validator->errors()->add(
                    'public_intake',
                    'Public intake is only available for individual programs.',
                );
            }

            if ($this->boolean('first_batch.public_intake') && $isOrganization) {
                $validator->errors()->add(
                    'first_batch.public_intake',
                    'Public intake is only available for individual programs.',
                );
            }
        });
    }
}
