'use client';

import { BeneficiarySearchCombobox } from '@/components/beneficiary-search-combobox';
import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
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
import { MultiSelect } from '@/components/ui/multi-select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { UserProgramAssistanceRow } from '@/pages/user/programs/assistance-columns';
import { store as storeProgramAssistance } from '@/routes/user/programs/assistances';
import { Form } from '@inertiajs/react';
import { Table, VisibilityState } from '@tanstack/react-table';
import {
    CalendarDays,
    ChevronDownIcon,
    Plus,
    RotateCcw,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

export type FilterOption = {
    label: string;
    value: string;
};

export type ModeFilterOption = FilterOption;

export type StatusFilterOption = FilterOption;

export type AssistanceTableFilters = {
    search: string;
    status: string[];
    mode: string[];
};

export type AssistanceSelectOption = {
    id: number;
    label: string;
};

export type AssistanceModeOption = {
    id: number;
    name: string;
};

export type AssistanceProgramItemOption = {
    id: number;
    name: string;
    unit: string | null;
};

export type AssistanceRequestSubStatusOption = {
    id: number;
    name: string;
    request_status: string | null;
    label: string;
};

type AssistanceItemDetail = {
    quantity: string;
    specification: string;
};

function formatDateForSubmit(date: Date | undefined): string | undefined {
    if (!date) {
        return undefined;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

type AssistanceDatePickerProps = {
    id: string;
    label: string;
    selected: Date | undefined;
    onSelect: (date: Date | undefined) => void;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    error?: string;
};

function AssistanceDatePicker({
    id,
    label,
    selected,
    onSelect,
    open,
    onOpenChange,
    error,
}: AssistanceDatePickerProps) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Popover open={open} onOpenChange={onOpenChange}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        id={id}
                        className="w-full justify-between font-normal"
                    >
                        {selected
                            ? selected.toLocaleDateString()
                            : 'Select date'}
                        <ChevronDownIcon className="size-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-auto overflow-hidden p-0"
                    align="start"
                >
                    <div className="flex gap-2 px-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onSelect(new Date())}
                            className="flex items-center gap-2 bg-transparent"
                        >
                            <CalendarDays className="size-4" />
                            Today
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onSelect(undefined)}
                            className="flex items-center gap-2 bg-transparent"
                        >
                            <RotateCcw className="size-4" />
                            Reset
                        </Button>
                    </div>
                    <Calendar
                        mode="single"
                        selected={selected}
                        captionLayout="dropdown"
                        onSelect={(date) => {
                            onSelect(date);
                            onOpenChange(false);
                        }}
                    />
                </PopoverContent>
            </Popover>
            <InputError message={error} />
        </div>
    );
}

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

interface AssistanceDataTableToolbarProps {
    table: Table<UserProgramAssistanceRow>;
    columnVisibility: VisibilityState;
    filters: AssistanceTableFilters;
    statusOptions: StatusFilterOption[];
    modeOptions: ModeFilterOption[];
    onFiltersChange: (
        overrides: Partial<AssistanceTableFilters> & { page?: number },
    ) => void;
    sort: string;
    direction: 'asc' | 'desc';
    departmentSlug: string;
    programId: number;
    programName: string;
    isOrganization: boolean;
    canCreate: boolean;
    modeOfRequestOptions: AssistanceModeOption[];
    programItems: AssistanceProgramItemOption[];
    onAssistanceCreated?: () => void;
}

export function AssistanceDataTableToolbar({
    table,
    columnVisibility,
    filters,
    statusOptions,
    modeOptions,
    onFiltersChange,
    sort,
    direction,
    departmentSlug,
    programId,
    programName,
    isOrganization,
    canCreate,
    modeOfRequestOptions,
    programItems,
    onAssistanceCreated,
}: AssistanceDataTableToolbarProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [dateRequestedOpen, setDateRequestedOpen] = useState(false);
    const [dateRequested, setDateRequested] = useState<Date | undefined>(
        undefined,
    );
    const [selectedItemIds, setSelectedItemIds] = useState<string[]>([]);
    const [itemDetails, setItemDetails] = useState<
        Record<string, AssistanceItemDetail>
    >({});
    const [selectedBeneficiaryId, setSelectedBeneficiaryId] = useState<
        number | null
    >(null);
    const [beneficiaryFieldKey, setBeneficiaryFieldKey] = useState(0);

    const programItemSelectOptions = programItems.map((item) => ({
        value: String(item.id),
        label: item.unit ? `${item.name} (${item.unit})` : item.name,
    }));

    const resetCreateForm = () => {
        setDateRequested(undefined);
        setDateRequestedOpen(false);
        setSelectedItemIds([]);
        setItemDetails({});
        setSelectedBeneficiaryId(null);
        setBeneficiaryFieldKey((key) => key + 1);
    };

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
            resetCreateForm();
        }
    }, [createOpen]);

    useEffect(() => {
        setItemDetails((current) => {
            const selectedIds = new Set(selectedItemIds);
            const next: Record<string, AssistanceItemDetail> = {};

            selectedItemIds.forEach((itemId) => {
                next[itemId] = current[itemId] ?? {
                    quantity: '1',
                    specification: '',
                };
            });

            if (Object.keys(current).length === Object.keys(next).length) {
                const unchanged = Object.entries(next).every(
                    ([id, detail]) =>
                        current[id]?.quantity === detail.quantity &&
                        current[id]?.specification === detail.specification,
                );

                if (unchanged && selectedIds.size === selectedItemIds.length) {
                    return current;
                }
            }

            return next;
        });
    }, [selectedItemIds]);

    const isFiltered =
        filters.search !== '' ||
        filters.status.length > 0 ||
        filters.mode.length > 0;

    const triggerExport = (format: 'csv' | 'xlsx') => {
        const query = new URLSearchParams({
            format,
            sort,
            direction,
        });

        if (filters.search.trim() !== '') {
            query.set('search', filters.search.trim());
        }

        filters.status.forEach((statusValue) => {
            query.append('status[]', statusValue);
        });

        filters.mode.forEach((modeValue) => {
            query.append('mode[]', modeValue);
        });

        window.location.assign(
            `/${departmentSlug}/programs/${programId}/assistances/export?${query.toString()}`,
        );
    };

    return (
        <>
            <div className="flex items-center justify-between gap-2">
                <div className="flex flex-1 flex-col-reverse items-start gap-y-2 sm:flex-row sm:items-center sm:space-x-2">
                    <Input
                        placeholder="Filter CAIS number or name..."
                        value={searchQuery}
                        onChange={(event) => setSearchQuery(event.target.value)}
                        className={cn(
                            'h-8 w-[150px] lg:w-[250px]',
                            searchQuery.trim().length > 0 &&
                                'border-primary bg-primary/5 ring-1 ring-primary/30',
                        )}
                    />
                    {statusOptions.length > 0 ? (
                        <DataTableFacetedFilter
                            filterValue={filters.status}
                            title="Status"
                            options={statusOptions}
                            onFilterChange={(values) =>
                                onFiltersChange({ status: values, page: 1 })
                            }
                        />
                    ) : null}
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
                <div className="flex flex-1 flex-col-reverse items-start gap-y-2 sm:flex-row sm:items-center sm:space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-8 px-2 lg:px-3"
                        onClick={() => triggerExport('csv')}
                    >
                        Export CSV
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-8 px-2 lg:px-3"
                        onClick={() => triggerExport('xlsx')}
                    >
                        Export XLSX
                    </Button>
                    <DataTableViewOptions
                        table={table}
                        columnVisibility={columnVisibility}
                    />
                    <Button
                        type="button"
                        variant="default"
                        className="h-8 px-2 lg:px-3"
                        disabled={!canCreate}
                        onClick={() => setCreateOpen(true)}
                    >
                        <Plus className="size-4" />
                        Add Assistance
                    </Button>
                </div>
            </div>

            <Drawer
                open={createOpen}
                onOpenChange={setCreateOpen}
                direction="right"
            >
                <DrawerContent className="w-full data-[vaul-drawer-direction=right]:w-full sm:max-w-full data-[vaul-drawer-direction=right]:sm:max-w-full lg:max-w-3xl data-[vaul-drawer-direction=right]:lg:max-w-3xl">
                    <DrawerHeader>
                        <DrawerTitle>Add assistance</DrawerTitle>
                        <DrawerDescription>
                            Create a new assistance record for {programName}.
                        </DrawerDescription>
                    </DrawerHeader>
                    {canCreate ? (
                        <Form
                            action={storeProgramAssistance.url({
                                department: departmentSlug,
                                program: programId,
                            })}
                            method="post"
                            disableWhileProcessing
                            resetOnSuccess
                            transform={(data) => ({
                                ...data,
                                recorded_at:
                                    formatDateForSubmit(dateRequested),
                                item_details: selectedItemIds.map((itemId) => ({
                                    item_id: Number(itemId),
                                    quantity: Number(
                                        itemDetails[itemId]?.quantity ?? 1,
                                    ),
                                    specification:
                                        itemDetails[itemId]?.specification ??
                                        '',
                                })),
                            })}
                            onSuccess={() => {
                                resetCreateForm();
                                setCreateOpen(false);
                                toast.success(
                                    'Assistance created successfully.',
                                );
                                onAssistanceCreated?.();
                            }}
                            className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    {isOrganization ? (
                                        <BeneficiarySearchCombobox
                                            key={beneficiaryFieldKey}
                                            departmentSlug={departmentSlug}
                                            beneficiaryType="organization"
                                            label="Organization"
                                            name="beneficiary_id"
                                            value={selectedBeneficiaryId}
                                            onChange={(id) =>
                                                setSelectedBeneficiaryId(id)
                                            }
                                            error={errors.beneficiary_id}
                                        />
                                    ) : (
                                        <BeneficiarySearchCombobox
                                            key={beneficiaryFieldKey}
                                            departmentSlug={departmentSlug}
                                            beneficiaryType="individual"
                                            value={selectedBeneficiaryId}
                                            onChange={(id) =>
                                                setSelectedBeneficiaryId(id)
                                            }
                                            error={errors.beneficiary_id}
                                        />
                                    )}

                                    <div className="space-y-2">
                                        <Label htmlFor="assistance-mode">
                                            Mode of request
                                        </Label>
                                        <Select
                                            name="mode_of_request_id"
                                            defaultValue=""
                                            value={undefined}
                                        >
                                            <SelectTrigger
                                                id="assistance-mode"
                                                className={selectClassName}
                                            >
                                                <SelectValue placeholder="Select mode of request" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {modeOfRequestOptions.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.id}
                                                            value={String(
                                                                option.id,
                                                            )}
                                                        >
                                                            {option.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.mode_of_request_id}
                                        />
                                    </div>

                                    <AssistanceDatePicker
                                        id="assistance-date-requested"
                                        label="Recorded at"
                                        selected={dateRequested}
                                        onSelect={setDateRequested}
                                        open={dateRequestedOpen}
                                        onOpenChange={setDateRequestedOpen}
                                        error={errors.recorded_at}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="assistance-items">
                                            Items
                                        </Label>
                                        <MultiSelect
                                            options={programItemSelectOptions}
                                            selected={selectedItemIds}
                                            onChange={setSelectedItemIds}
                                            placeholder="Choose items..."
                                            className="w-full"
                                        />
                                        <InputError
                                            message={
                                                errors.item_details ??
                                                errors.item_ids
                                            }
                                        />
                                    </div>

                                    {selectedItemIds.length > 0 ? (
                                        <div className="space-y-3">
                                            <Label>Item details</Label>
                                            {selectedItemIds.map(
                                                (selectedItemId, index) => {
                                                    const item =
                                                        programItems.find(
                                                            ({ id }) =>
                                                                String(id) ===
                                                                selectedItemId,
                                                        );

                                                    if (!item) {
                                                        return null;
                                                    }

                                                    const detail = itemDetails[
                                                        selectedItemId
                                                    ] ?? {
                                                        quantity: '1',
                                                        specification: '',
                                                    };

                                                    return (
                                                        <div
                                                            key={selectedItemId}
                                                            className="grid gap-3 rounded-xl border p-3"
                                                        >
                                                            <p className="text-sm font-medium">
                                                                {item.name}
                                                            </p>
                                                            <div className="grid gap-2 sm:grid-cols-2">
                                                                <div className="space-y-2">
                                                                    <Label
                                                                        htmlFor={`assistance-item-quantity-${selectedItemId}`}
                                                                    >
                                                                        Quantity
                                                                    </Label>
                                                                    <Input
                                                                        id={`assistance-item-quantity-${selectedItemId}`}
                                                                        type="number"
                                                                        min={1}
                                                                        step={1}
                                                                        value={
                                                                            detail.quantity
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            setItemDetails(
                                                                                (
                                                                                    current,
                                                                                ) => ({
                                                                                    ...current,
                                                                                    [selectedItemId]:
                                                                                        {
                                                                                            ...detail,
                                                                                            quantity:
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                        },
                                                                                }),
                                                                            )
                                                                        }
                                                                    />
                                                                    <InputError
                                                                        message={
                                                                            errors[
                                                                                `item_details.${index}.quantity`
                                                                            ]
                                                                        }
                                                                    />
                                                                </div>
                                                                <div className="space-y-2">
                                                                    <Label
                                                                        htmlFor={`assistance-item-specification-${selectedItemId}`}
                                                                    >
                                                                        Specification
                                                                    </Label>
                                                                    <Input
                                                                        id={`assistance-item-specification-${selectedItemId}`}
                                                                        value={
                                                                            detail.specification
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            setItemDetails(
                                                                                (
                                                                                    current,
                                                                                ) => ({
                                                                                    ...current,
                                                                                    [selectedItemId]:
                                                                                        {
                                                                                            ...detail,
                                                                                            specification:
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                        },
                                                                                }),
                                                                            )
                                                                        }
                                                                        placeholder="Optional specification"
                                                                    />
                                                                    <InputError
                                                                        message={
                                                                            errors[
                                                                                `item_details.${index}.specification`
                                                                            ]
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                },
                                            )}
                                        </div>
                                    ) : null}

                                    <div className="space-y-2">
                                        <Label htmlFor="assistance-remark">
                                            Remark
                                        </Label>
                                        <Textarea
                                            id="assistance-remark"
                                            name="remark"
                                            placeholder="Optional notes about this request"
                                            rows={3}
                                        />
                                        <InputError message={errors.remark} />
                                    </div>

                                    <DrawerFooter className="px-0">
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                programItems.length === 0
                                            }
                                        >
                                            {processing
                                                ? 'Creating...'
                                                : 'Add assistance'}
                                        </Button>
                                        <DrawerClose asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                            >
                                                Cancel
                                            </Button>
                                        </DrawerClose>
                                    </DrawerFooter>
                                </>
                            )}
                        </Form>
                    ) : null}
                </DrawerContent>
            </Drawer>
        </>
    );
}
