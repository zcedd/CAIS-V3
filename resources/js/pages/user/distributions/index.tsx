import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardCheck,
    Layers,
    Play,
    UserRound,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Can } from '@/components/auth/can';
import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/user/stat-card';
import { Permission } from '@/lib/permissions';
import { createDistributionColumns } from '@/pages/user/distributions/distribution-columns';
import type { DistributionBatchRow } from '@/pages/user/distributions/distribution-columns';
import { DistributionDataTableToolbar } from '@/pages/user/distributions/distribution-toolbar';
import type {
    DistributionOption,
    DistributionProgramOption,
    DistributionTableFilters,
} from '@/pages/user/distributions/distribution-toolbar';
import {
    calendar,
    create,
    index as distributionsIndex,
} from '@/routes/user/distributions';
import type { BreadcrumbItem } from '@/types';

const DISTRIBUTIONS_TABLE_PARTIAL_PROPS = ['batches', 'filters'] as const;
const DISTRIBUTIONS_TABLE_SKELETON_COLUMNS = 7;
const DEFAULT_PER_PAGE = 15;

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type PaginatedBatches = ServerPaginationMeta & {
    data: DistributionBatchRow[];
};

type DistributionSummary = {
    total: number;
    individual: number;
    batch: number;
    scheduled: number;
    pending_approval: number;
    in_progress: number;
    completed: number;
    cancelled: number;
    beneficiaries_scheduled: number;
    beneficiaries_released: number;
};

type DistributionFilters = {
    search?: string | null;
    program_id?: string | number | null;
    status?: string | string[] | null;
    distribution_type?: string | string[] | null;
    venue?: string | null;
    beneficiary?: string | null;
    sort?: string | null;
    direction?: string | null;
};

type DistributionListFilters = DistributionTableFilters & {
    page: number;
    per_page: number;
};

function asList(value: string | string[] | null | undefined): string[] {
    if (Array.isArray(value)) {
        return value.filter((item) => item !== '');
    }

    if (typeof value === 'string' && value !== '') {
        return [value];
    }

    return [];
}

function buildDistributionsQuery(
    filters: DistributionListFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};
    const search = filters.search.trim();
    const venue = filters.venue.trim();
    const beneficiary = filters.beneficiary.trim();

    if (search !== '') {
        query.search = search;
    }

    if (venue !== '') {
        query.venue = venue;
    }

    if (beneficiary !== '') {
        query.beneficiary = beneficiary;
    }

    if (filters.program_id !== '') {
        query.program_id = filters.program_id;
    }

    if (filters.status.length > 0) {
        query.status = filters.status;
    }

    if (filters.distribution_type.length > 0) {
        query.distribution_type = filters.distribution_type;
    }

    if (filters.sort !== 'scheduled_at') {
        query.sort = filters.sort;
    }

    if (filters.direction !== 'desc') {
        query.direction = filters.direction;
    }

    if (filters.page > 1) {
        query.page = String(filters.page);
    }

    if (filters.per_page !== DEFAULT_PER_PAGE) {
        query.per_page = String(filters.per_page);
    }

    return query;
}

function isDistributionsPartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        DISTRIBUTIONS_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof DISTRIBUTIONS_TABLE_PARTIAL_PROPS)[number],
        ),
    );
}

const summaryCards: {
    key: keyof DistributionSummary;
    label: string;
    icon: LucideIcon;
}[] = [
    { key: 'total', label: 'Activities', icon: Layers },
    { key: 'pending_approval', label: 'Pending approval', icon: ClipboardCheck },
    { key: 'in_progress', label: 'In progress', icon: Play },
    { key: 'beneficiaries_released', label: 'Beneficiaries released', icon: Users },
];

function summaryDescription(
    key: keyof DistributionSummary,
    summary: DistributionSummary,
): string {
    if (key === 'total') {
        return `${summary.individual.toLocaleString()} individual · ${summary.batch.toLocaleString()} batch`;
    }

    if (key === 'pending_approval') {
        return `${summary.scheduled.toLocaleString()} scheduled`;
    }

    if (key === 'in_progress') {
        return `${summary.completed.toLocaleString()} completed · ${summary.cancelled.toLocaleString()} cancelled`;
    }

    return `${summary.beneficiaries_scheduled.toLocaleString()} scheduled`;
}

export default function DistributionsIndex({
    department,
    batches,
    summary,
    filters,
    statuses,
    distribution_types,
    programs,
}: {
    department: DepartmentSummary;
    batches: PaginatedBatches;
    summary: DistributionSummary;
    filters: DistributionFilters;
    statuses: DistributionOption[];
    distribution_types: DistributionOption[];
    programs: DistributionProgramOption[];
}) {
    const incomingTableState = {
        search: filters.search ?? '',
        program_id:
            filters.program_id == null || filters.program_id === ''
                ? ''
                : String(filters.program_id),
        status: asList(filters.status),
        distribution_type: asList(filters.distribution_type),
        venue: filters.venue ?? '',
        beneficiary: filters.beneficiary ?? '',
        sort: filters.sort || 'scheduled_at',
        direction: (filters.direction === 'asc' ? 'asc' : 'desc') as
            | 'asc'
            | 'desc',
        per_page: batches.per_page,
    };
    const incomingSnapshot = JSON.stringify(incomingTableState);
    const [tableState, setTableState] = useState(incomingTableState);
    const [syncedSnapshot, setSyncedSnapshot] = useState(incomingSnapshot);

    if (incomingSnapshot !== syncedSnapshot) {
        setSyncedSnapshot(incomingSnapshot);
        setTableState(incomingTableState);
    }

    const [isTableReloading, setIsTableReloading] = useState(false);
    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Distributions',
                    href: distributionsIndex.url(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (isDistributionsPartialVisit(event.detail.visit.only)) {
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
        (
            overrides: Partial<
                DistributionTableFilters & {
                    per_page: number;
                    page: number;
                }
            > = {},
        ) => {
            const next: DistributionListFilters = {
                search: overrides.search ?? tableStateRef.current.search,
                program_id:
                    overrides.program_id ?? tableStateRef.current.program_id,
                status: overrides.status ?? tableStateRef.current.status,
                distribution_type:
                    overrides.distribution_type ??
                    tableStateRef.current.distribution_type,
                venue: overrides.venue ?? tableStateRef.current.venue,
                beneficiary:
                    overrides.beneficiary ?? tableStateRef.current.beneficiary,
                sort: overrides.sort ?? tableStateRef.current.sort,
                direction:
                    overrides.direction ?? tableStateRef.current.direction,
                page: overrides.page ?? 1,
                per_page:
                    overrides.per_page ?? tableStateRef.current.per_page,
            };

            setTableState({
                search: next.search,
                program_id: next.program_id,
                status: next.status,
                distribution_type: next.distribution_type,
                venue: next.venue,
                beneficiary: next.beneficiary,
                sort: next.sort,
                direction: next.direction,
                per_page: next.per_page,
            });

            router.cancelAll();
            router.get(
                distributionsIndex.url(department.slug, {
                    query: buildDistributionsQuery(next),
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: [...DISTRIBUTIONS_TABLE_PARTIAL_PROPS],
                },
            );
        },
        [department.slug],
    );

    const tableFilters: DistributionTableFilters = {
        search: tableState.search,
        program_id: tableState.program_id,
        status: tableState.status,
        distribution_type: tableState.distribution_type,
        venue: tableState.venue,
        beneficiary: tableState.beneficiary,
        sort: tableState.sort,
        direction: tableState.direction,
    };

    const columns = useMemo(
        () => createDistributionColumns(department.slug),
        [department.slug],
    );

    return (
        <>
            <Head title="Distributions" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Distributions
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Individual releases go out directly. Batch
                            distributions wait for executive approval.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            asChild
                            data-tour="distributions-calendar"
                        >
                            <Link href={calendar.url(department.slug)}>
                                <CalendarDays />
                                Calendar
                            </Link>
                        </Button>
                        <Can permission={Permission.DistributionCreate}>
                            <Button
                                variant="outline"
                                asChild
                                data-tour="distributions-create-individual"
                            >
                                <Link
                                    href={create.url(department.slug, {
                                        query: { mode: 'individual' },
                                    })}
                                >
                                    <UserRound />
                                    Individual distribution
                                </Link>
                            </Button>
                            <Button asChild data-tour="distributions-create">
                                <Link
                                    href={create.url(department.slug, {
                                        query: { mode: 'batch' },
                                    })}
                                >
                                    <Layers />
                                    Batch distribution
                                </Link>
                            </Button>
                        </Can>
                    </div>
                </div>

                <div
                    className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                    data-tour="distributions-stats"
                >
                    {summaryCards.map((card) => (
                        <StatCard
                            key={card.key}
                            label={card.label}
                            value={summary[card.key].toLocaleString()}
                            description={summaryDescription(card.key, summary)}
                            icon={card.icon}
                        />
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Activities</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div data-tour="distributions-table">
                            <DataTable
                                columns={columns}
                                data={batches.data}
                                emptyMessage="No distributions match these filters."
                                manualPagination
                                manualSorting
                                manualFiltering
                                serverPagination={batches}
                                serverSorting={{
                                    sort: tableState.sort,
                                    direction: tableState.direction,
                                }}
                                partialReloadOnly={[
                                    ...DISTRIBUTIONS_TABLE_PARTIAL_PROPS,
                                ]}
                                isLoading={isTableReloading}
                                loadingFallback={
                                    <DataTableSkeleton
                                        columnCount={
                                            DISTRIBUTIONS_TABLE_SKELETON_COLUMNS
                                        }
                                        rowCount={tableState.per_page}
                                    />
                                }
                                onServerSortingChange={(
                                    columnId,
                                    nextDirection,
                                ) => {
                                    visitTable({
                                        sort: columnId,
                                        direction: nextDirection,
                                        page: 1,
                                    });
                                }}
                                onPerPageChange={(nextPerPage) => {
                                    visitTable({
                                        per_page: nextPerPage,
                                        page: 1,
                                    });
                                }}
                                onPageChange={(page) => {
                                    visitTable({ page });
                                }}
                                toolbar={(table, columnVisibility) => (
                                    <DistributionDataTableToolbar
                                        table={table}
                                        columnVisibility={columnVisibility}
                                        filters={tableFilters}
                                        statuses={statuses}
                                        distributionTypes={distribution_types}
                                        programs={programs}
                                        onFiltersChange={visitTable}
                                    />
                                )}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
