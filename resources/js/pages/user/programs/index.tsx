import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import InputError from '@/components/input-error';
import { ProjectCard } from '@/components/skeleton/project-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    index as departmentProgramsIndex,
    show as departmentProgramShow,
    store as storeProgram,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import { cn } from '@/lib/utils';
import {
    Form,
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
} from '@inertiajs/react';
import { CalendarDays, ChevronDownIcon, Plus, RotateCcw, X } from 'lucide-react';
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

type ProgramRow = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    is_closed: boolean | null;
    is_organization: boolean | null;
    department?: DepartmentSummary | null;
};

type PaginatedPrograms = {
    data: ProgramRow[];
};

type ProgramListFilters = {
    search: string;
    type: string[];
    status: string[];
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
    funds,
    items,
}: {
    programs: PaginatedPrograms;
    department: DepartmentSummary | null;
    search: string;
    type: string[];
    status: string[];
    funds: SelectOption[];
    items: SelectOption[];
}) {
    const [searchQuery, setSearchQuery] = useState(initialSearch);
    const [createOpen, setCreateOpen] = useState(false);
    const [startAtOpen, setStartAtOpen] = useState(false);
    const [startAt, setStartAt] = useState<Date | undefined>(undefined);
    const [endAtOpen, setEndAtOpen] = useState(false);
    const [endAt, setEndAt] = useState<Date | undefined>(undefined);
    const [selectedFundIds, setSelectedFundIds] = useState<string[]>([]);
    const [selectedItemIds, setSelectedItemIds] = useState<string[]>([]);

    const fundOptions = funds.map((fund) => ({
        value: String(fund.id),
        label: String(`${fund.name} (${fund.year})`),
    }));

    const itemOptions = items.map((item) => ({
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
                        'department',
                    ],
                    reset: ['programs'],
                },
            );
        },
        [
            department?.slug,
            searchQuery,
            initialType,
            initialStatus,
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
    }, [
        searchQuery,
        initialSearch,
        department?.slug,
        navigateWithFilters,
    ]);

    const heading = department ? `${department.name} programs` : 'Programs';
    const canCreate = Boolean(department?.slug);
    const isFiltered =
        initialSearch.trim() !== '' ||
        initialType.length > 0 ||
        initialStatus.length > 0;

    return (
        <>
            <Head title="Department programs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
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
                    <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
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
                    >
                        <Plus className="size-4" />
                        Create program
                    </Button>
                </div>
                {programs.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No programs match your filters.
                    </p>
                ) : (
                    <InfiniteScroll
                        data="programs"
                        onlyNext
                        next={({ loading }) =>
                            loading ? (
                                <div className="mt-4 grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <ProjectCard />
                                    <ProjectCard />
                                    <ProjectCard />
                                </div>
                            ) : null
                        }
                    >
                        <div className="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {programs.data.map((program) => {
                                const card = (
                                    <Card className="flex h-full flex-col bg-card transition-colors hover:border-primary/50 hover:shadow-sm">
                                        <CardHeader className="gap-1">
                                            <CardTitle className="text-lg">
                                                {program.name}
                                            </CardTitle>
                                            <CardDescription>
                                                <span className="font-medium text-foreground">
                                                    {program.is_organization
                                                        ? 'Organization'
                                                        : 'Individual'}
                                                </span>
                                                {program.is_closed ? (
                                                    <Badge variant="destructive">
                                                        Closed
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="default">
                                                        Open
                                                    </Badge>
                                                )}
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="flex flex-1 flex-col gap-2 text-sm text-muted-foreground">
                                            <p className="line-clamp-4">
                                                {program.descriptions}
                                            </p>
                                            <p>
                                                <span className="font-medium text-foreground">
                                                    Period:{' '}
                                                </span>
                                                {program.start_at ?? '—'}
                                                {program.end_at
                                                    ? ` – ${program.end_at}`
                                                    : ''}
                                            </p>
                                        </CardContent>
                                    </Card>
                                );

                                return department?.slug ? (
                                    <Link
                                        key={program.id}
                                        href={departmentProgramShow.url({
                                            department: department.slug,
                                            program: program.id,
                                        })}
                                        prefetch
                                        className="block h-full rounded-xl ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        {card}
                                    </Link>
                                ) : (
                                    <div key={program.id}>{card}</div>
                                );
                            })}
                        </div>
                    </InfiniteScroll>
                )}
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
