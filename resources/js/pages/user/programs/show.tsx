import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    createUserProgramAssistanceColumns,
    userProgramAssistanceInitialColumnVisibility,
    type UserProgramAssistanceRow,
} from '@/pages/user/programs/assistance-columns';
import {
    AssistanceDataTableToolbar,
    type AssistanceTableFilters,
    type ModeFilterOption,
    type StatusFilterOption,
} from '@/pages/user/programs/assistance-toolbar';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';

import {
    Head,
    Link,
    router,
    setLayoutProps,
    WhenVisible,
} from '@inertiajs/react';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const ASSISTANCE_TABLE_PARTIAL_PROPS = ['assistances'] as const;

const ASSISTANCE_TABLE_SKELETON_COLUMNS = 14;

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProgramDetail = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    is_closed: boolean | null;
    is_organization: boolean | null;
    department_id: number;
    department?: DepartmentSummary | null;
};

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

function ProgramAssistanceTable({
    assistances,
    assistanceColumns,
    tableFilters,
    tableState,
    statusOptions,
    modeOptions,
    isLoading,
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
    department,
    assistances,
    sort,
    direction,
    per_page,
    search,
    status,
    mode,
    mode_options,
    status_options,
}: {
    program: ProgramDetail;
    department: DepartmentSummary | null;
    assistances?: PaginatedAssistances;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    search: string;
    status: string[];
    mode: string[];
    mode_options: ModeFilterOption[];
    status_options: StatusFilterOption[];
}) {
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

    const assistanceColumns = useMemo(() => {
        if (!department?.slug) {
            return [];
        }

        return createUserProgramAssistanceColumns({
            departmentSlug: department.slug,
            programId: program.id,
        });
    }, [department?.slug, program.id]);

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
                    {department?.slug ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={departmentProgramsIndex.url(
                                    department.slug,
                                )}
                            >
                                Back to programs
                            </Link>
                        </Button>
                    ) : null}
                </div>
                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Overview</CardTitle>
                        <CardDescription>
                            {program.is_organization
                                ? 'Organization'
                                : 'Personal'}
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
                            {program.start_at ?? '—'}
                            {program.end_at ? ` – ${program.end_at}` : ''}
                        </p>
                        <p>
                            <span className="font-medium text-foreground">
                                Status:{' '}
                            </span>
                            {program.is_closed ? 'Closed' : 'Open'}
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
                            data="assistances"
                            fallback={() => tableSkeleton}
                        >
                            {assistances ? (
                                <ProgramAssistanceTable
                                    assistances={assistances}
                                    assistanceColumns={assistanceColumns}
                                    tableFilters={tableFilters}
                                    tableState={tableState}
                                    statusOptions={status_options}
                                    modeOptions={mode_options}
                                    isLoading={isTableReloading}
                                    onVisitTable={visitTable}
                                />
                            ) : (
                                tableSkeleton
                            )}
                        </WhenVisible>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
