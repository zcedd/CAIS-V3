<?php

namespace App\Services\User;

use App\Models\Program;
use App\Models\ProgramDocumentRequirement;

class ProgramDocumentRequirementService
{
    /**
     * @param  list<array{
     *     id?: int|null,
     *     document_type_id: int,
     *     is_required?: bool,
     *     required_before: string,
     *     sort_order?: int
     * }>  $requirements
     */
    public function syncForProgram(Program $program, array $requirements): void
    {
        $existing = $program->documentRequirements()->get()->keyBy('id');
        $retainedIds = [];

        foreach (array_values($requirements) as $index => $requirementData) {
            $requirementId = isset($requirementData['id']) ? (int) $requirementData['id'] : null;
            $payload = [
                'document_type_id' => (int) $requirementData['document_type_id'],
                'is_required' => (bool) ($requirementData['is_required'] ?? true),
                'required_before' => $requirementData['required_before'],
                'sort_order' => (int) ($requirementData['sort_order'] ?? $index),
            ];

            if ($requirementId !== null && $existing->has($requirementId)) {
                /** @var ProgramDocumentRequirement $requirement */
                $requirement = $existing->get($requirementId);
                $requirement->update($payload);
                $retainedIds[] = $requirementId;

                continue;
            }

            $program->documentRequirements()->create($payload);
        }

        $idsToDelete = $existing->keys()
            ->reject(fn (int|string $id): bool => in_array((int) $id, $retainedIds, true))
            ->all();

        if ($idsToDelete !== []) {
            ProgramDocumentRequirement::query()
                ->where('program_id', $program->id)
                ->whereIn('id', $idsToDelete)
                ->delete();
        }
    }

    public function copyToProgram(Program $source, Program $target): void
    {
        $source->loadMissing('documentRequirements');

        foreach ($source->documentRequirements as $requirement) {
            $target->documentRequirements()->create([
                'document_type_id' => $requirement->document_type_id,
                'is_required' => $requirement->is_required,
                'required_before' => $requirement->required_before,
                'sort_order' => $requirement->sort_order,
            ]);
        }
    }

    /**
     * @return list<array{
     *     id: int,
     *     document_type_id: int,
     *     is_required: bool,
     *     required_before: string,
     *     sort_order: int
     * }>
     */
    public function requirementsPayload(Program $program): array
    {
        return $program->documentRequirements()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static fn (ProgramDocumentRequirement $requirement): array => [
                'id' => $requirement->id,
                'document_type_id' => $requirement->document_type_id,
                'is_required' => $requirement->is_required,
                'required_before' => $requirement->required_before,
                'sort_order' => $requirement->sort_order,
            ])
            ->values()
            ->all();
    }
}
