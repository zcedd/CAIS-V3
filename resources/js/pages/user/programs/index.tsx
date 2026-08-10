import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import InputError from '@/components/input-error';
import { ProgramFieldsEditor } from '@/components/program-fields-editor';
import { ServerPagination } from '@/components/server-pagination';
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
import { Textarea } from '@/components/ui/textarea';
import {
    ProgramFolderCard,
    type ProgramListRow,
} from '@/pages/user/programs/program-folder-card';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
    store as storeProgram,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import type { ProgramFieldDefinition } from '@/types/program-field';
import { cn } from '@/lib/utils';
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDownIcon,
    Plus,
    RotateCcw,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type SelectOption = {
    id: number;
    name: string;
    unit: string;
    year: string;
};

type ProgramRow = ProgramListRow & {
    department?: DepartmentSummary | null;
};

type PaginatedPrograms = ServerPaginationMeta & {
    data: ProgramRow[];
};

type ProgramListFilters = {
    search: string;
    type: string[];
    status: string[];
    page?: number;
    per_page?: number;
};

const programTypeOptions = [
    { label: 'Individual', value: 'individual' },
    { label: 'Organization', value: 'organization' },
] as const;

const programStatusOptions = [
    { label: 'Open', value: 'open' },
    { label: 'Closed', value: 'closed' },
] as const;

function buildProgramsQuery(
    filters: ProgramListFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};
    const search = filters.search.trim();

    if (search !== '') {
        query.search = search;
    }

    if (filters.type.length > 0) {
        query.type = filters.type;
    }

    if (filters.status.length > 0) {
        query.status = filters.status;
    }

    if (filters.page !== undefined && filters.page > 1) {
        query.page = String(filters.page);
    }

    if (filters.per_page !== undefined && filters.per_page !== 12) {
        query.per_page = String(filters.per_page);
    }

    return query;
}
function formatDateForSubmit(date: Date | undefined): string | undefined {
    if (!date) {
        return undefined;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

type ProgramDatePickerProps = {
    id: string;
    label: string;
    selected: Date | undefined;
    onSelect: (date: Date | undefined) => void;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    error?: string;
};

function ProgramDatePicker({
    id,
    label,
    selected,
    onSelect,
    open,
    onOpenChange,
    error,
}: ProgramDatePickerProps) {
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

export default function UserProgramsIndex({
    programs,
    department,
    search: initialSearch,
    type: initialType,
    status: initialStatus,
    per_page: initialPerPage,
    funds,
    items,
}: {
    programs: PaginatedPrograms;
    department: DepartmentSummary | null;
    search: string;
    type: string[];
    status: string[];
    per_page: number;
    funds?: SelectOption[];
    items?: SelectOption[];
}) {
    const [searchQuery, setSearchQuery] = useState(initialSearch);
    const [createOpen, setCreateOpen] = useState(false);
    const [startAtOpen, setStartAtOpen] = useState(false);
    const [startAt, setStartAt] = useState<Date | undefined>(undefined);
    const [endAtOpen, setEndAtOpen] = useState(false);
    const [endAt, setEndAt] = useState<Date | undefined>(undefined);
    const [selectedFundIds, setSelectedFundIds] = useState<string[]>([]);
    const [selectedItemIds, setSelectedItemIds] = useState<string[]>([]);
    const [fields, setFields] = useState<ProgramFieldDefinition[]>([]);

    const fundOptions = (funds ?? []).map((fund) => ({
        value: String(fund.id),
        label: String(`${fund.name} (${fund.year})`),
    }));

    const itemOptions = (items ?? []).map((item) => ({
        value: String(item.id),
        label: String(`${item.name} (${item.unit})`),
    }));

    const resetCreateForm = () => {
        setStartAt(undefined);
        setEndAt(undefined);
        setStartAtOpen(false);
        setEndAtOpen(false);
        setSelectedFundIds([]);
        setSelectedItemIds([]);
        setFields([]);
    };

    useEffect(() => {
        setSearchQuery(initialSearch);
    }, [initialSearch]);

    useEffect(() => {
        if (!createOpen) {
            resetCreateForm();
        }
    }, [createOpen]);

    const navigateWithFilters = useCallback(
        (overrides: Partial<ProgramListFilters> = {}) => {
            if (!department?.slug) {
                return;
            }

            const next: ProgramListFilters = {
                search: overrides.search ?? searchQuery,
                type: overrides.type ?? initialType,
                status: overrides.status ?? initialStatus,
                page: overrides.page ?? 1,
                per_page: overrides.per_page ?? initialPerPage,
            };

            router.get(
                departmentProgramsIndex.url(
                    { department: department.slug },
                    { query: buildProgramsQuery(next) },
                ),
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: [
                        'programs',
                        'search',
                        'type',
                        'status',
                        'per_page',
                        'department',
                    ],
                },
            );
        },
        [
            department?.slug,
            searchQuery,
            initialType,
            initialStatus,
            initialPerPage,
        ],
    );
    if (department?.slug) {
        const programsHref = departmentProgramsIndex.url(department.slug);
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Programs',
                    href: programsHref,
                },
            ] satisfies BreadcrumbItem[],
        });
    }

    useEffect(() => {
        const trimmed = searchQuery.trim();

        if (trimmed === initialSearch.trim() || !department?.slug) {
            return;
        }

        const handle = window.setTimeout(() => {
            navigateWithFilters({ search: trimmed });
        }, 400);

        return () => window.clearTimeout(handle);
    }, [searchQuery, initialSearch, department?.slug, navigateWithFilters]);

    const heading = department ? `${department.name} programs` : 'Programs';
    const canCreate = Boolean(department?.slug);
    const isFiltered =
        initialSearch.trim() !== '' ||
        initialType.length > 0 ||
        initialStatus.length > 0;

    return (
        <>
            <Head title="Department programs" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {department
                                ? 'Programs assigned to your department.'
                                : 'You are not linked to a department yet, so no programs are shown.'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div
                        className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
                        data-tour="programs-filters"
                    >
                        <Input
                            type="text"
                            name="search"
                            autoComplete="off"
                            placeholder="Search by program name"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className={cn(
                                'max-w-md',
                                searchQuery.trim().length > 0 &&
                                    'border-primary bg-primary/5 ring-1 ring-primary/30',
                            )}
                        />
                        <DataTableFacetedFilter
                            filterValue={initialType}
                            title="Type"
                            options={[...programTypeOptions]}
                            onFilterChange={(values) =>
                                navigateWithFilters({ type: values })
                            }
                        />
                        <DataTableFacetedFilter
                            filterValue={initialStatus}
                            title="Status"
                            options={[...programStatusOptions]}
                            onFilterChange={(values) =>
                                navigateWithFilters({ status: values })
                            }
                        />
                        {isFiltered ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-8 px-2 lg:px-3"
                                onClick={() => {
                                    setSearchQuery('');
                                    navigateWithFilters({
                                        search: '',
                                        type: [],
                                        status: [],
                                    });
                                }}
                            >
                                Reset
                                <X className="ml-2 size-4" />
                            </Button>
                        ) : null}
                    </div>
                    <Button
                        type="button"
                        disabled={!canCreate}
                        onClick={() => setCreateOpen(true)}
                        data-tour="programs-create"
                    >
                        <Plus className="size-4" />
                        Create program
                    </Button>
                </div>
                <div data-tour="programs-list">
                    {programs.data.length === 0 ? (
                        <div className="flex flex-col items-start gap-1 border-t border-foreground/10 py-12">
                            <p className="text-sm font-medium">
                                No programs found
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Nothing matches your filters.
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4">
                            <div className="grid auto-rows-min gap-x-5 gap-y-7 md:grid-cols-2 lg:grid-cols-4">
                                {programs.data.map((program) =>
                                    department?.slug ? (
                                        <Link
                                            key={program.id}
                                            href={departmentProgramShow.url({
                                                department: department.slug,
                                                program: program.id,
                                            })}
                                            prefetch
                                            className="block h-full rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                        >
                                            <ProgramFolderCard
                                                program={program}
                                            />
                                        </Link>
                                    ) : (
                                        <div key={program.id}>
                                            <ProgramFolderCard
                                                program={program}
                                            />
                                        </div>
                                    ),
                                )}
                            </div>

                            <ServerPagination
                                pagination={programs}
                                onPageChange={(page) =>
                                    navigateWithFilters({ page })
                                }
                                onPerPageChange={(perPage) =>
                                    navigateWithFilters({
                                        per_page: perPage,
                                        page: 1,
                                    })
                                }
                            />
                        </div>
                    )}
                </div>
            </div>

            <Drawer
                open={createOpen}
                onOpenChange={setCreateOpen}
                direction="right"
            >
                <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                    <DrawerHeader>
                        <DrawerTitle>Create program</DrawerTitle>
                        <DrawerDescription>
                            Add a new program for {department?.name ?? 'your'}.
                        </DrawerDescription>
                    </DrawerHeader>
                    {canCreate && department && (
                        <Form
                            action={storeProgram.url({
                                department: department.slug,
                            })}
                            method="post"
                            disableWhileProcessing
                            resetOnSuccess
                            transform={(data) => ({
                                ...data,
                                start_at: formatDateForSubmit(startAt),
                                end_at: formatDateForSubmit(endAt),
                                fund_ids: selectedFundIds,
                                item_ids: selectedItemIds,
                                fields: fields.map((field, index) => ({
                                    ...field,
                                    sort_order: index,
                                })),
                            })}
                            onSuccess={() => {
                                resetCreateForm();
                                setCreateOpen(false);
                                toast.success('Program created successfully.');
                            }}
                            className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="program-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="program-name"
                                            name="name"
                                            placeholder="Program name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="program-descriptions">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="program-descriptions"
                                            name="descriptions"
                                            placeholder="Describe the program"
                                            rows={4}
                                        />
                                        <InputError
                                            message={errors.descriptions}
                                        />
                                    </div>

                                    <ProgramDatePicker
                                        id="program-start-at"
                                        label="Start date"
                                        selected={startAt}
                                        onSelect={setStartAt}
                                        open={startAtOpen}
                                        onOpenChange={setStartAtOpen}
                                        error={errors.start_at}
                                    />

                                    <ProgramDatePicker
                                        id="program-end-at"
                                        label="End date"
                                        selected={endAt}
                                        onSelect={setEndAt}
                                        open={endAtOpen}
                                        onOpenChange={setEndAtOpen}
                                        error={errors.end_at}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="program-funds">
                                            Funds
                                        </Label>
                                        <MultiSelect
                                            options={fundOptions}
                                            selected={selectedFundIds}
                                            onChange={setSelectedFundIds}
                                            placeholder="Choose funds..."
                                            className="w-full"
                                        />
                                        <InputError message={errors.fund_ids} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="program-items">
                                            Items
                                        </Label>
                                        <MultiSelect
                                            options={itemOptions}
                                            selected={selectedItemIds}
                                            onChange={setSelectedItemIds}
                                            placeholder="Choose items..."
                                            className="w-full"
                                        />
                                        <InputError message={errors.item_ids} />
                                    </div>

                                    <ProgramFieldsEditor
                                        fields={fields}
                                        onChange={setFields}
                                        errors={errors}
                                    />

                                    <div className="flex items-start gap-3">
                                        <Input
                                            id="program-is-organization"
                                            type="checkbox"
                                            name="is_organization"
                                            value="1"
                                            className="mt-1 size-4 shrink-0 rounded border-input"
                                        />
                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor="program-is-organization"
                                                className="font-normal"
                                            >
                                                Organization program
                                            </Label>
                                            <p className="text-sm text-muted-foreground">
                                                Enable when assistance is for
                                                organizations rather than
                                                individuals.
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
                                                : 'Create program'}
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
                    )}
                </DrawerContent>
            </Drawer>
        </>
    );
}
