'use client';

import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import InputError from '@/components/input-error';
import { UnspscCodeCombobox } from '@/components/unspsc-code-combobox';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    applyCreateDrawerOpenChange,
    useCreateDrawerTourLock,
} from '@/lib/tour-create-drawer';
import { cn } from '@/lib/utils';
import type { UserDepartmentItemRow } from '@/pages/user/items/item-columns';
import { store as storeDepartmentItem } from '@/routes/user/items';
import {
    ITEM_KIND_OPTIONS,
    tracksInventory,
    type ItemKind,
} from '@/types/item';
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
    const createDrawerTourLocked = useCreateDrawerTourLock();
    const [createFormKey, setCreateFormKey] = useState(0);
    const [unspscCodeId, setUnspscCodeId] = useState<number | null>(null);
    const [isPerishable, setIsPerishable] = useState(false);
    const [kind, setKind] = useState<ItemKind>('goods');
    const [unitId, setUnitId] = useState('');

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
            setUnspscCodeId(null);
            setIsPerishable(false);
            setKind('goods');
            setUnitId('');
        }
    }, [createOpen]);

    useEffect(() => {
        if (kind !== 'cash') {
            return;
        }

        const phpUnit = unitMeasurements.find(
            (unit) => unit.name.toLowerCase() === 'php',
        );

        if (phpUnit) {
            setUnitId(String(phpUnit.id));
        }
    }, [kind, unitMeasurements]);

    const hasActiveFilters = filters.search.trim() !== '';

    return (
        <div className="flex flex-col gap-4">
            <div
                data-tour="items-filters"
                className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <Input
                        placeholder="Search items..."
                        value={searchQuery}
                        onChange={(event) => setSearchQuery(event.target.value)}
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
                        data-tour="items-create"
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        New item
                    </Button>
                </div>
            </div>

            <Drawer
                open={createOpen}
                onOpenChange={(open) =>
                    applyCreateDrawerOpenChange(open, setCreateOpen)
                }
                dismissible={!createDrawerTourLocked}
                noBodyStyles={createDrawerTourLocked}
                direction="right"
            >
                <DrawerContent
                    className="data-[vaul-drawer-direction=right]:sm:max-w-3xl"
                    data-tour="items-create-form"
                    onPointerDownOutside={(event) => {
                        if (createDrawerTourLocked) {
                            event.preventDefault();
                        }
                    }}
                >
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
                                <div
                                    className="space-y-2"
                                    data-tour="items-create-name"
                                >
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
                                    <Label htmlFor="create-item-kind">
                                        Kind
                                    </Label>
                                    <Select
                                        name="kind"
                                        value={kind}
                                        onValueChange={(value) => {
                                            setKind(value as ItemKind);

                                            if (value !== 'goods') {
                                                setIsPerishable(false);
                                            }
                                        }}
                                        required
                                    >
                                        <SelectTrigger
                                            id="create-item-kind"
                                            className={selectClassName}
                                        >
                                            <SelectValue placeholder="Select kind" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {ITEM_KIND_OPTIONS.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.kind} />
                                </div>

                                <div
                                    className="space-y-2"
                                    data-tour="items-create-unit"
                                >
                                    <Label htmlFor="create-item-unit">
                                        Unit of measurement
                                    </Label>
                                    <Select
                                        name="item_unit_measurement_id"
                                        value={unitId}
                                        onValueChange={setUnitId}
                                        required
                                        disabled={kind === 'cash'}
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
                                    {kind === 'cash' && unitId ? (
                                        <input
                                            type="hidden"
                                            name="item_unit_measurement_id"
                                            value={unitId}
                                        />
                                    ) : null}
                                    <InputError
                                        message={
                                            errors.item_unit_measurement_id
                                        }
                                    />
                                </div>

                                <UnspscCodeCombobox
                                    departmentSlug={departmentSlug}
                                    value={unspscCodeId}
                                    onChange={(next) => setUnspscCodeId(next)}
                                    error={errors.unspsc_code_id}
                                />

                                {tracksInventory(kind) ? (
                                    <>
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="hidden"
                                                name="is_perishable"
                                                value={isPerishable ? '1' : '0'}
                                            />
                                            <Checkbox
                                                id="create-item-perishable"
                                                checked={isPerishable}
                                                onCheckedChange={(checked) =>
                                                    setIsPerishable(
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <Label htmlFor="create-item-perishable">
                                                Perishable (require batch and
                                                expiry)
                                            </Label>
                                        </div>
                                        <InputError
                                            message={errors.is_perishable}
                                        />

                                        <div className="space-y-2">
                                            <Label htmlFor="create-item-threshold">
                                                Low-stock threshold
                                            </Label>
                                            <Input
                                                id="create-item-threshold"
                                                name="low_stock_threshold"
                                                type="number"
                                                min={0}
                                            />
                                            <InputError
                                                message={
                                                    errors.low_stock_threshold
                                                }
                                            />
                                        </div>
                                    </>
                                ) : null}

                                <DrawerFooter
                                    className="px-0"
                                    data-tour="items-create-submit"
                                >
                                    <Button type="submit" disabled={processing}>
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
