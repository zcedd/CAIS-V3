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
    PROGRAM_FIELD_TYPE_OPTIONS,
    createEmptyProgramField,
    type ProgramFieldDefinition,
    type ProgramFieldType,
} from '@/types/program-field';
import { Plus, Trash2 } from 'lucide-react';

type ProgramFieldsEditorProps = {
    fields: ProgramFieldDefinition[];
    onChange: (fields: ProgramFieldDefinition[]) => void;
    errors?: Record<string, string>;
};

export function ProgramFieldsEditor({
    fields,
    onChange,
    errors = {},
}: ProgramFieldsEditorProps) {
    const updateField = (
        index: number,
        patch: Partial<ProgramFieldDefinition>,
    ) => {
        onChange(
            fields.map((field, fieldIndex) =>
                fieldIndex === index ? { ...field, ...patch } : field,
            ),
        );
    };

    const removeField = (index: number) => {
        onChange(
            fields
                .filter((_, fieldIndex) => fieldIndex !== index)
                .map((field, sortOrder) => ({ ...field, sort_order: sortOrder })),
        );
    };

    const addField = () => {
        onChange([...fields, createEmptyProgramField(fields.length)]);
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <div className="space-y-1">
                    <Label>Custom fields</Label>
                    <p className="text-sm text-muted-foreground">
                        Collect program-specific information on each assistance.
                    </p>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={addField}>
                    <Plus className="size-4" />
                    Add field
                </Button>
            </div>

            <InputError message={errors.fields} />

            {fields.length === 0 ? (
                <p className="rounded-xl border border-dashed px-3 py-4 text-sm text-muted-foreground">
                    No custom fields yet. Optional — add fields when this program
                    needs extra beneficiary information.
                </p>
            ) : (
                <div className="space-y-3">
                    {fields.map((field, index) => (
                        <div
                            key={field.id ?? `new-${index}`}
                            className="space-y-3 rounded-xl border p-3"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm font-medium">
                                    Field {index + 1}
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 text-muted-foreground"
                                    onClick={() => removeField(index)}
                                    aria-label={`Remove field ${index + 1}`}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor={`program-field-label-${index}`}>
                                        Label
                                    </Label>
                                    <Input
                                        id={`program-field-label-${index}`}
                                        value={field.label}
                                        onChange={(event) =>
                                            updateField(index, {
                                                label: event.target.value,
                                            })
                                        }
                                        placeholder="e.g. Household size"
                                    />
                                    <InputError
                                        message={errors[`fields.${index}.label`]}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor={`program-field-type-${index}`}>
                                        Type
                                    </Label>
                                    <Select
                                        value={field.type}
                                        onValueChange={(value) =>
                                            updateField(index, {
                                                type: value as ProgramFieldType,
                                                options:
                                                    value === 'select'
                                                        ? (field.options ?? [''])
                                                        : null,
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id={`program-field-type-${index}`}
                                        >
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {PROGRAM_FIELD_TYPE_OPTIONS.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors[`fields.${index}.type`]}
                                    />
                                </div>
                            </div>

                            {field.type === 'select' ? (
                                <div className="space-y-2">
                                    <Label
                                        htmlFor={`program-field-options-${index}`}
                                    >
                                        Options (one per line)
                                    </Label>
                                    <textarea
                                        id={`program-field-options-${index}`}
                                        className="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        value={(field.options ?? []).join('\n')}
                                        onChange={(event) =>
                                            updateField(index, {
                                                options: event.target.value
                                                    .split('\n')
                                                    .map((option) => option.trim())
                                                    .filter(
                                                        (option) =>
                                                            option.length > 0,
                                                    ),
                                            })
                                        }
                                        placeholder={'Option A\nOption B'}
                                    />
                                    <InputError
                                        message={
                                            errors[`fields.${index}.options`]
                                        }
                                    />
                                </div>
                            ) : null}

                            <div className="flex flex-wrap gap-4">
                                <label className="flex items-center gap-2 text-sm">
                                    <Input
                                        type="checkbox"
                                        checked={field.is_required}
                                        onChange={(event) =>
                                            updateField(index, {
                                                is_required:
                                                    event.target.checked,
                                            })
                                        }
                                        className="size-4 shrink-0 rounded border-input"
                                    />
                                    Required
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <Input
                                        type="checkbox"
                                        checked={field.show_in_table}
                                        onChange={(event) =>
                                            updateField(index, {
                                                show_in_table:
                                                    event.target.checked,
                                            })
                                        }
                                        className="size-4 shrink-0 rounded border-input"
                                    />
                                    Show in assistance table
                                </label>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
