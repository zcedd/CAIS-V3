'use client';

import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { UserProgramAssistanceRow } from '@/pages/user/programs/assistance-columns';
import { assistanceStatuses } from '@/pages/user/programs/assistance-data';
import { Table, VisibilityState } from '@tanstack/react-table';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';

export type ModeFilterOption = {
    label: string;
    value: string;
};

export type AssistanceTableFilters = {
    search: string;
    status: string[];
    mode: string[];
};

interface AssistanceDataTableToolbarProps {
    table: Table<UserProgramAssistanceRow>;
    columnVisibility: VisibilityState;
    filters: AssistanceTableFilters;
    modeOptions: ModeFilterOption[];
    onFiltersChange: (
        overrides: Partial<AssistanceTableFilters> & { page?: number },
    ) => void;
}

export function AssistanceDataTableToolbar({
    table,
    columnVisibility,
    filters,
    modeOptions,
    onFiltersChange,
}: AssistanceDataTableToolbarProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    useEffect(() => {
        const trimmed = searchQuery.trim();

        if (trimmed === filters.search.trim()) {
            return;
        }

        const handle = window.setTimeout(() => {
            onFiltersChange({ search: trimmed, page: 1 });
        }, 400);

        return () => window.clearTimeout(handle);
    }, [searchQuery, filters.search, onFiltersChange]);

    const isFiltered =
        filters.search !== '' ||
        filters.status.length > 0 ||
        filters.mode.length > 0;

    return (
        <div className="flex items-center justify-between gap-2">
            <div className="flex flex-1 flex-col-reverse items-start gap-y-2 sm:flex-row sm:items-center sm:space-x-2">
                <Input
                    placeholder="Filter CAIS number..."
                    value={searchQuery}
                    onChange={(event) => setSearchQuery(event.target.value)}
                    className={cn(
                        'h-8 w-[150px] lg:w-[250px]',
                        searchQuery.trim().length > 0 &&
                            'border-primary bg-primary/5 ring-1 ring-primary/30',
                    )}
                />
                <DataTableFacetedFilter
                    filterValue={filters.status}
                    title="Status"
                    options={[...assistanceStatuses]}
                    onFilterChange={(values) =>
                        onFiltersChange({ status: values, page: 1 })
                    }
                />
                {modeOptions.length > 0 ? (
                    <DataTableFacetedFilter
                        filterValue={filters.mode}
                        title="Mode"
                        options={modeOptions}
                        onFilterChange={(values) =>
                            onFiltersChange({ mode: values, page: 1 })
                        }
                    />
                ) : null}
                {isFiltered ? (
                    <Button
                        variant="ghost"
                        onClick={() =>
                            onFiltersChange({
                                search: '',
                                status: [],
                                mode: [],
                                page: 1,
                            })
                        }
                        className="h-8 px-2 lg:px-3"
                    >
                        Reset
                        <X className="ml-2 size-4" />
                    </Button>
                ) : null}
            </div>
            <DataTableViewOptions
                table={table}
                columnVisibility={columnVisibility}
            />
        </div>
    );
}
