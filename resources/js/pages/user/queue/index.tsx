'use client';

import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import InputError from '@/components/input-error';
import { SlaBadge } from '@/components/user/sla-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { DepartmentStaffOption } from '@/pages/user/programs/assistance-toolbar';
import { show as assistanceShow } from '@/routes/user/assistances';
import { claim as claimAssistance } from '@/routes/user/programs/assistances';
import {
    assign as bulkAssignQueue,
    index as departmentQueueIndex,
} from '@/routes/user/queue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import type { ColumnDef, Table, VisibilityState } from '@tanstack/react-table';
import { RotateCcw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

const QUEUE_TABLE_PARTIAL_PROPS = [
    'assistances',
    'search',
    'tab',
    'program',
    'status',
    'sla',
    'include_assigned',
] as const;

const DEFAULT_PER_PAGE = 25;

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type FilterOption = {
    label: string;
    value: string;
};

export type QueueAssistanceRow = {
    id: number;
    program_id: number;
    program_name: string;
    cais_number: string;
    beneficiary_name: string;
    request_status: string | null;
    request_sub_status: string | null;
    assigned_to_id: number | null;
    assignee_name: string | null;
    encoder_name: string | null;
    sla_due_at: string | null;
    sla_state: string | null;
    sla_label: string | null;
};

type PaginatedQueue = {
    data: QueueAssistanceRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type QueueFilters = {
    tab: 'mine' | 'team';
    search: string;
    program: string[];
    status: string[];
    sla: string[];
    include_assigned: boolean;
    page: number;
    per_page: number;
};

const slaFilterOptions = [
    { label: 'On time', value: 'on_time' },
    { label: 'Due soon', value: 'due_soon' },
    { label: 'Overdue', value: 'overdue' },
    { label: 'Paused', value: 'paused' },
];

function buildQueueQuery(
    filters: QueueFilters,
): Record<string, string | string[] | number | boolean> {
    const query: Record<string, string | string[] | number | boolean> = {
        tab: filters.tab,
    };
    const search = filters.search.trim();

    if (search !== '') {
        query.search = search;
    }

    if (filters.program.length > 0) {
        query.program = filters.program;
    }

    if (filters.status.length > 0) {
        query.status = filters.status;
    }

    if (filters.sla.length > 0) {
        query.sla = filters.sla;
    }

    if (filters.tab === 'team' && filters.include_assigned) {
        query.include_assigned = 1;
    }

    if (filters.page > 1) {
        query.page = `${filters.page}`;
    }

    if (filters.per_page !== DEFAULT_PER_PAGE) {
        query.per_page = `${filters.per_page}`;
    }

    return query;
}

function isQueuePartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        QUEUE_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof QUEUE_TABLE_PARTIAL_PROPS)[number],
        ),
    );
}

function createQueueColumns(
    departmentSlug: string,
    tab: 'mine' | 'team',
): ColumnDef<QueueAssistanceRow>[] {
    return [
        {
            id: 'select',
            enableHiding: false,
            header: ({ table }) => (
                <Checkbox
                    checked={table.getIsAllPageRowsSelected()}
                    onCheckedChange={(checked) =>
                        table.toggleAllPageRowsSelected(Boolean(checked))
                    }
                    aria-label="Select all"
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(checked) =>
                        row.toggleSelected(Boolean(checked))
                    }
                    aria-label="Select row"
                />
            ),
        },
        {
            accessorKey: 'cais_number',
            meta: { title: 'CAIS' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="CAIS" />
            ),
            cell: ({ row }) => (
                <Link
                    href={assistanceShow.url({
                        department: departmentSlug,
                        program: row.original.program_id,
                        assistance: row.original.id,
                    })}
                    className="font-medium text-primary hover:underline"
                >
                    {row.original.cais_number}
                </Link>
            ),
        },
        {
            accessorKey: 'beneficiary_name',
            meta: { title: 'Beneficiary' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Beneficiary" />
            ),
        },
        {
            accessorKey: 'program_name',
            meta: { title: 'Program' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Program" />
            ),
        },
        {
            accessorKey: 'request_status',
            meta: { title: 'Stage' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Stage" />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="font-medium">
                        {row.original.request_status ?? '—'}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {row.original.request_sub_status ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'sla_state',
            meta: { title: 'SLA' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="SLA" />
            ),
            cell: ({ row }) => <SlaBadge state={row.original.sla_state} />,
        },
        {
            accessorKey: 'assignee_name',
            meta: { title: 'Assignee' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Assignee" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.assignee_name ?? 'Unassigned'}
                </span>
            ),
        },
        {
            accessorKey: 'encoder_name',
            meta: { title: 'Encoded by' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Encoded by" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.encoder_name ?? '—'}
                </span>
            ),
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) =>
                tab === 'team' && row.original.assigned_to_id === null ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            router.patch(
                                claimAssistance.url({
                                    department: departmentSlug,
                                    program: row.original.program_id,
                                    assistance: row.original.id,
                                }),
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        toast.success('Assistance claimed.'),
                                },
                            );
                        }}
                    >
                        Claim
                    </Button>
                ) : null,
        },
    ];
}

function QueueToolbar({
    table,
    columnVisibility,
    filters,
    programOptions,
    statusOptions,
    onFiltersChange,
}: {
    table: Table<QueueAssistanceRow>;
    columnVisibility: VisibilityState;
    filters: QueueFilters;
    programOptions: FilterOption[];
    statusOptions: FilterOption[];
    onFiltersChange: (overrides: Partial<QueueFilters>) => void;
}) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

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

    const hasActiveFilters =
        filters.search.trim() !== '' ||
        filters.program.length > 0 ||
        filters.status.length > 0 ||
        filters.sla.length > 0 ||
        filters.include_assigned;

    return (
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <Input
                    placeholder="Search beneficiary, CAIS, or program..."
                    value={searchQuery}
                    onChange={(event) => setSearchQuery(event.target.value)}
                    className="h-9 max-w-sm"
                />
                <DataTableFacetedFilter
                    filterValue={filters.program}
                    title="Program"
                    options={programOptions}
                    onFilterChange={(values) =>
                        onFiltersChange({ program: values, page: 1 })
                    }
                />
                <DataTableFacetedFilter
                    filterValue={filters.status}
                    title="Stage"
                    options={statusOptions}
                    onFilterChange={(values) =>
                        onFiltersChange({ status: values, page: 1 })
                    }
                />
                <DataTableFacetedFilter
                    filterValue={filters.sla}
                    title="SLA"
                    options={slaFilterOptions}
                    onFilterChange={(values) =>
                        onFiltersChange({ sla: values, page: 1 })
                    }
                />
                {filters.tab === 'team' ? (
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={filters.include_assigned}
                            onCheckedChange={(checked) =>
                                onFiltersChange({
                                    include_assigned: Boolean(checked),
                                    page: 1,
                                })
                            }
                        />
                        Include assigned
                    </label>
                ) : null}
                {hasActiveFilters ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            onFiltersChange({
                                search: '',
                                program: [],
                                status: [],
                                sla: [],
                                include_assigned: false,
                                page: 1,
                            })
                        }
                    >
                        <RotateCcw className="size-4" />
                        Reset
                    </Button>
                ) : null}
            </div>
            <DataTableViewOptions
                table={table}
                columnVisibility={columnVisibility}
            />
        </div>
    );
}

export default function UserQueueIndex({
    department,
    tab: initialTab,
    search: initialSearch,
    program: initialProgram,
    status: initialStatus,
    sla: initialSla,
    include_assigned: initialIncludeAssigned,
    assistances,
    program_options,
    status_options,
    staff_options,
}: {
    department: DepartmentSummary;
    tab: 'mine' | 'team';
    search: string;
    program: string[] | number[];
    status: string[];
    sla: string[];
    include_assigned: boolean;
    assistances: PaginatedQueue;
    program_options: FilterOption[];
    status_options: FilterOption[];
    staff_options: DepartmentStaffOption[];
}) {
    const [tableState, setTableState] = useState({
        tab: initialTab,
        search: initialSearch,
        program: initialProgram.map(String),
        status: initialStatus,
        sla: initialSla,
        include_assigned: initialIncludeAssigned,
        per_page: assistances.per_page,
    });
    const [isTableReloading, setIsTableReloading] = useState(false);
    const [assigneeId, setAssigneeId] = useState('');
    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        setTableState((previous) => ({
            ...previous,
            tab: initialTab,
            search: initialSearch,
            program: initialProgram.map(String),
            status: initialStatus,
            sla: initialSla,
            include_assigned: initialIncludeAssigned,
            per_page: assistances.per_page,
        }));
    }, [
        initialTab,
        initialSearch,
        initialProgram,
        initialStatus,
        initialSla,
        initialIncludeAssigned,
        assistances.per_page,
    ]);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Queue',
                    href: departmentQueueIndex.url(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (isQueuePartialVisit(event.detail.visit.only)) {
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

    const visitTable = useCallback(
        (overrides: Partial<QueueFilters> = {}) => {
            const next: QueueFilters = {
                tab: overrides.tab ?? tableStateRef.current.tab,
                search: overrides.search ?? tableStateRef.current.search,
                program: overrides.program ?? tableStateRef.current.program,
                status: overrides.status ?? tableStateRef.current.status,
                sla: overrides.sla ?? tableStateRef.current.sla,
                include_assigned:
                    overrides.include_assigned ??
                    tableStateRef.current.include_assigned,
                page: overrides.page ?? 1,
                per_page: overrides.per_page ?? tableStateRef.current.per_page,
            };

            setTableState({
                tab: next.tab,
                search: next.search,
                program: next.program,
                status: next.status,
                sla: next.sla,
                include_assigned: next.include_assigned,
                per_page: next.per_page,
            });

            router.cancelAll();
            router.get(
                departmentQueueIndex.url(department.slug, {
                    query: buildQueueQuery(next),
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: [...QUEUE_TABLE_PARTIAL_PROPS],
                },
            );
        },
        [department.slug],
    );

    const columns = useMemo(
        () => createQueueColumns(department.slug, tableState.tab),
        [department.slug, tableState.tab],
    );

    const filters: QueueFilters = {
        ...tableState,
        page: assistances.current_page,
    };

    return (
        <>
            <Head title="Queue" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Queue
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Work assigned to you, or unassigned requests waiting
                            for the team.
                        </p>
                    </div>
                </div>

                <Tabs
                    value={tableState.tab}
                    onValueChange={(value) =>
                        visitTable({
                            tab: value === 'team' ? 'team' : 'mine',
                            page: 1,
                        })
                    }
                >
                    <TabsList>
                        <TabsTrigger value="mine">My queue</TabsTrigger>
                        <TabsTrigger value="team">Team queue</TabsTrigger>
                    </TabsList>
                </Tabs>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">
                            {tableState.tab === 'mine'
                                ? 'Assigned to me'
                                : 'Team queue'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <DataTable
                            columns={columns}
                            data={assistances.data}
                            emptyMessage={
                                tableState.tab === 'mine'
                                    ? 'Nothing is assigned to you.'
                                    : 'No matching requests in the team queue.'
                            }
                            manualPagination
                            manualFiltering
                            serverPagination={assistances}
                            partialReloadOnly={[...QUEUE_TABLE_PARTIAL_PROPS]}
                            isLoading={isTableReloading}
                            loadingFallback={
                                <DataTableSkeleton
                                    columnCount={8}
                                    rowCount={tableState.per_page}
                                />
                            }
                            onPerPageChange={(nextPerPage) => {
                                visitTable({
                                    per_page: nextPerPage,
                                    page: 1,
                                });
                            }}
                            onPageChange={(page) => {
                                visitTable({ page });
                            }}
                            enableRowSelection
                            toolbar={(table, columnVisibility) => (
                                <QueueToolbar
                                    table={table}
                                    columnVisibility={columnVisibility}
                                    filters={filters}
                                    programOptions={program_options}
                                    statusOptions={status_options}
                                    onFiltersChange={visitTable}
                                />
                            )}
                            selectionActions={({
                                table,
                                selectedCount,
                            }) =>
                                selectedCount > 0 ? (
                                    <Form
                                        {...bulkAssignQueue.form.patch(
                                            department.slug,
                                        )}
                                        disableWhileProcessing
                                        options={{ preserveScroll: true }}
                                        transform={(data) => ({
                                            ...data,
                                            assistance_ids: table
                                                .getSelectedRowModel()
                                                .rows.map(
                                                    (row) => row.original.id,
                                                ),
                                            assigned_to_id:
                                                assigneeId === '' ||
                                                assigneeId === 'unassigned'
                                                    ? null
                                                    : Number(assigneeId),
                                        })}
                                        onSuccess={() => {
                                            table.resetRowSelection();
                                            setAssigneeId('');
                                            toast.success(
                                                'Assistance assignment updated.',
                                            );
                                        }}
                                        className="flex flex-wrap items-end gap-2 rounded-lg border bg-muted/40 px-3 py-2"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <p className="text-sm text-muted-foreground">
                                                    {selectedCount} selected
                                                </p>
                                                <div className="space-y-1">
                                                    <Label htmlFor="queue-assignee">
                                                        Assign to
                                                    </Label>
                                                    <Select
                                                        value={assigneeId}
                                                        onValueChange={
                                                            setAssigneeId
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="queue-assignee"
                                                            className="h-9 w-56"
                                                        >
                                                            <SelectValue placeholder="Choose staff" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="unassigned">
                                                                Unassigned
                                                            </SelectItem>
                                                            {staff_options.map(
                                                                (staff) => (
                                                                    <SelectItem
                                                                        key={
                                                                            staff.id
                                                                        }
                                                                        value={String(
                                                                            staff.id,
                                                                        )}
                                                                    >
                                                                        {
                                                                            staff.name
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        message={
                                                            errors.assigned_to_id
                                                        }
                                                    />
                                                </div>
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={
                                                        processing ||
                                                        assigneeId === ''
                                                    }
                                                >
                                                    {processing
                                                        ? 'Saving...'
                                                        : 'Update assignment'}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                ) : null
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
