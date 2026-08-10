export type ProgramFieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'date'
    | 'boolean'
    | 'select';

export type ProgramFieldDefinition = {
    id?: number | null;
    label: string;
    key?: string;
    type: ProgramFieldType;
    options: string[] | null;
    is_required: boolean;
    show_in_table: boolean;
    sort_order: number;
};

export type ProgramFieldOption = {
    id: number;
    label: string;
    key: string;
    type: ProgramFieldType;
    options: string[] | null;
    is_required: boolean;
    show_in_table: boolean;
    sort_order: number;
};

export type AssistanceFieldValueInput = {
    program_field_id: number;
    value: string;
};

export const PROGRAM_FIELD_TYPE_OPTIONS: {
    value: ProgramFieldType;
    label: string;
}[] = [
    { value: 'text', label: 'Text' },
    { value: 'textarea', label: 'Long text' },
    { value: 'number', label: 'Number' },
    { value: 'date', label: 'Date' },
    { value: 'boolean', label: 'Yes / No' },
    { value: 'select', label: 'Select' },
];

export function createEmptyProgramField(
    sortOrder = 0,
): ProgramFieldDefinition {
    return {
        id: null,
        label: '',
        type: 'text',
        options: null,
        is_required: false,
        show_in_table: false,
        sort_order: sortOrder,
    };
}
