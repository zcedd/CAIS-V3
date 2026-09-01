import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { createFundColumns } from '@/pages/user/funds/fund-columns';
import {
    FundDataTableToolbar,
    type FundTableFilters,
} from '@/pages/user/funds/fund-toolbar';
import { index as departmentFundsIndex } from '@/routes/user/funds';
import type { BreadcrumbItem } from '@/types';
import type { FundRow } from '@/types/fund';
import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const FUNDS_TABLE_PARTIAL_PROPS = ['funds'] as const;
const FUNDS_TABLE_SKELETON_COLUMNS = 5;

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type PaginatedFunds = ServerPaginationMeta & {
    data: FundRow[];
};

function buildFundsQuery(
    state: {
        search: string;
        status: string[];
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
        page?: number;
    },
    overrides: Partial<typeof state> = {},
): Record<string, string | number | string[]> {
    const next = { ...state, ...overrides };
    const query: Record<string, string | number | string[]> = {
        sort: next.sort,
        direction: next.direction,
        per_page: next.per_page,
    };

    if (next.page !== undefined) {
        query.page = next.page;
    }

    const search = next.search.trim();
    if (search !== '') {
        query.search = search;
    }

    if (next.status.length > 0) {
        query.status = next.status;
    }

    return query;
}

function isFundsPartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        FUNDS_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof FUNDS_TABLE_PARTIAL_PROPS)[number],
        ),
    );
}

export default function UserFundsIndex({
    funds,
    department,
    search,
    status,
    sort,
    direction,
}: {
    funds: PaginatedFunds;
    department: DepartmentSummary;
    search: string;
    status: string[];
    sort: string;
    direction: 'asc' | 'desc';
}) {
    const [tableState, setTableState] = useState({
        sort,
        direction,
        per_page: funds.per_page,
        search,
        status,
    });
    const [isTableReloading, setIsTableReloading] = useState(false);
    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (isFundsPartialVisit(event.detail.visit.only)) {
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

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Funds',
                    href: departmentFundsIndex.url(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    const tableFilters: FundTableFilters = {
        search: tableState.search,
        status: tableState.status,
    };

    const visitTable = useCallback(
        (
            overrides: Partial<
                FundTableFilters & {
                    sort: string;
                    direction: 'asc' | 'desc';
                    per_page: number;
                    page: number;
                }
            > = {},
        ) => {
            const next = { ...tableStateRef.current, ...overrides };
            setTableState(next);
            router.cancelAll();
            router.get(
                departmentFundsIndex.url(
                    { department: department.slug },
                    {
                        query: buildFundsQuery(next, overrides),
                    },
                ),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: [...FUNDS_TABLE_PARTIAL_PROPS],
                },
            );
        },
        [department.slug],
    );

    const fundColumns = useMemo(
        () =>
            createFundColumns({
                departmentSlug: department.slug,
            }),
        [department.slug],
    );

    return (
        <>
            <Head title="Department funds" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Funds
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage funds assigned to your department.
                    </p>
                </div>

                <Card data-tour="funds-table">
                    <div data-tour="funds-table">
                        <CardContent>
                            <DataTable
                                columns={fundColumns}
                                data={funds.data}
                                emptyMessage="No funds match your filters."
                                manualPagination
                                manualSorting
                                manualFiltering
                                serverPagination={funds}
                                serverSorting={{
                                    sort: tableState.sort,
                                    direction: tableState.direction,
                                }}
                                partialReloadOnly={[
                                    ...FUNDS_TABLE_PARTIAL_PROPS,
                                ]}
                                isLoading={isTableReloading}
                                loadingFallback={
                                    <DataTableSkeleton
                                        columnCount={
                                            FUNDS_TABLE_SKELETON_COLUMNS
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
                                toolbar={(table, columnVisibility) => (
                                    <FundDataTableToolbar
                                        table={table}
                                        columnVisibility={columnVisibility}
                                        filters={tableFilters}
                                        departmentSlug={department.slug}
                                        departmentName={department.name}
                                        onFiltersChange={visitTable}
                                        onFundCreated={() =>
                                            visitTable({ page: 1 })
                                        }
                                    />
                                )}
                            />
                        </CardContent>
                    </div>
                </Card>
            </div>
        </>
    );
}
