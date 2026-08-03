'use client';

import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BeneficiaryListRow } from '@/types/beneficiary';
import type { Table, VisibilityState } from '@tanstack/react-table';
import { RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';

const beneficiaryTypeOptions = [
    { label: 'Individual', value: 'individual' },
    { label: 'Organization', value: 'organization' },
] as const;

export type BeneficiaryTableFilters = {
    search: string;
    type: string[];
};

interface BeneficiaryDataTableToolbarProps {
    table: Table<BeneficiaryListRow>;
    columnVisibility: VisibilityState;
    filters: BeneficiaryTableFilters;
    onFiltersChange: (
        overrides: Partial<BeneficiaryTableFilters> & { page?: number },
    ) => void;
}

export function BeneficiaryDataTableToolbar({
    table,
    columnVisibility,
    filters,
    onFiltersChange,
}: BeneficiaryDataTableToolbarProps) {
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
        }, 250);

        return () => window.clearTimeout(handle);
    }, [searchQuery, filters.search, onFiltersChange]);

    const hasActiveFilters =
        filters.search.trim() !== '' || filters.type.length > 0;

    return (
        <div
            className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            data-tour="beneficiaries-filters"
        >
            <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <Input
                    placeholder="Search by name or CAIS number..."
                    value={searchQuery}
                    onChange={(event) => setSearchQuery(event.target.value)}
                    className="h-9 max-w-sm"
                />
                <DataTableFacetedFilter
                    filterValue={filters.type}
                    title="Type"
                    options={[...beneficiaryTypeOptions]}
                    onFilterChange={(values) =>
                        onFiltersChange({ type: values, page: 1 })
                    }
                />
                {hasActiveFilters ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-9 px-2 lg:px-3"
                        onClick={() => {
                            setSearchQuery('');
                            onFiltersChange({
                                search: '',
                                type: [],
                                page: 1,
                            });
                        }}
                    >
                        Reset
                        <RotateCcw className="ml-2 h-4 w-4" />
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
