<?php

namespace App\Http\Requests\User\Concerns;

use App\Enums\DocumentRequirementMilestone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesProgramDocumentRequirements
{
    /**
     * @return array<string, mixed>
     */
    protected function programDocumentRequirementRules(): array
    {
        return [
            'document_requirements' => ['nullable', 'array'],
            'document_requirements.*.id' => ['nullable', 'integer'],
            'document_requirements.*.document_type_id' => [
                'required',
                'integer',
                Rule::exists('document_types', 'id'),
            ],
            'document_requirements.*.is_required' => ['nullable', 'boolean'],
            'document_requirements.*.required_before' => [
                'required',
                Rule::enum(DocumentRequirementMilestone::class),
            ],
            'document_requirements.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function programDocumentRequirementAttributes(): array
    {
        return [
            'document_requirements' => 'document checklist',
            'document_requirements.*.document_type_id' => 'document type',
            'document_requirements.*.is_required' => 'required document',
            'document_requirements.*.required_before' => 'required before',
            'document_requirements.*.sort_order' => 'document order',
        ];
    }

    protected function afterProgramDocumentRequirements(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $requirements = $this->input('document_requirements', []);

            if (! is_array($requirements)) {
                return;
            }

            $seenTypeIds = [];

            foreach ($requirements as $index => $requirement) {
                if (! is_array($requirement)) {
                    continue;
                }

                $typeId = (int) ($requirement['document_type_id'] ?? 0);

                if ($typeId === 0) {
                    continue;
                }

                if (in_array($typeId, $seenTypeIds, true)) {
                    $validator->errors()->add(
                        "document_requirements.{$index}.document_type_id",
                        'Each document type can only appear once on the checklist.',
                    );

                    continue;
                }

                $seenTypeIds[] = $typeId;
            }
        });
    }
}
