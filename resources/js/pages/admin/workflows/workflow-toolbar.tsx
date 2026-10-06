'use client';

import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { WorkflowFormFields } from '@/pages/admin/workflows/workflow-form-fields';
import { store as storeAdminWorkflow } from '@/routes/admin/workflows';
import type { AdminDepartmentOption } from '@/types/admin-user';
import type {
    AdminWorkflowRow,
    AdminWorkflowStatusOption,
    AdminWorkflowTableFilters,
} from '@/types/admin-workflow';
import { Form } from '@inertiajs/react';
import type { Table, VisibilityState } from '@tanstack/react-table';
import { Plus, RotateCcw, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface AdminWorkflowToolbarProps {
    table: Table<AdminWorkflowRow>;
    columnVisibility: VisibilityState;
    filters: AdminWorkflowTableFilters;
    departments: AdminDepartmentOption[];
    statusOptions: AdminWorkflowStatusOption[];
    canCreate: boolean;
    onFiltersChange: (
        overrides: Partial<AdminWorkflowTableFilters> & { page?: number },
    ) => void;
    onWorkflowCreated?: () => void;
}

export function AdminWorkflowToolbar({
    table,
    columnVisibility,
    filters,
    departments,
    statusOptions,
    canCreate,
    onFiltersChange,
    onWorkflowCreated,
}: AdminWorkflowToolbarProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [createFormKey, setCreateFormKey] = useState(0);

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

    useEffect(() => {
        if (!createOpen) {
            setCreateFormKey((key) => key + 1);
        }
    }, [createOpen]);

    const hasActiveFilters =
        filters.search.trim() !== '' ||
        filters.department_id !== null ||
        filters.status.length > 0;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <Input
                        placeholder="Search by name or code..."
                        value={searchQuery}
                        onChange={(event) => setSearchQuery(event.target.value)}
                        className="h-9 max-w-sm"
                    />
                    <Select
                        value={
                            filters.department_id === null
                                ? 'all'
                                : String(filters.department_id)
                        }
                        onValueChange={(value) =>
                            onFiltersChange({
                                department_id:
                                    value === 'all' ? null : Number(value),
                                page: 1,
                            })
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
                        filterValue={filters.status}
                        title="Status"
                        options={statusOptions}
                        onFilterChange={(values) =>
                            onFiltersChange({ status: values, page: 1 })
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
                                    department_id: null,
                                    status: [],
                                    page: 1,
                                });
                            }}
                        >
                            Reset
                            <RotateCcw className="ml-2 h-4 w-4" />
                        </Button>
                    ) : null}
                </div>

                <div className="flex items-center gap-2">
                    <DataTableViewOptions
                        table={table}
                        columnVisibility={columnVisibility}
                    />
                    {canCreate ? (
                        <Button type="button" onClick={() => setCreateOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create workflow
                        </Button>
                    ) : null}
                </div>
            </div>

            <Drawer
                open={createOpen}
                onOpenChange={setCreateOpen}
                direction="right"
            >
                <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-lg">
                    <DrawerHeader>
                        <DrawerTitle>Create workflow</DrawerTitle>
                        <DrawerDescription>
                            Add a draft workflow for a department. Publish it
                            before it can be activated and assigned to programs.
                        </DrawerDescription>
                    </DrawerHeader>

                    <Form
                        key={createFormKey}
                        {...storeAdminWorkflow.form()}
                        disableWhileProcessing
                        resetOnSuccess
                        onSuccess={() => {
                            setCreateOpen(false);
                            toast.success('Workflow created as a draft.');
                            onWorkflowCreated?.();
                        }}
                        className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <WorkflowFormFields
                                    departments={departments}
                                    errors={errors}
                                    idPrefix="create-workflow"
                                />
                                <DrawerFooter className="px-0">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Creating...'
                                            : 'Create workflow'}
                                    </Button>
                                    <DrawerClose asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            <X className="mr-2 h-4 w-4" />
                                            Cancel
                                        </Button>
                                    </DrawerClose>
                                </DrawerFooter>
                            </>
                        )}
                    </Form>
                </DrawerContent>
            </Drawer>
        </div>
    );
}
