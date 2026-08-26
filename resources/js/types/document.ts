export type DocumentRequirementMilestone = 'verified' | 'delivered';

export type DocumentTypeOption = {
    id: number;
    name: string;
    slug: string;
};

export type ProgramDocumentRequirementInput = {
    id?: number | null;
    document_type_id: number | null;
    is_required: boolean;
    required_before: DocumentRequirementMilestone;
    sort_order: number;
};

export type AssistanceDocumentFile = {
    id: number;
    document_type_id: number;
    document_type_name: string;
    original_name: string;
    mime_type: string;
    size: number;
    notes: string | null;
    uploaded_by_name: string | null;
    created_at: string;
    is_image: boolean;
};

export type AssistanceDocumentChecklistItem = {
    id: number;
    document_type_id: number;
    document_type_name: string;
    document_type_slug: string;
    is_required: boolean;
    required_before: DocumentRequirementMilestone;
    is_complete: boolean;
    documents: AssistanceDocumentFile[];
};

export type AssistanceDocumentsPayload = {
    checklist: AssistanceDocumentChecklistItem[];
    additional_documents: AssistanceDocumentFile[];
    required_complete: boolean;
    missing_for_verified: string[];
    missing_for_delivered: string[];
};

export const DOCUMENT_REQUIREMENT_MILESTONES: {
    value: DocumentRequirementMilestone;
    label: string;
}[] = [
    { value: 'verified', label: 'Before Verified' },
    { value: 'delivered', label: 'Before Delivered' },
];

export function createEmptyDocumentRequirement(
    sortOrder = 0,
    documentTypeId: number | null = null,
): ProgramDocumentRequirementInput {
    return {
        id: null,
        document_type_id: documentTypeId,
        is_required: true,
        required_before: 'verified',
        sort_order: sortOrder,
    };
}
