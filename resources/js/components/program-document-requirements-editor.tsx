import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DOCUMENT_REQUIREMENT_MILESTONES,
    createEmptyDocumentRequirement,
    type DocumentTypeOption,
    type ProgramDocumentRequirementInput,
} from '@/types/document';
import { Plus, Trash2 } from 'lucide-react';

type ProgramDocumentRequirementsEditorProps = {
    requirements: ProgramDocumentRequirementInput[];
    documentTypes: DocumentTypeOption[];
    onChange: (requirements: ProgramDocumentRequirementInput[]) => void;
    errors?: Record<string, string>;
};

export function ProgramDocumentRequirementsEditor({
    requirements,
    documentTypes,
    onChange,
    errors = {},
}: ProgramDocumentRequirementsEditorProps) {
    const usedTypeIds = new Set(
        requirements
            .map((requirement) => requirement.document_type_id)
            .filter((id): id is number => id !== null),
    );

    const updateRequirement = (
        index: number,
        patch: Partial<ProgramDocumentRequirementInput>,
    ) => {
        onChange(
            requirements.map((requirement, requirementIndex) =>
                requirementIndex === index
                    ? { ...requirement, ...patch }
                    : requirement,
            ),
        );
    };

    const removeRequirement = (index: number) => {
        onChange(
            requirements
                .filter((_, requirementIndex) => requirementIndex !== index)
                .map((requirement, sortOrder) => ({
                    ...requirement,
                    sort_order: sortOrder,
                })),
        );
    };

    const addRequirement = () => {
        const nextType = documentTypes.find(
            (type) => !usedTypeIds.has(type.id),
        );

        onChange([
            ...requirements,
            createEmptyDocumentRequirement(requirements.length, nextType?.id ?? null),
        ]);
    };

    const canAdd = documentTypes.some((type) => !usedTypeIds.has(type.id));

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <div className="space-y-1">
                    <Label>Document checklist</Label>
                    <p className="text-sm text-muted-foreground">
                        Require IDs, indigency, delivery photos, or a signed
                        acknowledgment before Verified or Delivered.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={addRequirement}
                    disabled={!canAdd}
                >
                    <Plus className="size-4" />
                    Add document
                </Button>
            </div>

            <InputError message={errors.document_requirements} />

            {requirements.length === 0 ? (
                <p className="rounded-xl border border-dashed px-3 py-4 text-sm text-muted-foreground">
                    No required documents yet. Add types that staff must attach
                    on each assistance request.
                </p>
            ) : (
                <div className="space-y-3">
                    {requirements.map((requirement, index) => {
                        const availableTypes = documentTypes.filter(
                            (type) =>
                                type.id === requirement.document_type_id ||
                                !usedTypeIds.has(type.id),
                        );

                        return (
                            <div
                                key={requirement.id ?? `new-${index}`}
                                className="space-y-3 rounded-xl border p-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="text-sm font-medium">
                                        Document {index + 1}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 text-muted-foreground"
                                        onClick={() => removeRequirement(index)}
                                        aria-label={`Remove document ${index + 1}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`program-document-type-${index}`}
                                        >
                                            Type
                                        </Label>
                                        <Select
                                            value={
                                                requirement.document_type_id
                                                    ? String(
                                                          requirement.document_type_id,
                                                      )
                                                    : ''
                                            }
                                            onValueChange={(value) =>
                                                updateRequirement(index, {
                                                    document_type_id:
                                                        Number(value),
                                                })
                                            }
                                        >
                                            <SelectTrigger
                                                id={`program-document-type-${index}`}
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select document type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableTypes.map((type) => (
                                                    <SelectItem
                                                        key={type.id}
                                                        value={String(type.id)}
                                                    >
                                                        {type.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                errors[
                                                    `document_requirements.${index}.document_type_id`
                                                ]
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`program-document-before-${index}`}
                                        >
                                            Required before
                                        </Label>
                                        <Select
                                            value={requirement.required_before}
                                            onValueChange={(value) =>
                                                updateRequirement(index, {
                                                    required_before:
                                                        value as ProgramDocumentRequirementInput['required_before'],
                                                })
                                            }
                                        >
                                            <SelectTrigger
                                                id={`program-document-before-${index}`}
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {DOCUMENT_REQUIREMENT_MILESTONES.map(
                                                    (milestone) => (
                                                        <SelectItem
                                                            key={milestone.value}
                                                            value={milestone.value}
                                                        >
                                                            {milestone.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                errors[
                                                    `document_requirements.${index}.required_before`
                                                ]
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <Input
                                        id={`program-document-required-${index}`}
                                        type="checkbox"
                                        checked={requirement.is_required}
                                        onChange={(event) =>
                                            updateRequirement(index, {
                                                is_required:
                                                    event.target.checked,
                                            })
                                        }
                                        className="mt-1 size-4 shrink-0 rounded border-input"
                                    />
                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor={`program-document-required-${index}`}
                                            className="font-normal"
                                        >
                                            Required
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Block Verified or Delivered until a
                                            file of this type is attached.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
