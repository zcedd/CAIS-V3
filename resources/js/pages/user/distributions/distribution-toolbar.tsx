'use client';

import type { Table, VisibilityState } from '@tanstack/react-table';
import { RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { DistributionBatchRow } from '@/pages/user/distributions/distribution-columns';

export type DistributionOption = { value: string; label: string };
export type DistributionProgramOption = { id: number; name: string };

export type DistributionTableFilters = {
    search: string;
    program_id: string;
    status: string[];
    distribution_type: string[];
    venue: string;
    beneficiary: string;
    sort: string;
    direction: 'asc' | 'desc';
};

function useSyncedText(source: string): [string, (value: string) => void] {
    const [value, setValue] = useState(source);
    const [synced, setSynced] = useState(source);

    if (source !== synced) {
        setSynced(source);
        setValue(source);
    }

    return [value, setValue];
}

function useDebouncedText(
    value: string,
    current: string,
    onCommit: (value: string) => void,
): void {
    useEffect(() => {
        const trimmed = value.trim();

        if (trimmed === current.trim()) {
            return;
        }

        const handle = window.setTimeout(() => {
            onCommit(trimmed);
        }, 250);

        return () => window.clearTimeout(handle);
    }, [value, current, onCommit]);
}

export function DistributionDataTableToolbar({
    table,
    columnVisibility,
    filters,
    statuses,
    distributionTypes,
    programs,
    onFiltersChange,
}: {
    table: Table<DistributionBatchRow>;
    columnVisibility: VisibilityState;
    filters: DistributionTableFilters;
    statuses: DistributionOption[];
    distributionTypes: DistributionOption[];
    programs: DistributionProgramOption[];
    onFiltersChange: (
        overrides: Partial<DistributionTableFilters> & { page?: number },
    ) => void;
}) {
    const [searchQuery, setSearchQuery] = useSyncedText(filters.search);
    const [venueQuery, setVenueQuery] = useSyncedText(filters.venue);
    const [beneficiaryQuery, setBeneficiaryQuery] = useSyncedText(
        filters.beneficiary,
    );

    useDebouncedText(searchQuery, filters.search, (search) => {
        onFiltersChange({ search, page: 1 });
    });
    useDebouncedText(venueQuery, filters.venue, (venue) => {
        onFiltersChange({ venue, page: 1 });
    });
    useDebouncedText(beneficiaryQuery, filters.beneficiary, (beneficiary) => {
        onFiltersChange({ beneficiary, page: 1 });
    });

    const hasActiveFilters =
        filters.search.trim() !== '' ||
        filters.venue.trim() !== '' ||
        filters.beneficiary.trim() !== '' ||
        filters.program_id !== '' ||
        filters.status.length > 0 ||
        filters.distribution_type.length > 0;

    return (
        <div
            className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            data-tour="distributions-filters"
        >
            <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <Input
                    placeholder="Search code or title..."
                    value={searchQuery}
                    onChange={(event) => setSearchQuery(event.target.value)}
                    aria-label="Search distributions"
                    className="h-9 max-w-sm"
                />
                <Input
                    placeholder="Beneficiary"
                    value={beneficiaryQuery}
                    onChange={(event) =>
                        setBeneficiaryQuery(event.target.value)
                    }
                    aria-label="Beneficiary"
                    className="h-9 w-44"
                />
                <Input
                    placeholder="Venue"
                    value={venueQuery}
                    onChange={(event) => setVenueQuery(event.target.value)}
                    aria-label="Venue"
                    className="h-9 w-36"
                />
                <DataTableFacetedFilter
                    filterValue={filters.status}
                    title="Status"
                    options={statuses}
                    onFilterChange={(values) =>
                        onFiltersChange({ status: values, page: 1 })
                    }
                />
                <DataTableFacetedFilter
                    filterValue={filters.distribution_type}
                    title="Type"
                    options={distributionTypes}
                    onFilterChange={(values) =>
                        onFiltersChange({
                            distribution_type: values,
                            page: 1,
                        })
                    }
                />
                <Select
                    value={filters.program_id === '' ? 'all' : filters.program_id}
                    onValueChange={(value) =>
                        onFiltersChange({
                            program_id: value === 'all' ? '' : value,
                            page: 1,
                        })
                    }
                >
                    <SelectTrigger aria-label="Program" className="h-9 w-48">
                        <SelectValue placeholder="All programs" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All programs</SelectItem>
                        {programs.map((program) => (
                            <SelectItem
                                key={program.id}
                                value={String(program.id)}
                            >
                                {program.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {hasActiveFilters ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-9 px-2 lg:px-3"
                        onClick={() => {
                            setSearchQuery('');
                            setVenueQuery('');
                            setBeneficiaryQuery('');
                            onFiltersChange({
                                search: '',
                                venue: '',
                                beneficiary: '',
                                program_id: '',
                                status: [],
                                distribution_type: [],
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
