'use client';

import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { assistanceStatuses } from '@/pages/user/programs/assistance-data';
import { AssistanceDataTableRowActions } from '@/pages/user/programs/assistance-row-actions';
import { ColumnDef } from '@tanstack/react-table';

export type UserProgramAssistanceItem = {
    name: string;
    quantity: number | null;
    unit: string | null;
    specification: string | null;
};

function formatItemAmount(item: UserProgramAssistanceItem): string | null {
    if (item.quantity !== null && item.unit) {
        return `× ${item.quantity} ${item.unit}`;
    }

    if (item.quantity !== null) {
        return `× ${item.quantity}`;
    }

    if (item.unit) {
        return item.unit;
    }

    return null;
}

export type UserProgramAssistanceRow = {
    id: number;
    cais_number: string;
    items: UserProgramAssistanceItem[];
    mode_of_request: string;
    date_requested: string | null;
    date_verified: string | null;
    date_delivered: string | null;
    date_denied: string | null;
    status: string;
    remark: string | null;
};

function itemsSummary(items: UserProgramAssistanceItem[]): string {
    if (items.length === 0) {
        return 'No items listed';
    }

    const first = items[0];
    const amount = formatItemAmount(first);
    let summary = first.name;

    if (amount) {
        summary += ` ${amount}`;
    }

    if (items.length > 1) {
        summary += ` (+${items.length - 1} more)`;
    }

    return summary;
}

export const userProgramAssistanceColumns: ColumnDef<UserProgramAssistanceRow>[] =
    [
        {
            id: 'select',
            header: ({ table }) => (
                <Checkbox
                    checked={
                        table.getIsAllPageRowsSelected() ||
                        (table.getIsSomePageRowsSelected() && 'indeterminate')
                    }
                    onCheckedChange={(value) =>
                        table.toggleAllPageRowsSelected(!!value)
                    }
                    aria-label="Select all"
                    className="translate-y-[2px]"
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    aria-label="Select row"
                    className="translate-y-[2px]"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: 'cais_number',
            meta: { title: 'CAIS Number' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="CAIS Number" />
            ),
            cell: ({ row }) => (
                <span className="w-[120px] font-mono text-xs font-medium">
                    {row.getValue('cais_number')}
                </span>
            ),
        },
        {
            id: 'items',
            accessorFn: (row) => itemsSummary(row.items),
            enableSorting: false,
            meta: {
                title: 'Items requested',
                cellClassName: 'whitespace-normal',
            },
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Items requested"
                />
            ),
            cell: ({ row }) => {
                const items = row.original.items;

                if (items.length === 0) {
                    return <span className="text-muted-foreground">—</span>;
                }

                const first = items[0];
                const amount = formatItemAmount(first);

                return (
                    <div className="flex max-w-[min(28rem,50vw)] flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="font-normal">
                                {row.original.mode_of_request}
                            </Badge>
                            {items.length > 1 ? (
                                <Badge
                                    variant="secondary"
                                    className="font-normal"
                                >
                                    +{items.length - 1} item
                                    {items.length - 1 === 1 ? '' : 's'}
                                </Badge>
                            ) : null}
                        </div>
                        <span className="font-medium">{first.name}</span>
                        {amount ? (
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {amount}
                                {first.specification?.trim()
                                    ? ` · ${first.specification}`
                                    : ''}
                            </span>
                        ) : first.specification?.trim() ? (
                            <span className="text-sm text-muted-foreground">
                                {first.specification}
                            </span>
                        ) : null}
                    </div>
                );
            },
        },
        {
            accessorKey: 'status',
            meta: { title: 'Status' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => {
                const status = row.getValue('status') as string;
                const option = assistanceStatuses.find(
                    (entry) => entry.value === status,
                );
                const Icon = option?.icon;

                return (
                    <div className="flex w-[120px] items-center gap-2">
                        {Icon ? (
                            <Icon className="h-4 w-4 text-muted-foreground" />
                        ) : null}
                        <span>{option?.label ?? status}</span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'mode_of_request',
            meta: { title: 'Mode of request' },
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Mode of request"
                />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.getValue('mode_of_request')}
                </span>
            ),
        },
        {
            accessorKey: 'date_requested',
            meta: { title: 'Requested' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Requested" />
            ),
            cell: ({ row }) => {
                const value = row.getValue('date_requested') as string | null;

                return (
                    <span className="text-muted-foreground tabular-nums">
                        {value ?? '—'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'date_verified',
            meta: { title: 'Verified' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Verified" />
            ),
            cell: ({ row }) => {
                const value = row.getValue('date_verified') as string | null;

                return (
                    <span className="text-muted-foreground tabular-nums">
                        {value ?? '—'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'date_delivered',
            meta: { title: 'Delivered' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Delivered" />
            ),
            cell: ({ row }) => {
                const value = row.getValue('date_delivered') as string | null;

                return (
                    <span className="text-muted-foreground tabular-nums">
                        {value ?? '—'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'date_denied',
            meta: { title: 'Denied' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Denied" />
            ),
            cell: ({ row }) => {
                const value = row.getValue('date_denied') as string | null;

                return (
                    <span className="text-muted-foreground tabular-nums">
                        {value ?? '—'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'remark',
            meta: { title: 'Remark', cellClassName: 'whitespace-normal' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Remark" />
            ),
            cell: ({ row }) => {
                const value = row.getValue('remark') as string | null;

                return (
                    <span className="max-w-[min(24rem,50vw)] whitespace-normal text-muted-foreground">
                        {value?.trim() ? value : '—'}
                    </span>
                );
            },
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => <AssistanceDataTableRowActions row={row} />,
        },
    ];

export const userProgramAssistanceInitialColumnVisibility = {
    mode_of_request: true,
    date_verified: true,
    date_delivered: true,
    date_denied: true,
    remark: true,
};
