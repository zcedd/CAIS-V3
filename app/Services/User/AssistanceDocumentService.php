<?php

namespace App\Services\User;

use App\Models\Assistance;
use App\Models\AssistanceDocument;
use App\Models\DocumentType;
use App\Models\Program;
use App\Models\ProgramDocumentRequirement;
use App\Models\RequestSubStatus;
use App\Support\DocumentRequirementMilestone;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AssistanceDocumentService
{
    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function documentTypesForSelect(): array
    {
        return DocumentType::query()
            ->orderBy('id')
            ->get(['id', 'name', 'slug'])
            ->map(static fn (DocumentType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     checklist: list<array{
     *         id: int,
     *         document_type_id: int,
     *         document_type_name: string,
     *         document_type_slug: string,
     *         is_required: bool,
     *         required_before: string,
     *         is_complete: bool,
     *         documents: list<array<string, mixed>>
     *     }>,
     *     additional_documents: list<array<string, mixed>>,
     *     required_complete: bool,
     *     missing_for_verified: list<string>,
     *     missing_for_delivered: list<string>
     * }
     */
    public function profilePayload(Assistance $assistance): array
    {
        $assistance->loadMissing([
            'program.documentRequirements.documentType:id,name,slug',
            'documents.documentType:id,name,slug',
            'documents.uploader:id,firstName,lastName,email',
        ]);

        $documentsByType = $assistance->documents
            ->groupBy('document_type_id');

        $usedTypeIds = [];
        $checklist = [];

        foreach ($this->requirementsForProgram($assistance->program) as $requirement) {
            $typeDocuments = $documentsByType->get($requirement->document_type_id, collect());
            $usedTypeIds[] = $requirement->document_type_id;

            $checklist[] = [
                'id' => $requirement->id,
                'document_type_id' => $requirement->document_type_id,
                'document_type_name' => $requirement->documentType?->name ?? 'Document',
                'document_type_slug' => $requirement->documentType?->slug ?? '',
                'is_required' => $requirement->is_required,
                'required_before' => $requirement->required_before,
                'is_complete' => $typeDocuments->isNotEmpty(),
                'documents' => $typeDocuments
                    ->map(fn (AssistanceDocument $document): array => $this->documentPayload($document))
                    ->values()
                    ->all(),
            ];
        }

        $additional = $assistance->documents
            ->reject(static fn (AssistanceDocument $document): bool => in_array($document->document_type_id, $usedTypeIds, true))
            ->map(fn (AssistanceDocument $document): array => $this->documentPayload($document))
            ->values()
            ->all();

        $missingForVerified = $this->missingRequiredLabels($assistance, DocumentRequirementMilestone::Verified);
        $missingForDelivered = $this->missingRequiredLabels($assistance, DocumentRequirementMilestone::Delivered);

        return [
            'checklist' => $checklist,
            'additional_documents' => $additional,
            'required_complete' => $missingForVerified === [] && $missingForDelivered === [],
            'missing_for_verified' => $missingForVerified,
            'missing_for_delivered' => $missingForDelivered,
        ];
    }

    /**
     * @return list<string>
     */
    public function missingRequiredLabels(Assistance $assistance, string $milestone): array
    {
        $assistance->loadMissing([
            'program.documentRequirements.documentType:id,name,slug',
        ]);

        $uploadedTypeIds = $assistance->documents()
            ->pluck('document_type_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->requirementsForMilestone($assistance->program, $milestone)
            ->filter(static fn (ProgramDocumentRequirement $requirement): bool => $requirement->is_required)
            ->reject(static fn (ProgramDocumentRequirement $requirement): bool => in_array((int) $requirement->document_type_id, $uploadedTypeIds, true))
            ->map(static fn (ProgramDocumentRequirement $requirement): string => $requirement->documentType?->name ?? 'Document')
            ->values()
            ->all();
    }

    public function assertCompleteForSubStatus(Assistance $assistance, RequestSubStatus $subStatus): void
    {
        $milestone = $this->milestoneForSubStatus($subStatus);

        if ($milestone === null) {
            return;
        }

        $missing = $this->missingRequiredLabels($assistance, $milestone);

        if ($missing === []) {
            return;
        }

        $this->failForMissingDocuments($this->missingDocumentsMessage($missing, $milestone));
    }

    /**
     * @param  list<Assistance>  $assistances
     */
    public function assertCompleteForAssistances(array $assistances, RequestSubStatus $subStatus): void
    {
        $milestone = $this->milestoneForSubStatus($subStatus);

        if ($milestone === null) {
            return;
        }

        $messages = [];

        foreach ($assistances as $assistance) {
            $missing = $this->missingRequiredLabels($assistance, $milestone);

            if ($missing === []) {
                continue;
            }

            $label = $assistance->beneficiary?->cais_number
                ?? 'Assistance #'.$assistance->id;

            $messages[] = $label.': '.implode(', ', $missing);
        }

        if ($messages === []) {
            return;
        }

        $this->failForMissingDocuments('Required documents are missing. '.implode(' ', $messages));
    }

    public function milestoneForSubStatus(RequestSubStatus $subStatus): ?string
    {
        $subStatus->loadMissing('requestStatus:id,name');

        if ($subStatus->name === 'Verified') {
            return DocumentRequirementMilestone::Verified;
        }

        if ($subStatus->requestStatus?->name === 'Delivered') {
            return DocumentRequirementMilestone::Delivered;
        }

        return null;
    }

    /**
     * @return array{
     *     id: int,
     *     document_type_id: int,
     *     document_type_name: string,
     *     original_name: string,
     *     mime_type: string,
     *     size: int,
     *     notes: string|null,
     *     uploaded_by_name: string|null,
     *     created_at: string,
     *     is_image: bool
     * }
     */
    public function documentPayload(AssistanceDocument $document): array
    {
        $document->loadMissing([
            'documentType:id,name',
            'uploader:id,firstName,lastName,email',
        ]);

        return [
            'id' => $document->id,
            'document_type_id' => $document->document_type_id,
            'document_type_name' => $document->documentType?->name ?? 'Document',
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'notes' => $document->notes,
            'uploaded_by_name' => $this->uploaderName($document),
            'created_at' => $document->created_at?->toDateTimeString(),
            'is_image' => str_starts_with($document->mime_type, 'image/'),
        ];
    }

    /**
     * @return Collection<int, ProgramDocumentRequirement>
     */
    private function requirementsForProgram(?Program $program): Collection
    {
        if ($program === null) {
            return collect();
        }

        $program->loadMissing('documentRequirements.documentType:id,name,slug');

        return $program->documentRequirements
            ->sortBy(['sort_order', 'id'])
            ->values();
    }

    /**
     * @return Collection<int, ProgramDocumentRequirement>
     */
    private function requirementsForMilestone(?Program $program, string $milestone): Collection
    {
        $milestones = $milestone === DocumentRequirementMilestone::Delivered
            ? [DocumentRequirementMilestone::Verified, DocumentRequirementMilestone::Delivered]
            : [DocumentRequirementMilestone::Verified];

        return $this->requirementsForProgram($program)
            ->filter(static fn (ProgramDocumentRequirement $requirement): bool => in_array($requirement->required_before, $milestones, true));
    }

    /**
     * @param  list<string>  $missing
     */
    private function missingDocumentsMessage(array $missing, string $milestone): string
    {
        $stage = $milestone === DocumentRequirementMilestone::Delivered
            ? 'Delivered'
            : 'Verified';

        return 'Upload required documents before marking this request as '.$stage.': '.implode(', ', $missing).'.';
    }

    private function failForMissingDocuments(string $message): never
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        throw ValidationException::withMessages([
            'request_sub_status_id' => $message,
        ]);
    }

    private function uploaderName(AssistanceDocument $document): ?string
    {
        $uploader = $document->uploader;

        if ($uploader === null) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $uploader->firstName ?? null,
            $uploader->lastName ?? null,
        ])));

        return $name !== '' ? $name : $uploader->email;
    }
}
