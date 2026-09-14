'use client';

import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { Badge } from '@/components/ui/badge';
import { EMPTY_CELL } from '@/lib/empty-cell';
import { UserRowActions } from '@/pages/admin/users/user-row-actions';
import type {
    AdminDepartmentOption,
    AdminRoleOption,
    AdminUserRow,
} from '@/types/admin-user';
import type { ColumnDef } from '@tanstack/react-table';

export type AdminUserTableContext = {
    departments: AdminDepartmentOption[];
    roleOptions: AdminRoleOption[];
    currentUserId: number;
};

export function createAdminUserColumns({
    departments,
    roleOptions,
    currentUserId,
}: AdminUserTableContext): ColumnDef<AdminUserRow>[] {
    const roleLabels = Object.fromEntries(
        roleOptions.map((role) => [role.value, role.label]),
    );

    return [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'email',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Email" />
            ),
        },
        {
            accessorKey: 'department',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Department" />
            ),
            cell: ({ row }) => row.original.department?.name ?? EMPTY_CELL,
        },
        {
            id: 'roles',
            enableSorting: false,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Roles" />
            ),
            cell: ({ row }) => {
                if (row.original.roles.length === 0) {
                    return EMPTY_CELL;
                }

                return (
                    <div className="flex flex-wrap gap-1">
                        {row.original.roles.map((role) => (
                            <Badge key={role} variant="secondary">
                                {roleLabels[role] ?? role}
                            </Badge>
                        ))}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            meta: { cellClassName: 'text-right' },
            cell: ({ row }) => (
                <UserRowActions
                    user={row.original}
                    departments={departments}
                    roleOptions={roleOptions}
                    currentUserId={currentUserId}
                />
            ),
        },
    ];
}
