'use client';

import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
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
import { FundAmountField } from '@/pages/user/funds/fund-amount-field';
import type { FundRow } from '@/types/fund';
import { store as storeFund } from '@/routes/user/funds';
import { Form } from '@inertiajs/react';
import { Table, VisibilityState } from '@tanstack/react-table';
import { Plus, RotateCcw, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

const fundStatusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
] as const;

export type FundTableFilters = {
    search: string;
    status: string[];
};

interface FundDataTableToolbarProps {
    table: Table<FundRow>;
    columnVisibility: VisibilityState;
    filters: FundTableFilters;
    departmentSlug: string;
    departmentName: string;
    onFiltersChange: (
        overrides: Partial<FundTableFilters> & { page?: number },
    ) => void;
    onFundCreated?: () => void;
}

export function FundDataTableToolbar({
    table,
    columnVisibility,
    filters,
    departmentSlug,
    departmentName,
    onFiltersChange,
    onFundCreated,
}: FundDataTableToolbarProps) {
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
        filters.search.trim() !== '' || filters.status.length > 0;

    return (
        <div className="flex flex-col gap-4">
            <div
                className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                data-tour="funds-filters"
            >
                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <Input
                        placeholder="Search by fund name..."
                        value={searchQuery}
                        onChange={(event) =>
                            setSearchQuery(event.target.value)
                        }
                        className="h-9 max-w-sm"
                    />
                    <DataTableFacetedFilter
                        filterValue={filters.status}
                        title="Status"
                        options={[...fundStatusOptions]}
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
                    <Button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                        data-tour="funds-create"
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Create fund
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
                        <DrawerTitle>Create fund</DrawerTitle>
                        <DrawerDescription>
                            Add a new fund for {departmentName}.
                        </DrawerDescription>
                    </DrawerHeader>

                    <Form
                        key={createFormKey}
                        {...storeFund.form({
                            department: departmentSlug,
                        })}
                        disableWhileProcessing
                        resetOnSuccess
                        onSuccess={() => {
                            setCreateOpen(false);
                            toast.success('Fund created successfully.');
                            onFundCreated?.();
                        }}
                        className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="fund-name">Name</Label>
                                    <Input
                                        id="fund-name"
                                        name="name"
                                        placeholder="Fund name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="fund-amount">Amount</Label>
                                    <FundAmountField id="fund-amount" />
                                    <InputError message={errors.amount} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="fund-year">Year</Label>
                                    <Input
                                        id="fund-year"
                                        name="year"
                                        placeholder="e.g. 2026"
                                        maxLength={4}
                                    />
                                    <InputError message={errors.year} />
                                </div>

                                <div className="flex items-start gap-3">
                                    <Input
                                        id="fund-is-active"
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        defaultChecked
                                        className="mt-1 size-4 shrink-0 rounded border-input"
                                    />
                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor="fund-is-active"
                                            className="font-normal"
                                        >
                                            Active fund
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            New funds are active by default.
                                        </p>
                                    </div>
                                </div>

                                <DrawerFooter className="px-0">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Creating...'
                                            : 'Create fund'}
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
