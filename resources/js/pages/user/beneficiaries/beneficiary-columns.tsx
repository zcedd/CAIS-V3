'use client';

import { Badge } from '@/components/ui/badge';
import { show as beneficiaryShow } from '@/routes/user/beneficiaries';
import type { BeneficiaryListRow } from '@/types/beneficiary';
import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Building2, MapPin, Phone, UserRound } from 'lucide-react';

export type BeneficiaryTableContext = {
    departmentSlug: string;
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function typeBadge(type: BeneficiaryListRow['type']) {
    return type === 'organization' ? (
        <Badge variant="secondary">
            <Building2 aria-hidden />
            Organization
        </Badge>
    ) : (
        <Badge variant="outline">
            <UserRound aria-hidden />
            Individual
        </Badge>
    );
}

export function createBeneficiaryColumns({
    departmentSlug,
}: BeneficiaryTableContext): ColumnDef<BeneficiaryListRow>[] {
    return [
        {
            accessorKey: 'cais_number',
            meta: { title: 'CAIS Number' },
            header: 'CAIS Number',
            cell: ({ row }) => (
                <Link
                    href={beneficiaryShow.url({
                        department: departmentSlug,
                        beneficiary: row.original.id,
                    })}
                    className="font-medium text-primary hover:underline"
                >
                    {row.original.cais_number}
                </Link>
            ),
        },
        {
            id: 'beneficiary',
            accessorKey: 'name',
            meta: { title: 'Beneficiary' },
            header: 'Beneficiary',
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="font-medium">{row.original.name}</span>
                    {row.original.address ? (
                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                            <MapPin className="size-3 shrink-0" aria-hidden />
                            {row.original.address}
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            accessorKey: 'type',
            meta: { title: 'Type' },
            header: 'Type',
            cell: ({ row }) => typeBadge(row.original.type),
        },
        {
            accessorKey: 'contact',
            meta: { title: 'Contact' },
            header: 'Contact',
            cell: ({ row }) =>
                row.original.contact ? (
                    <span className="flex items-center gap-1.5 tabular-nums">
                        <Phone
                            className="size-3.5 shrink-0 text-muted-foreground"
                            aria-hidden
                        />
                        {row.original.contact}
                    </span>
                ) : (
                    <span className="text-muted-foreground/60">—</span>
                ),
        },
        {
            accessorKey: 'assistances_count',
            meta: {
                title: 'Requests',
                cellClassName: 'text-right tabular-nums',
            },
            header: () => <div className="text-right">Requests</div>,
            cell: ({ row }) =>
                row.original.assistances_count > 0 ? (
                    row.original.assistances_count.toLocaleString()
                ) : (
                    <span className="text-muted-foreground/60">0</span>
                ),
        },
        {
            accessorKey: 'last_assisted_at',
            meta: {
                title: 'Last assisted',
                cellClassName: 'text-right tabular-nums text-muted-foreground',
            },
            header: () => <div className="text-right">Last assisted</div>,
            cell: ({ row }) =>
                row.original.last_assisted_at ? (
                    formatDate(row.original.last_assisted_at)
                ) : (
                    <span className="text-muted-foreground/60">Never</span>
                ),
        },
        {
            accessorKey: 'registered_at',
            meta: {
                title: 'Registered',
                cellClassName: 'text-right tabular-nums text-muted-foreground',
            },
            header: () => <div className="text-right">Registered</div>,
            cell: ({ row }) => formatDate(row.original.registered_at),
        },
    ];
}
