'use client';

import type { SortingState } from '@tanstack/react-table';
import { createContext, useContext } from 'react';

export type DataTableSortingContextValue = {
    sorting: SortingState;
    onSortChange?: (columnId: string, direction: 'asc' | 'desc') => void;
};

export const DataTableSortingContext =
    createContext<DataTableSortingContextValue>({
        sorting: [],
    });

export function useDataTableSorting(): DataTableSortingContextValue {
    return useContext(DataTableSortingContext);
}

export function getColumnSortDirection(
    sorting: SortingState,
    columnId: string,
): false | 'asc' | 'desc' {
    const entry = sorting.find((item) => item.id === columnId);

    if (!entry) {
        return false;
    }

    return entry.desc ? 'desc' : 'asc';
}
