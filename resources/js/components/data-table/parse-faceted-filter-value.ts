import type { ColumnFiltersState } from '@tanstack/react-table';

export function getColumnFilterValue(
    columnFilters: ColumnFiltersState,
    columnId: string,
): unknown {
    return columnFilters.find((filter) => filter.id === columnId)?.value;
}

export function parseFacetedFilterValue(value: unknown): string[] {
    if (value == null || value === '') {
        return [];
    }

    if (Array.isArray(value)) {
        return value.map(String);
    }

    return [String(value)];
}
