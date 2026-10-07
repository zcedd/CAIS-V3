import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import { Card, CardContent } from '@/components/ui/card';
import { createAdminWorkflowColumns } from '@/pages/admin/workflows/workflow-columns';
import { AdminWorkflowToolbar } from '@/pages/admin/workflows/workflow-toolbar';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as adminWorkflowsIndex } from '@/routes/admin/workflows';
import type { BreadcrumbItem } from '@/types';
import type { AdminDepartmentOption } from '@/types/admin-user';
import type {
    AdminWorkflowAbilities,
    AdminWorkflowRow,
    AdminWorkflowStatusOption,
    AdminWorkflowTableFilters,
} from '@/types/admin-workflow';
import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const WORKFLOWS_TABLE_PARTIAL_PROPS = ['workflows'] as const;
const WORKFLOWS_TABLE_SKELETON_COLUMNS = 6;

type PaginatedWorkflows = ServerPaginationMeta & {
    data: AdminWorkflowRow[];
};

function buildWorkflowsQuery(
    state: {
        search: string;
        department_id: number | null;
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

    if (next.department_id !== null) {
        query.department_id = next.department_id;
    }

    if (next.status.length > 0) {
        query.status = next.status;
    }

    return query;
}

function isWorkflowsPartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        WORKFLOWS_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof WORKFLOWS_TABLE_PARTIAL_PROPS)[number],
        ),
    );
}

export default function AdminWorkflowsIndex({
    workflows,
    departments,
    status_options,
    search,
    department_id,
    status,
    sort,
    direction,
    can,
}: {
    workflows: PaginatedWorkflows;
    departments: AdminDepartmentOption[];
    status_options: AdminWorkflowStatusOption[];
    search: string;
    department_id: number | null;
    status: string[];
    sort: string;
    direction: 'asc' | 'desc';
    can: AdminWorkflowAbilities;
}) {
    const [tableState, setTableState] = useState({
        sort,
        direction,
        per_page: workflows.per_page,
        search,
        department_id,
        status,
    });
    const [isTableReloading, setIsTableReloading] = useState(false);
    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (isWorkflowsPartialVisit(event.detail.visit.only)) {
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
                    title: 'Administration',
                    href: adminUsersIndex.url(),
                },
                {
                    title: 'Workflows',
                    href: adminWorkflowsIndex.url(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, []);

    const tableFilters: AdminWorkflowTableFilters = {
        search: tableState.search,
        department_id: tableState.department_id,
        status: tableState.status,
    };

    const visitTable = useCallback(
        (
            overrides: Partial<
                AdminWorkflowTableFilters & {
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
                adminWorkflowsIndex.url({
                    query: buildWorkflowsQuery(next, overrides),
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: [...WORKFLOWS_TABLE_PARTIAL_PROPS],
                },
            );
        },
        [],
    );

    const workflowColumns = useMemo(
        () =>
            createAdminWorkflowColumns({
                canDelete: can.delete,
            }),
        [can.delete],
    );

    return (
        <>
            <Head title="Workflows" />

            <Card>
                <CardContent>
                    <DataTable
                        columns={workflowColumns}
                        data={workflows.data}
                        emptyMessage="No workflows match your filters."
                        manualPagination
                        manualSorting
                        manualFiltering
                        serverPagination={workflows}
                        serverSorting={{
                            sort: tableState.sort,
                            direction: tableState.direction,
                        }}
                        partialReloadOnly={[...WORKFLOWS_TABLE_PARTIAL_PROPS]}
                        isLoading={isTableReloading}
                        loadingFallback={
                            <DataTableSkeleton
                                columnCount={WORKFLOWS_TABLE_SKELETON_COLUMNS}
                                rowCount={tableState.per_page}
                            />
                        }
                        onServerSortingChange={(columnId, nextDirection) => {
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
                            <AdminWorkflowToolbar
                                table={table}
                                columnVisibility={columnVisibility}
                                filters={tableFilters}
                                departments={departments}
                                statusOptions={status_options}
                                canCreate={can.create}
                                onFiltersChange={visitTable}
                                onWorkflowCreated={() => visitTable({ page: 1 })}
                            />
                        )}
                    />
                </CardContent>
            </Card>
        </>
    );
}
