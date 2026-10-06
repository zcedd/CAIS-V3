'use client';

import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { Badge } from '@/components/ui/badge';
import { EMPTY_CELL } from '@/lib/empty-cell';
import { WorkflowRowActions } from '@/pages/admin/workflows/workflow-row-actions';
import { show as adminWorkflowsShow } from '@/routes/admin/workflows';
import type { AdminWorkflowRow } from '@/types/admin-workflow';
import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';

export type AdminWorkflowTableContext = {
    canDelete: boolean;
};

export function createAdminWorkflowColumns({
    canDelete,
}: AdminWorkflowTableContext): ColumnDef<AdminWorkflowRow>[] {
    return [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href={adminWorkflowsShow.url(row.original.id)}
                        className="font-medium hover:underline"
                    >
                        {row.original.name}
                    </Link>
                    {row.original.is_default ? (
                        <Badge variant="outline">Default</Badge>
                    ) : null}
                </div>
            ),
        },
        {
            accessorKey: 'code',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Code" />
            ),
            cell: ({ row }) => row.original.code ?? EMPTY_CELL,
        },
        {
            accessorKey: 'version',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Version" />
            ),
            cell: ({ row }) => `v${row.original.version}`,
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <Badge variant="secondary">{row.original.status_label}</Badge>
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
            id: 'actions',
            meta: { cellClassName: 'text-right' },
            cell: ({ row }) => (
                <WorkflowRowActions
                    workflow={row.original}
                    canDelete={canDelete}
                />
            ),
        },
    ];
}
