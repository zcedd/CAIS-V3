import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import { Card, CardContent } from '@/components/ui/card';
import { createAdminUserColumns } from '@/pages/admin/users/user-columns';
import { AdminUserToolbar } from '@/pages/admin/users/user-toolbar';
import { index as adminUsersIndex } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';
import type {
    AdminDepartmentOption,
    AdminRoleOption,
    AdminUserRow,
    AdminUserTableFilters,
} from '@/types/admin-user';
import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const USERS_TABLE_PARTIAL_PROPS = ['users'] as const;
const USERS_TABLE_SKELETON_COLUMNS = 5;

type PaginatedUsers = ServerPaginationMeta & {
    data: AdminUserRow[];
};

function buildUsersQuery(
    state: {
        search: string;
        department_id: number | null;
        role: string[];
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

    if (next.role.length > 0) {
        query.role = next.role;
    }

    return query;
}

function isUsersPartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        USERS_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof USERS_TABLE_PARTIAL_PROPS)[number],
        ),
    );
}

export default function AdminUsersIndex({
    users,
    departments,
    role_options,
    search,
    department_id,
    role,
    sort,
    direction,
}: {
    users: PaginatedUsers;
    departments: AdminDepartmentOption[];
    role_options: AdminRoleOption[];
    search: string;
    department_id: number | null;
    role: string[];
    sort: string;
    direction: 'asc' | 'desc';
}) {
    const { auth } = usePage().props;
    const currentUserId = auth.user.id;
    const [tableState, setTableState] = useState({
        sort,
        direction,
        per_page: users.per_page,
        search,
        department_id,
        role,
    });
    const [isTableReloading, setIsTableReloading] = useState(false);
    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (isUsersPartialVisit(event.detail.visit.only)) {
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
                    title: 'Users',
                    href: adminUsersIndex.url(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, []);

    const tableFilters: AdminUserTableFilters = {
        search: tableState.search,
        department_id: tableState.department_id,
        role: tableState.role,
    };

    const visitTable = useCallback(
        (
            overrides: Partial<
                AdminUserTableFilters & {
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
                adminUsersIndex.url({
                    query: buildUsersQuery(next, overrides),
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: [...USERS_TABLE_PARTIAL_PROPS],
                },
            );
        },
        [],
    );

    const userColumns = useMemo(
        () =>
            createAdminUserColumns({
                departments,
                roleOptions: role_options,
                currentUserId,
            }),
        [departments, role_options, currentUserId],
    );

    return (
        <>
            <Head title="Users" />

            <Card>
                <CardContent>
                    <DataTable
                        columns={userColumns}
                        data={users.data}
                        emptyMessage="No users match your filters."
                        manualPagination
                        manualSorting
                        manualFiltering
                        serverPagination={users}
                        serverSorting={{
                            sort: tableState.sort,
                            direction: tableState.direction,
                        }}
                        partialReloadOnly={[...USERS_TABLE_PARTIAL_PROPS]}
                        isLoading={isTableReloading}
                        loadingFallback={
                            <DataTableSkeleton
                                columnCount={USERS_TABLE_SKELETON_COLUMNS}
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
                            <AdminUserToolbar
                                table={table}
                                columnVisibility={columnVisibility}
                                filters={tableFilters}
                                departments={departments}
                                roleOptions={role_options}
                                onFiltersChange={visitTable}
                                onUserCreated={() => visitTable({ page: 1 })}
                            />
                        )}
                    />
                </CardContent>
            </Card>
        </>
    );
}
