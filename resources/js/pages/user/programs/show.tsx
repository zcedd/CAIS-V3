import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import InputError from '@/components/input-error';
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
    createUserProgramAssistanceColumns,
    userProgramAssistanceInitialColumnVisibility,
    type UserProgramAssistanceRow,
} from '@/pages/user/programs/assistance-columns';
import {
    AssistanceDataTableToolbar,
    type AssistanceModeOption,
    type AssistanceProgramItemOption,
    type AssistanceRequestSubStatusOption,
    type AssistanceSelectOption,
    type AssistanceTableFilters,
    type ModeFilterOption,
    type StatusFilterOption,
} from '@/pages/user/programs/assistance-toolbar';
import { ProgramKpiCards } from '@/pages/user/programs/kpi-cards';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
    update as updateProgram,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import type { ProgramSummary } from '@/types/program';

import {
    Form,
    Head,
    Link,
    router,
    setLayoutProps,
    WhenVisible,
} from '@inertiajs/react';

import { CalendarDays, ChevronDownIcon, Pencil, RotateCcw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

const ASSISTANCE_TABLE_PARTIAL_PROPS = ['assistances'] as const;

const ASSISTANCE_TABLE_DEFER_GROUP_PROPS = [
    'assistances',
    'mode_options',
    'status_options',
    'mode_of_request_options',
    'program_items',
    'request_sub_status_options',
] as const;

const ASSISTANCE_TABLE_SKELETON_COLUMNS = 14;

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

type ProgramDetail = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    start_at_input: string | null;
    end_at_input: string | null;
    is_closed: boolean | null;
    is_organization: boolean | null;
    department_id: number;
    department?: DepartmentSummary | null;
    fund_ids: number[];
    item_ids: number[];
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

function parseProgramDateInput(
    value: string | null | undefined,
): Date | undefined {
    if (!value) {
        return undefined;
    }

    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
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

type PaginatedAssistances = {
    data: UserProgramAssistanceRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

function buildTableQuery(
    current: AssistanceTableFilters & {
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
    },
    overrides: Partial<
        AssistanceTableFilters & {
            sort: string;
            direction: 'asc' | 'desc';
            per_page: number;
            page: number;
        }
    > = {},
): Record<string, string | number | string[]> {
    const search = overrides.search ?? current.search;
    const status = overrides.status ?? current.status;
    const mode = overrides.mode ?? current.mode;

    const query: Record<string, string | number | string[]> = {
        sort: overrides.sort ?? current.sort,
        direction: overrides.direction ?? current.direction,
        per_page: overrides.per_page ?? current.per_page,
        page: overrides.page ?? 1,
    };

    if (search !== '') {
        query.search = search;
    }

    if (status.length > 0) {
        query.status = status;
    }

    if (mode.length > 0) {
        query.mode = mode;
    }

    return query;
}

function isAssistancesTableReady(props: {
    assistances?: PaginatedAssistances;
    mode_options?: StatusFilterOption[];
    status_options?: StatusFilterOption[];
    mode_of_request_options?: AssistanceModeOption[];
    program_items?: AssistanceProgramItemOption[];
    request_sub_status_options?: AssistanceRequestSubStatusOption[];
}): props is {
    assistances: PaginatedAssistances;
    mode_options: StatusFilterOption[];
    status_options: StatusFilterOption[];
    mode_of_request_options: AssistanceModeOption[];
    program_items: AssistanceProgramItemOption[];
    request_sub_status_options: AssistanceRequestSubStatusOption[];
} {
    return (
        props.assistances !== undefined &&
        props.mode_options !== undefined &&
        props.status_options !== undefined &&
        props.mode_of_request_options !== undefined &&
        props.program_items !== undefined &&
        props.request_sub_status_options !== undefined
    );
}

function isAssistancesPartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        ASSISTANCE_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof ASSISTANCE_TABLE_PARTIAL_PROPS)[number],
        ),
    );
}

type ProgramAssistanceTableProps = {
    assistances: PaginatedAssistances;
    assistanceColumns: ReturnType<typeof createUserProgramAssistanceColumns>;
    tableFilters: AssistanceTableFilters;
    tableState: {
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
        search: string;
        status: string[];
        mode: string[];
    };
    statusOptions: StatusFilterOption[];
    modeOptions: ModeFilterOption[];
    isLoading: boolean;
    departmentSlug: string;
    programId: number;
    programName: string;
    isOrganization: boolean;
    canCreateAssistance: boolean;
    modeOfRequestOptions: AssistanceModeOption[];
    programItems: AssistanceProgramItemOption[];
    onVisitTable: (
        overrides: Partial<
            AssistanceTableFilters & {
                sort: string;
                direction: 'asc' | 'desc';
                per_page: number;
                page: number;
            }
        >,
    ) => void;
};

type ProgramAssistanceTableSectionProps = {
    assistances: PaginatedAssistances;
    tableFilters: AssistanceTableFilters;
    tableState: ProgramAssistanceTableProps['tableState'];
    statusOptions: StatusFilterOption[];
    modeOptions: ModeFilterOption[];
    isLoading: boolean;
    departmentSlug: string;
    programId: number;
    programName: string;
    isOrganization: boolean;
    canCreateAssistance: boolean;
    modeOfRequestOptions: AssistanceModeOption[];
    programItems: AssistanceProgramItemOption[];
    requestSubStatusOptions: AssistanceRequestSubStatusOption[];
    onVisitTable: ProgramAssistanceTableProps['onVisitTable'];
};

function ProgramAssistanceTableSection({
    assistances,
    tableFilters,
    tableState,
    statusOptions,
    modeOptions,
    isLoading,
    departmentSlug,
    programId,
    programName,
    isOrganization,
    canCreateAssistance,
    modeOfRequestOptions,
    programItems,
    requestSubStatusOptions,
    onVisitTable,
}: ProgramAssistanceTableSectionProps) {
    const assistanceColumns = useMemo(
        () =>
            createUserProgramAssistanceColumns({
                departmentSlug,
                programId,
                programName,
                isOrganization,
                modeOfRequestOptions,
                programItems,
                requestSubStatusOptions,
                onAssistanceUpdated: () => onVisitTable({ page: 1 }),
            }),
        [
            departmentSlug,
            programId,
            programName,
            isOrganization,
            modeOfRequestOptions,
            programItems,
            requestSubStatusOptions,
            onVisitTable,
        ],
    );

    return (
        <ProgramAssistanceTable
            assistances={assistances}
            assistanceColumns={assistanceColumns}
            tableFilters={tableFilters}
            tableState={tableState}
            statusOptions={statusOptions}
            modeOptions={modeOptions}
            isLoading={isLoading}
            departmentSlug={departmentSlug}
            programId={programId}
            programName={programName}
            isOrganization={isOrganization}
            canCreateAssistance={canCreateAssistance}
            modeOfRequestOptions={modeOfRequestOptions}
            programItems={programItems}
            onVisitTable={onVisitTable}
        />
    );
}

function ProgramAssistanceTable({
    assistances,
    assistanceColumns,
    tableFilters,
    tableState,
    statusOptions,
    modeOptions,
    isLoading,
    departmentSlug,
    programId,
    programName,
    isOrganization,
    canCreateAssistance,
    modeOfRequestOptions,
    programItems,
    onVisitTable,
}: ProgramAssistanceTableProps) {
    return (
        <DataTable
            columns={assistanceColumns}
            data={assistances.data}
            emptyMessage="No assistance records for this program."
            manualPagination
            manualSorting
            manualFiltering
            serverPagination={assistances}
            serverSorting={{
                sort: tableState.sort,

                direction: tableState.direction,
            }}
            partialReloadOnly={[...ASSISTANCE_TABLE_PARTIAL_PROPS]}
            isLoading={isLoading}
            loadingFallback={
                <DataTableSkeleton
                    columnCount={ASSISTANCE_TABLE_SKELETON_COLUMNS}
                    rowCount={tableState.per_page}
                />
            }
            onServerSortingChange={(columnId, nextDirection) => {
                onVisitTable({
                    sort: columnId,

                    direction: nextDirection,

                    page: 1,
                });
            }}
            onPerPageChange={(nextPerPage) => {
                onVisitTable({ per_page: nextPerPage, page: 1 });
            }}
            toolbar={(table, columnVisibility) => (
                <AssistanceDataTableToolbar
                    table={table}
                    columnVisibility={columnVisibility}
                    filters={tableFilters}
                    statusOptions={statusOptions}
                    modeOptions={modeOptions}
                    onFiltersChange={onVisitTable}
                    sort={tableState.sort}
                    direction={tableState.direction}
                    departmentSlug={departmentSlug}
                    programId={programId}
                    programName={programName}
                    isOrganization={isOrganization}
                    canCreate={canCreateAssistance}
                    modeOfRequestOptions={modeOfRequestOptions}
                    programItems={programItems}
                    onAssistanceCreated={() => onVisitTable({ page: 1 })}
                />
            )}
            initialColumnVisibility={
                userProgramAssistanceInitialColumnVisibility
            }
            enableRowSelection
        />
    );
}

export default function UserProgramShow({
    program,
    summary,
    department,
    funds,
    items,
    assistances,
    sort,
    direction,
    per_page,
    search,
    status,
    mode,
    mode_options,
    status_options,
    mode_of_request_options,
    organization_options,
    program_items,
    request_sub_status_options,
}: {
    program: ProgramDetail;
    summary: ProgramSummary;
    department: DepartmentSummary | null;
    funds?: SelectOption[];
    items?: SelectOption[];
    assistances?: PaginatedAssistances;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    search: string;
    status: string[];
    mode: string[];
    mode_options?: ModeFilterOption[];
    status_options?: StatusFilterOption[];
    mode_of_request_options?: AssistanceModeOption[];
    organization_options?: AssistanceSelectOption[];
    program_items?: AssistanceProgramItemOption[];
    request_sub_status_options?: AssistanceRequestSubStatusOption[];
}) {
    const [editOpen, setEditOpen] = useState(false);
    const [editFormKey, setEditFormKey] = useState(0);
    const [startAtOpen, setStartAtOpen] = useState(false);
    const [startAt, setStartAt] = useState<Date | undefined>(undefined);
    const [endAtOpen, setEndAtOpen] = useState(false);
    const [endAt, setEndAt] = useState<Date | undefined>(undefined);
    const [selectedFundIds, setSelectedFundIds] = useState<string[]>([]);
    const [selectedItemIds, setSelectedItemIds] = useState<string[]>([]);

    const fundOptions = (funds ?? []).map((fund) => ({
        value: String(fund.id),
        label: String(`${fund.name} (${fund.year})`),
    }));

    const itemOptions = (items ?? []).map((item) => ({
        value: String(item.id),
        label: String(`${item.name} (${item.unit})`),
    }));

    const resetEditForm = useCallback(() => {
        setStartAt(undefined);
        setEndAt(undefined);
        setStartAtOpen(false);
        setEndAtOpen(false);
        setSelectedFundIds([]);
        setSelectedItemIds([]);
    }, []);

    const populateEditForm = useCallback(() => {
        setStartAt(parseProgramDateInput(program.start_at_input));
        setEndAt(parseProgramDateInput(program.end_at_input));
        setSelectedFundIds(program.fund_ids.map(String));
        setSelectedItemIds(program.item_ids.map(String));
    }, [program]);

    useEffect(() => {
        if (!editOpen || (funds !== undefined && items !== undefined)) {
            return;
        }

        router.reload({
            only: ['funds', 'items'],
        });
    }, [editOpen, funds, items]);

    useEffect(() => {
        if (!editOpen) {
            resetEditForm();

            return;
        }

        populateEditForm();
        setEditFormKey((key) => key + 1);
    }, [editOpen, populateEditForm, resetEditForm]);
    const [tableState, setTableState] = useState({
        sort,
        direction,
        per_page,
        search,
        status,
        mode,
    });

    const [isTableReloading, setIsTableReloading] = useState(false);

    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (isAssistancesPartialVisit(event.detail.visit.only)) {
                setIsTableReloading(true);
            }
        });

        const removeFinish = router.on('finish', () => {
            setIsTableReloading(false);
        });

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    const tableFilters: AssistanceTableFilters = {
        search: tableState.search,
        status: tableState.status,
        mode: tableState.mode,
    };

    useEffect(() => {
        if (!department?.slug) {
            return;
        }

        const programsHref = departmentProgramsIndex.url(department.slug);
        const selfHref = departmentProgramShow.url({
            department: department.slug,
            program: program.id,
        });

        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Programs',
                    href: programsHref,
                },
                {
                    title: program.name,
                    href: selfHref,
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department?.slug, program.id, program.name]);

    const visitTable = useCallback(
        (
            overrides: Partial<
                AssistanceTableFilters & {
                    sort: string;
                    direction: 'asc' | 'desc';
                    per_page: number;
                    page: number;
                }
            > = {},
        ) => {
            if (!department?.slug) {
                return;
            }
            const next = { ...tableStateRef.current, ...overrides };
            setTableState(next);
            router.cancelAll();
            router.get(
                departmentProgramShow.url(
                    { department: department.slug, program: program.id },
                    {
                        query: buildTableQuery(next, overrides),
                    },
                ),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: [...ASSISTANCE_TABLE_PARTIAL_PROPS],
                },
            );
        },

        [department?.slug, program.id],
    );

    const tableSkeleton = (
        <DataTableSkeleton
            columnCount={ASSISTANCE_TABLE_SKELETON_COLUMNS}
            rowCount={tableState.per_page}
        />
    );

    const heading = program.name;
    const canEdit = Boolean(department?.slug);
    const canCreateAssistance = Boolean(department?.slug && !program.is_closed);

    return (
        <>
            <Head title={heading} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {department
                                ? `${department.name} program details.`
                                : 'Program details.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit ? (
                            <Button
                                type="button"
                                onClick={() => setEditOpen(true)}
                            >
                                <Pencil className="size-4" />
                                Edit program
                            </Button>
                        ) : null}
                    </div>
                </div>
                <ProgramKpiCards summary={summary} />
                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Overview</CardTitle>
                        <CardDescription>
                            <div className="flex gap-2">
                                <Badge variant="default">
                                    {program.is_organization
                                        ? 'Organization'
                                        : 'Individual'}
                                </Badge>
                                <Badge
                                    variant={
                                        program.is_closed
                                            ? 'destructive'
                                            : 'default'
                                    }
                                >
                                    {program.is_closed ? 'Closed' : 'Open'}
                                </Badge>
                            </div>
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 text-sm text-muted-foreground">
                        <p className="whitespace-pre-wrap">
                            {program.descriptions ?? '—'}
                        </p>
                        <p>
                            <span className="font-medium text-foreground">
                                Period:{' '}
                            </span>
                            {program.start_at
                                ? new Date(program.start_at).toLocaleDateString(
                                      undefined,
                                      {
                                          year: 'numeric',
                                          month: 'short',
                                          day: 'numeric',
                                      },
                                  )
                                : '—'}
                            {program.end_at
                                ? ` – ${new Date(
                                      program.end_at,
                                  ).toLocaleDateString(undefined, {
                                      year: 'numeric',
                                      month: 'short',
                                      day: 'numeric',
                                  })}`
                                : ''}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Assistance</CardTitle>
                        <CardDescription>
                            Filter, sort, and manage assistance records for this
                            program.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <WhenVisible
                            data={[...ASSISTANCE_TABLE_DEFER_GROUP_PROPS]}
                            fallback={() => tableSkeleton}
                        >
                            {(() => {
                                const tableProps = {
                                    assistances,
                                    mode_options,
                                    status_options,
                                    mode_of_request_options,
                                    program_items,
                                    request_sub_status_options,
                                };

                                if (!isAssistancesTableReady(tableProps)) {
                                    return tableSkeleton;
                                }

                                return (
                                    <ProgramAssistanceTableSection
                                        assistances={tableProps.assistances}
                                        tableFilters={tableFilters}
                                        tableState={tableState}
                                        statusOptions={
                                            tableProps.status_options
                                        }
                                        modeOptions={tableProps.mode_options}
                                        isLoading={isTableReloading}
                                        departmentSlug={department?.slug ?? ''}
                                        programId={program.id}
                                        programName={program.name}
                                        isOrganization={
                                            program.is_organization ?? false
                                        }
                                        canCreateAssistance={
                                            canCreateAssistance
                                        }
                                        modeOfRequestOptions={
                                            tableProps.mode_of_request_options
                                        }
                                        programItems={tableProps.program_items}
                                        requestSubStatusOptions={
                                            tableProps.request_sub_status_options
                                        }
                                        onVisitTable={visitTable}
                                    />
                                );
                            })()}
                        </WhenVisible>
                    </CardContent>
                </Card>
            </div>

            <Drawer
                open={editOpen}
                onOpenChange={setEditOpen}
                direction="right"
            >
                <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                    <DrawerHeader>
                        <DrawerTitle>Edit program</DrawerTitle>
                        <DrawerDescription>
                            Update program details for{' '}
                            {department?.name ?? 'your department'}.
                        </DrawerDescription>
                    </DrawerHeader>
                    {canEdit && department && (
                        <Form
                            key={editFormKey}
                            action={updateProgram.url({
                                department: department.slug,
                                program: program.id,
                            })}
                            method="put"
                            disableWhileProcessing
                            transform={(data) => ({
                                ...data,
                                start_at: formatDateForSubmit(startAt),
                                end_at: formatDateForSubmit(endAt),
                                fund_ids: selectedFundIds,
                                item_ids: selectedItemIds,
                            })}
                            onSuccess={() => {
                                resetEditForm();
                                setEditOpen(false);
                                toast.success('Program updated successfully.');
                            }}
                            className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="edit-program-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="edit-program-name"
                                            name="name"
                                            defaultValue={program.name}
                                            placeholder="Program name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit-program-descriptions">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="edit-program-descriptions"
                                            name="descriptions"
                                            defaultValue={
                                                program.descriptions ?? ''
                                            }
                                            placeholder="Describe the program"
                                            rows={4}
                                        />
                                        <InputError
                                            message={errors.descriptions}
                                        />
                                    </div>

                                    <ProgramDatePicker
                                        id="edit-program-start-at"
                                        label="Start date"
                                        selected={startAt}
                                        onSelect={setStartAt}
                                        open={startAtOpen}
                                        onOpenChange={setStartAtOpen}
                                        error={errors.start_at}
                                    />

                                    <ProgramDatePicker
                                        id="edit-program-end-at"
                                        label="End date"
                                        selected={endAt}
                                        onSelect={setEndAt}
                                        open={endAtOpen}
                                        onOpenChange={setEndAtOpen}
                                        error={errors.end_at}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="edit-program-funds">
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
                                        <Label htmlFor="edit-program-items">
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
                                            id="edit-program-is-closed"
                                            type="checkbox"
                                            name="is_closed"
                                            value="1"
                                            defaultChecked={
                                                program.is_closed ?? false
                                            }
                                            className="mt-1 size-4 shrink-0 rounded border-input"
                                        />
                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor="edit-program-is-closed"
                                                className="font-normal"
                                            >
                                                Closed program
                                            </Label>
                                            <p className="text-sm text-muted-foreground">
                                                Mark when the program is no
                                                longer accepting assistance.
                                            </p>
                                        </div>
                                    </div>

                                    <DrawerFooter className="px-0">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving...'
                                                : 'Save changes'}
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
