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
import {
    createUserDepartmentItemColumns,
    type UserDepartmentItemRow,
} from '@/pages/user/items/item-columns';
import {
    ItemDataTableToolbar,
    type ItemTableFilters,
    type UnitMeasurementOption,
} from '@/pages/user/items/item-toolbar';
import { dashboard } from '@/routes';
import { index as departmentItemsIndex } from '@/routes/user/items';
import type { BreadcrumbItem } from '@/types';
import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const ITEMS_TABLE_PARTIAL_PROPS = ['items'] as const;
const ITEMS_TABLE_SKELETON_COLUMNS = 3;

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type PaginatedItems = ServerPaginationMeta & {
    data: UserDepartmentItemRow[];
};

function buildItemsQuery(
    state: {
        search: string;
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
        page?: number;
    },
    overrides: Partial<typeof state> = {},
): Record<string, string | number> {
    const next = { ...state, ...overrides };
    const query: Record<string, string | number> = {
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

    return query;
}

function isItemsPartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        ITEMS_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof ITEMS_TABLE_PARTIAL_PROPS)[number],
        ),
    );
}

export default function UserDepartmentItemsIndex({
    items,
    department,
    search,
    sort,
    direction,
    unit_measurements,
}: {
    items: PaginatedItems;
    department: DepartmentSummary;
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
    unit_measurements: UnitMeasurementOption[];
}) {
    const [tableState, setTableState] = useState({
        sort,
        direction,
        per_page: items.per_page,
        search,
    });
    const [isTableReloading, setIsTableReloading] = useState(false);
    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (isItemsPartialVisit(event.detail.visit.only)) {
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
                    title: 'Dashboard',
                    href: dashboard(),
                },
                {
                    title: 'Items',
                    href: departmentItemsIndex.url(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    const tableFilters: ItemTableFilters = {
        search: tableState.search,
    };

    const visitTable = useCallback(
        (
            overrides: Partial<
                ItemTableFilters & {
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
                departmentItemsIndex.url(
                    { department: department.slug },
                    {
                        query: buildItemsQuery(next, overrides),
                    },
                ),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: [...ITEMS_TABLE_PARTIAL_PROPS],
                },
            );
        },
        [department.slug],
    );

    const itemColumns = useMemo(
        () =>
            createUserDepartmentItemColumns({
                departmentSlug: department.slug,
                unitMeasurements: unit_measurements,
                onItemUpdated: () => visitTable({ page: 1 }),
            }),
        [department.slug, unit_measurements, visitTable],
    );

    return (
        <>
            <Head title="Items" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Items
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage assistance items for {department.name}.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Department items</CardTitle>
                        <CardDescription>
                            Create and maintain items available for programs
                            and assistances.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={itemColumns}
                            data={items.data}
                            emptyMessage="No items found for this department."
                            manualPagination
                            manualSorting
                            manualFiltering
                            serverPagination={items}
                            serverSorting={{
                                sort: tableState.sort,
                                direction: tableState.direction,
                            }}
                            partialReloadOnly={[...ITEMS_TABLE_PARTIAL_PROPS]}
                            isLoading={isTableReloading}
                            loadingFallback={
                                <DataTableSkeleton
                                    columnCount={ITEMS_TABLE_SKELETON_COLUMNS}
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
                                <ItemDataTableToolbar
                                    table={table}
                                    columnVisibility={columnVisibility}
                                    filters={tableFilters}
                                    departmentSlug={department.slug}
                                    unitMeasurements={unit_measurements}
                                    onFiltersChange={visitTable}
                                    onItemCreated={() =>
                                        visitTable({ page: 1 })
                                    }
                                />
                            )}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
