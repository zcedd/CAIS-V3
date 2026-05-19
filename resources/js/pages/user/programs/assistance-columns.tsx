'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown } from 'lucide-react';

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

function sortHeader(label: string) {
    return ({
        column,
    }: {
        column: {
            toggleSorting: (desc: boolean) => void;
            getIsSorted: () => false | 'asc' | 'desc';
        };
    }) => (
        <Button
            variant="ghost"
            className="-ml-4 h-8 px-2 lg:px-3"
            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
            {label}
            <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
    );
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'Denied':
            return 'destructive';
        case 'Delivered':
            return 'secondary';
        case 'Verified':
            return 'default';
        case 'Pending':
            return 'outline';
        default:
            return 'secondary';
    }
}

export const userProgramAssistanceColumns: ColumnDef<UserProgramAssistanceRow>[] =
    [
        {
            accessorKey: 'id',
            header: sortHeader('ID'),
            cell: ({ row }) => (
                <span className="text-muted-foreground tabular-nums">
                    {row.getValue('id')}
                </span>
            ),
        },
        {
            accessorKey: 'cais_number',
            header: sortHeader('CAIS Number'),
            cell: ({ row }) => (
                <span className="max-w-[min(20rem,40vw)] truncate font-medium">
                    {row.getValue('cais_number')}
                </span>
            ),
        },
        {
            accessorKey: 'items',
            header: 'Items requested',
            cell: ({ row }) => {
                const items = row.getValue(
                    'items',
                ) as UserProgramAssistanceItem[];

                if (items.length === 0) {
                    return (
                        <span className="text-muted-foreground">—</span>
                    );
                }

                return (
                    <ul className="max-w-[min(24rem,50vw)] list-none space-y-1 text-sm">
                        {items.map((item, index) => {
                            const amount = formatItemAmount(item);

                            return (
                            <li key={`${item.name}-${index}`}>
                                <span className="font-medium">{item.name}</span>
                                {amount ? (
                                    <span className="tabular-nums text-muted-foreground">
                                        {' '}
                                        {amount}
                                    </span>
                                ) : null}
                                {item.specification?.trim() ? (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        ({item.specification})
                                    </span>
                                ) : null}
                            </li>
                            );
                        })}
                    </ul>
                );
            },
        },
        {
            accessorKey: 'mode_of_request',
            header: sortHeader('Mode of request'),
        },
        {
            accessorKey: 'status',
            header: sortHeader('Status'),
            cell: ({ row }) => {
                const status = row.getValue('status') as string;

                return (
                    <Badge
                        variant={statusVariant(status)}
                        className="font-normal"
                    >
                        {status}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'date_requested',
            header: sortHeader('Requested'),
            cell: ({ row }) => {
                const v = row.getValue('date_requested') as string | null;

                return <span className="tabular-nums">{v ?? '—'}</span>;
            },
        },
        {
            accessorKey: 'date_verified',
            header: sortHeader('Verified'),
            cell: ({ row }) => {
                const v = row.getValue('date_verified') as string | null;

                return <span className="tabular-nums">{v ?? '—'}</span>;
            },
        },
        {
            accessorKey: 'date_delivered',
            header: sortHeader('Delivered'),
            cell: ({ row }) => {
                const v = row.getValue('date_delivered') as string | null;

                return <span className="tabular-nums">{v ?? '—'}</span>;
            },
        },
        {
            accessorKey: 'date_denied',
            header: sortHeader('Denied'),
            cell: ({ row }) => {
                const v = row.getValue('date_denied') as string | null;

                return <span className="tabular-nums">{v ?? '—'}</span>;
            },
        },
        {
            accessorKey: 'remark',
            header: 'Remark',
            cell: ({ row }) => {
                const v = row.getValue('remark') as string | null;

                return (
                    <span className="max-w-[min(24rem,50vw)] whitespace-normal text-muted-foreground">
                        {v?.trim() ? v : '—'}
                    </span>
                );
            },
        },
    ];
