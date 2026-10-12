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
import type { BeneficiaryListRow } from '@/types/beneficiary';

const beneficiaryTypeOptions = [
    { label: 'Individual', value: 'individual' },
    { label: 'Organization', value: 'organization' },
] as const;

export type BeneficiaryTableFilters = {
    search: string;
    type: string[];
    city: string;
    sector: string;
    sort: string;
};

interface BeneficiaryDataTableToolbarProps {
    table: Table<BeneficiaryListRow>;
    columnVisibility: VisibilityState;
    filters: BeneficiaryTableFilters;
    cityOptions?: { value: string; label: string }[];
    onFiltersChange: (
        overrides: Partial<BeneficiaryTableFilters> & { page?: number },
    ) => void;
}

export function BeneficiaryDataTableToolbar({
    table,
    columnVisibility,
    filters,
    cityOptions = [],
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
        filters.search.trim() !== '' ||
        filters.type.length > 0 ||
        filters.city !== '' ||
        filters.sector !== '' ||
        (filters.sort !== '' && filters.sort !== 'name');

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
                <Select
                    value={filters.city === '' ? 'all' : filters.city}
                    onValueChange={(value) =>
                        onFiltersChange({
                            city: value === 'all' ? '' : value,
                            page: 1,
                        })
                    }
                >
                    <SelectTrigger className="h-9 w-48">
                        <SelectValue placeholder="All municipalities" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All municipalities</SelectItem>
                        {cityOptions.map((city) => (
                            <SelectItem key={city.value} value={city.value}>
                                {city.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select
                    value={filters.sector === '' ? 'all' : filters.sector}
                    onValueChange={(value) =>
                        onFiltersChange({
                            sector: value === 'all' ? '' : value,
                            page: 1,
                        })
                    }
                >
                    <SelectTrigger className="h-9 w-44">
                        <SelectValue placeholder="All vulnerabilities" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All vulnerabilities</SelectItem>
                        <SelectItem value="pwd">PWD</SelectItem>
                        <SelectItem value="solo_parent">Solo parent</SelectItem>
                        <SelectItem value="four_ps">4Ps</SelectItem>
                        <SelectItem value="indigenous">Indigenous</SelectItem>
                        <SelectItem value="senior">Senior</SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    value={filters.sort || 'name'}
                    onValueChange={(value) =>
                        onFiltersChange({ sort: value, page: 1 })
                    }
                >
                    <SelectTrigger className="h-9 w-40">
                        <SelectValue placeholder="Sort" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="newest">Newest first</SelectItem>
                        <SelectItem value="name">Name</SelectItem>
                        <SelectItem value="most_assisted">
                            Most assisted
                        </SelectItem>
                    </SelectContent>
                </Select>
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
                                city: '',
                                sector: '',
                                sort: 'name',
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
