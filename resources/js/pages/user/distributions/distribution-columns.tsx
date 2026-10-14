'use client';

import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { DistributionOfferSummary } from '@/components/distribution/composition';
import type { DistributionOffer } from '@/components/distribution/composition';
import { Badge } from '@/components/ui/badge';
import { show as distributionShow } from '@/routes/user/distributions';

export type DistributionBatchRow = {
    id: number;
    batch_code: string;
    distribution_type_label: string;
    title: string;
    program: string | null;
    scheduled_at: string | null;
    status: string;
    status_label: string;
    beneficiary_count: number;
    approved_beneficiary_count: number;
    offer: DistributionOffer;
};

function formatWhen(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function createDistributionColumns(
    departmentSlug: string,
): ColumnDef<DistributionBatchRow>[] {
    return [
        {
            id: 'batch_code',
            accessorKey: 'batch_code',
            meta: { title: 'Reference', cellClassName: 'whitespace-normal' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Reference" />
            ),
            cell: ({ row }) => (
                <div className="min-w-0">
                    <Link
                        href={distributionShow.url({
                            department: departmentSlug,
                            distributionBatch: row.original.id,
                        })}
                        className="font-medium hover:underline"
                    >
                        {row.original.batch_code}
                    </Link>
                    <p className="text-[11px] text-muted-foreground">
                        {row.original.title}
                    </p>
                </div>
            ),
        },
        {
            id: 'distribution_type',
            accessorKey: 'distribution_type_label',
            enableSorting: false,
            meta: { title: 'Type' },
            header: 'Type',
            cell: ({ row }) => (
                <Badge variant="outline">
                    {row.original.distribution_type_label}
                </Badge>
            ),
        },
        {
            id: 'program',
            accessorKey: 'program',
            enableSorting: false,
            meta: { title: 'Program' },
            header: 'Program',
            cell: ({ row }) => row.original.program ?? '—',
        },
        {
            id: 'scheduled_at',
            accessorKey: 'scheduled_at',
            meta: { title: 'Scheduled' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Scheduled" />
            ),
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {formatWhen(row.original.scheduled_at)}
                </span>
            ),
        },
        {
            id: 'beneficiaries',
            enableSorting: false,
            meta: { title: 'Beneficiaries' },
            header: 'Beneficiaries',
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {row.original.approved_beneficiary_count}/
                    {row.original.beneficiary_count}
                </span>
            ),
        },
        {
            id: 'assistance',
            enableSorting: false,
            meta: { title: 'Assistance', cellClassName: 'whitespace-normal' },
            header: 'Assistance',
            cell: ({ row }) => (
                <DistributionOfferSummary offer={row.original.offer} />
            ),
        },
        {
            id: 'status',
            accessorKey: 'status',
            meta: { title: 'Status' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <Badge variant="outline">{row.original.status_label}</Badge>
            ),
        },
    ];
}
