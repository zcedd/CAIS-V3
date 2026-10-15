'use client';

import type { Table, VisibilityState } from '@tanstack/react-table';
import { Plus, RotateCcw } from 'lucide-react';
import { Can } from '@/components/auth/can';
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
import { Permission } from '@/lib/permissions';
import type { DistributionPolicy } from '@/pages/admin/distribution-policies/policy-columns';

type DepartmentOption = { id: number; name: string };

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export function DistributionPolicyToolbar({
    table,
    columnVisibility,
    search,
    departmentId,
    status,
    departments,
    onSearchChange,
    onDepartmentChange,
    onStatusChange,
    onReset,
    onCreate,
}: {
    table: Table<DistributionPolicy>;
    columnVisibility: VisibilityState;
    search: string;
    departmentId: number | null;
    status: string[];
    departments: DepartmentOption[];
    onSearchChange: (value: string) => void;
    onDepartmentChange: (departmentId: number | null) => void;
    onStatusChange: (status: string[]) => void;
    onReset: () => void;
    onCreate: () => void;
}) {
    const hasActiveFilters =
        search.trim() !== '' || departmentId !== null || status.length > 0;

    return (
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <Input
                    placeholder="Search policies..."
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    className="h-9 max-w-sm"
                />
                <Select
                    value={departmentId === null ? 'all' : String(departmentId)}
                    onValueChange={(value) =>
                        onDepartmentChange(
                            value === 'all' ? null : Number(value),
                        )
                    }
                >
                    <SelectTrigger className="h-9 w-50">
                        <SelectValue placeholder="Department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All departments</SelectItem>
                        {departments.map((department) => (
                            <SelectItem
                                key={department.id}
                                value={String(department.id)}
                            >
                                {department.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <DataTableFacetedFilter
                    filterValue={status}
                    title="Status"
                    options={statusOptions}
                    onFilterChange={onStatusChange}
                />
                {hasActiveFilters ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-9 px-2 lg:px-3"
                        onClick={onReset}
                    >
                        Reset
                        <RotateCcw className="ml-2 size-4" />
                    </Button>
                ) : null}
            </div>
            <div className="flex items-center gap-2">
                <DataTableViewOptions
                    table={table}
                    columnVisibility={columnVisibility}
                />
                <Can permission={Permission.DistributionPolicyManage}>
                    <Button type="button" onClick={onCreate}>
                        <Plus className="mr-2 size-4" />
                        New policy
                    </Button>
                </Can>
            </div>
        </div>
    );
}
