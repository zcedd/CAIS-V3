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
import { UserFormFields } from '@/pages/admin/users/user-form-fields';
import { store as storeAdminUser } from '@/routes/admin/users';
import type {
    AdminDepartmentOption,
    AdminRoleOption,
    AdminUserRow,
    AdminUserTableFilters,
} from '@/types/admin-user';
import { Form } from '@inertiajs/react';
import type { Table, VisibilityState } from '@tanstack/react-table';
import { Plus, RotateCcw, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface AdminUserToolbarProps {
    table: Table<AdminUserRow>;
    columnVisibility: VisibilityState;
    filters: AdminUserTableFilters;
    departments: AdminDepartmentOption[];
    roleOptions: AdminRoleOption[];
    onFiltersChange: (
        overrides: Partial<AdminUserTableFilters> & { page?: number },
    ) => void;
    onUserCreated?: () => void;
}

export function AdminUserToolbar({
    table,
    columnVisibility,
    filters,
    departments,
    roleOptions,
    onFiltersChange,
    onUserCreated,
}: AdminUserToolbarProps) {
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
        filters.role.length > 0;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <Input
                        placeholder="Search by name or email..."
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
                        filterValue={filters.role}
                        title="Role"
                        options={roleOptions}
                        onFilterChange={(values) =>
                            onFiltersChange({ role: values, page: 1 })
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
                                    role: [],
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
                    <Button type="button" onClick={() => setCreateOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create user
                    </Button>
                </div>
            </div>

            <Drawer
                open={createOpen}
                onOpenChange={setCreateOpen}
                direction="right"
            >
                <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-lg">
                    <DrawerHeader>
                        <DrawerTitle>Create user</DrawerTitle>
                        <DrawerDescription>
                            Add an account, assign a department, and choose
                            roles. They will get an email with a link to set
                            their password.
                        </DrawerDescription>
                    </DrawerHeader>

                    <Form
                        key={createFormKey}
                        {...storeAdminUser.form()}
                        disableWhileProcessing
                        resetOnSuccess
                        onSuccess={() => {
                            setCreateOpen(false);
                            toast.success(
                                'User created. An invite was sent to set their password.',
                            );
                            onUserCreated?.();
                        }}
                        className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <UserFormFields
                                    departments={departments}
                                    roleOptions={roleOptions}
                                    errors={errors}
                                    idPrefix="create-user"
                                />
                                <DrawerFooter className="px-0">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Creating...'
                                            : 'Create user'}
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
