import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { ProgramFieldOption } from '@/types/program-field';

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

type AssistanceFieldInputsProps = {
    fields: ProgramFieldOption[];
    values: Record<number, string>;
    onChange: (fieldId: number, value: string) => void;
    errors?: Record<string, string>;
    idPrefix?: string;
};

export function AssistanceFieldInputs({
    fields,
    values,
    onChange,
    errors = {},
    idPrefix = 'assistance-field',
}: AssistanceFieldInputsProps) {
    if (fields.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3">
            <div className="space-y-1">
                <Label>Program information</Label>
                <p className="text-sm text-muted-foreground">
                    Extra details for this program.
                </p>
            </div>
            <InputError message={errors.field_values} />

            <div className="space-y-3">
                {fields.map((field) => {
                    const fieldId = `${idPrefix}-${field.id}`;
                    const value = values[field.id] ?? '';

                    return (
                        <div key={field.id} className="space-y-2">
                            <Label htmlFor={fieldId}>
                                {field.label}
                                {field.is_required ? (
                                    <span className="text-destructive"> *</span>
                                ) : null}
                            </Label>

                            {field.type === 'textarea' ? (
                                <Textarea
                                    id={fieldId}
                                    value={value}
                                    onChange={(event) =>
                                        onChange(field.id, event.target.value)
                                    }
                                    rows={3}
                                />
                            ) : field.type === 'boolean' ? (
                                <label className="flex items-center gap-2 text-sm">
                                    <Input
                                        id={fieldId}
                                        type="checkbox"
                                        checked={
                                            value === '1' ||
                                            value === 'true' ||
                                            value === 'yes'
                                        }
                                        onChange={(event) =>
                                            onChange(
                                                field.id,
                                                event.target.checked
                                                    ? '1'
                                                    : '0',
                                            )
                                        }
                                        className="size-4 shrink-0 rounded border-input"
                                    />
                                    Yes
                                </label>
                            ) : field.type === 'select' ? (
                                <Select
                                    value={value}
                                    onValueChange={(next) =>
                                        onChange(field.id, next)
                                    }
                                >
                                    <SelectTrigger
                                        id={fieldId}
                                        className={selectClassName}
                                    >
                                        <SelectValue
                                            placeholder={`Select ${field.label.toLowerCase()}`}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(field.options ?? []).map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    id={fieldId}
                                    type={
                                        field.type === 'number'
                                            ? 'number'
                                            : field.type === 'date'
                                              ? 'date'
                                              : 'text'
                                    }
                                    value={value}
                                    onChange={(event) =>
                                        onChange(field.id, event.target.value)
                                    }
                                />
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export function fieldValuesToPayload(
    fields: ProgramFieldOption[],
    values: Record<number, string>,
): { program_field_id: number; value: string }[] {
    return fields.map((field) => ({
        program_field_id: field.id,
        value:
            values[field.id] ??
            (field.type === 'boolean' ? '0' : ''),
    }));
}

export function initialFieldValues(
    fields: ProgramFieldOption[],
    existing: { program_field_id: number; value: string | null }[] = [],
): Record<number, string> {
    const existingById = Object.fromEntries(
        existing.map((row) => [row.program_field_id, row.value ?? '']),
    );

    return Object.fromEntries(
        fields.map((field) => [
            field.id,
            existingById[field.id] ??
                (field.type === 'boolean' ? '0' : ''),
        ]),
    );
}
