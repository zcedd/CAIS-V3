'use client';

import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { Badge } from '@/components/ui/badge';
import { formatPeso } from '@/lib/format-peso';
import { EMPTY_CELL } from '@/lib/empty-cell';
import { FundRowActions } from '@/pages/user/funds/fund-row-actions';
import type { FundRow } from '@/types/fund';
import { ColumnDef } from '@tanstack/react-table';

export type FundTableContext = {
    departmentSlug: string;
};

export function createFundColumns({
    departmentSlug,
}: FundTableContext): ColumnDef<FundRow>[] {
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
            accessorKey: 'amount',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Amount" />
            ),
            cell: ({ row }) => formatPeso(row.original.amount),
        },
        {
            accessorKey: 'year',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Year" />
            ),
            cell: ({ row }) => row.original.year ?? EMPTY_CELL,
        },
        {
            accessorKey: 'is_active',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <Badge
                    variant={
                        row.original.is_active ? 'default' : 'secondary'
                    }
                >
                    {row.original.is_active ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            meta: { cellClassName: 'text-right' },
            cell: ({ row }) => (
                <FundRowActions
                    fund={row.original}
                    departmentSlug={departmentSlug}
                />
            ),
        },
    ];
}
