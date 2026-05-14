'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown } from 'lucide-react';

export type UserProjectAssistanceRow = {
    id: number;
    party: string;
    party_type: string;
    mode_of_request: string;
    date_requested: string | null;
    date_verified: string | null;
    date_delivered: string | null;
    date_denied: string | null;
    status: string;
    remark: string | null;
};

function sortHeader(label: string) {
    return ({ column }: { column: { toggleSorting: (desc: boolean) => void; getIsSorted: () => false | 'asc' | 'desc' } }) => (
        <Button variant="ghost" className="-ml-4 h-8 px-2 lg:px-3" onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}>
            {label}
            <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
    );
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
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

export const userProjectAssistanceColumns: ColumnDef<UserProjectAssistanceRow>[] = [
    {
        accessorKey: 'id',
        header: sortHeader('ID'),
        cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{row.getValue('id')}</span>,
    },
    {
        accessorKey: 'party',
        header: sortHeader('Party'),
        cell: ({ row }) => <span className="max-w-[min(20rem,40vw)] truncate font-medium">{row.getValue('party')}</span>,
    },
    {
        accessorKey: 'party_type',
        header: sortHeader('Type'),
    },
    {
        accessorKey: 'mode_of_request',
        header: sortHeader('Mode of request'),
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
        accessorKey: 'status',
        header: sortHeader('Status'),
        cell: ({ row }) => {
            const status = row.getValue('status') as string;

            return (
                <Badge variant={statusVariant(status)} className="font-normal">
                    {status}
                </Badge>
            );
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

            return <span className="max-w-[min(24rem,50vw)] whitespace-normal text-muted-foreground">{v?.trim() ? v : '—'}</span>;
        },
    },
];
