'use client';

import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { UserDepartmentItemRow } from '@/pages/user/items/item-columns';
import { store as storeDepartmentItem } from '@/routes/user/items';
import { Form } from '@inertiajs/react';
import { Table, VisibilityState } from '@tanstack/react-table';
import { Plus, RotateCcw, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

export type UnitMeasurementOption = {
    id: number;
    name: string;
};

export type ItemTableFilters = {
    search: string;
};

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

interface ItemDataTableToolbarProps {
    table: Table<UserDepartmentItemRow>;
    columnVisibility: VisibilityState;
    filters: ItemTableFilters;
    departmentSlug: string;
    unitMeasurements: UnitMeasurementOption[];
    onFiltersChange: (
        overrides: Partial<ItemTableFilters> & { page?: number },
    ) => void;
    onItemCreated?: () => void;
}

export function ItemDataTableToolbar({
    table,
    columnVisibility,
    filters,
    departmentSlug,
    unitMeasurements,
    onFiltersChange,
    onItemCreated,
}: ItemDataTableToolbarProps) {
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

    const hasActiveFilters = filters.search.trim() !== '';

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <Input
                        placeholder="Search items..."
                        value={searchQuery}
                        onChange={(event) =>
                            setSearchQuery(event.target.value)
                        }
                        className="h-9 max-w-sm"
                    />
                    {hasActiveFilters ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-9 px-2 lg:px-3"
                            onClick={() => {
                                setSearchQuery('');
                                onFiltersChange({ search: '', page: 1 });
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
                    <Button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        New item
                    </Button>
                </div>
            </div>

            <Drawer open={createOpen} onOpenChange={setCreateOpen} direction="right">
                <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                    <DrawerHeader>
                        <DrawerTitle>Create item</DrawerTitle>
                        <DrawerDescription>
                            Add a new item for your department.
                        </DrawerDescription>
                    </DrawerHeader>

                    <Form
                        key={createFormKey}
                        action={storeDepartmentItem.url({
                            department: departmentSlug,
                        })}
                        method="post"
                        options={{
                            preserveScroll: true,
                        }}
                        onSuccess={() => {
                            setCreateOpen(false);
                            toast.success('Item created successfully.');
                            onItemCreated?.();
                        }}
                        className="space-y-4 px-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="create-item-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="create-item-name"
                                        name="name"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="create-item-unit">
                                        Unit of measurement
                                    </Label>
                                    <Select
                                        name="item_unit_measurement_id"
                                        required
                                    >
                                        <SelectTrigger
                                            id="create-item-unit"
                                            className={selectClassName}
                                        >
                                            <SelectValue placeholder="Select unit" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {unitMeasurements.map((unit) => (
                                                <SelectItem
                                                    key={unit.id}
                                                    value={String(unit.id)}
                                                >
                                                    {unit.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            errors.item_unit_measurement_id
                                        }
                                    />
                                </div>

                                <DrawerFooter className="px-0">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Create item
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
