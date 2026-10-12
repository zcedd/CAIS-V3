'use client';

import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatPeso } from '@/lib/format-peso';
import { show as beneficiaryShow } from '@/routes/user/beneficiaries';
import { index as queueIndex } from '@/routes/user/queue';
import type { BeneficiaryListRow } from '@/types/beneficiary';

export type BeneficiaryTableContext = {
    departmentSlug: string;
};

function beneficiaryTypeLabel(type: BeneficiaryListRow['type']): string {
    return type === 'organization' ? 'Organization' : 'Individual';
}

function CopyableValue({
    value,
    label,
}: {
    value: string | null;
    label: string;
}) {
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const handle = window.setTimeout(() => setCopied(false), 1500);

        return () => window.clearTimeout(handle);
    }, [copied]);

    if (value == null || value.trim() === '') {
        return '-';
    }

    return (
        <span className="inline-flex items-center gap-1">
            <span>{value}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={copied ? `${label} copied` : `Copy ${label}`}
                onClick={() => {
                    void copy(value).then((copiedSuccessfully) => {
                        if (copiedSuccessfully) {
                            setCopied(true);
                        }
                    });
                }}
            >
                {copied ? <Check /> : <Copy />}
            </Button>
        </span>
    );
}

export function createBeneficiaryColumns({
    departmentSlug,
}: BeneficiaryTableContext): ColumnDef<BeneficiaryListRow>[] {
    return [
        {
            id: 'beneficiary',
            accessorKey: 'name',
            meta: { title: 'Beneficiary & proof of life' },
            header: 'Beneficiary & proof of life',
            cell: ({ row }) => {
                const sectors = row.original.sectors ?? [];
                const details = [
                    row.original.age !== null &&
                    row.original.age !== undefined
                        ? `Age ${row.original.age}`
                        : null,
                    row.original.sex,
                    row.original.civil_status,
                ].filter(Boolean);

                return (
                    <div className="min-w-0">
                        <Link
                            href={beneficiaryShow.url({
                                department: departmentSlug,
                                beneficiary: row.original.id,
                            })}
                            className="font-medium hover:underline"
                        >
                            {row.original.name}
                        </Link>
                        {details.length > 0 ? (
                            <p className="text-[11px] text-muted-foreground">
                                {details.join(' · ')}
                            </p>
                        ) : null}
                        {sectors.length > 0 ? (
                            <div className="mt-1 flex flex-wrap gap-1">
                                {sectors.map((sector) => (
                                    <Badge key={sector} variant="outline">
                                        {sector}
                                    </Badge>
                                ))}
                            </div>
                        ) : null}
                    </div>
                );
            },
        },
        {
            id: 'type',
            accessorKey: 'type',
            meta: { title: 'Type' },
            header: 'Type',
            cell: ({ row }) => (
                <Badge variant="outline">
                    {beneficiaryTypeLabel(row.original.type)}
                </Badge>
            ),
        },
        {
            id: 'cais_number',
            accessorKey: 'cais_number',
            meta: { title: 'CAIS number' },
            header: 'CAIS number',
            cell: ({ row }) => (
                <CopyableValue
                    value={row.original.cais_number}
                    label="CAIS number"
                />
            ),
        },
        {
            id: 'government_id',
            meta: { title: 'Government ID' },
            header: 'Government ID',
            cell: ({ row }) => {
                const primaryId = row.original.primary_id;

                if (primaryId?.number == null || primaryId.number === '') {
                    return '-';
                }

                return (
                    <div>
                        <p className="text-[10px] tracking-wide text-muted-foreground uppercase">
                            {primaryId.type ?? 'Government ID'}
                        </p>
                        <p className="font-mono text-xs">{primaryId.number}</p>
                    </div>
                );
            },
        },
        {
            id: 'contact',
            accessorKey: 'contact',
            meta: { title: 'Contact number' },
            header: 'Contact number',
            cell: ({ row }) => (
                <CopyableValue
                    value={row.original.contact}
                    label="contact number"
                />
            ),
        },
        {
            id: 'address',
            meta: { title: 'Address' },
            header: 'Address',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">
                        {row.original.city ?? '-'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {row.original.barangay}
                    </p>
                    {row.original.other_address ? (
                        <p className="text-[11px] text-muted-foreground">
                            {row.original.other_address}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'history',
            meta: { title: 'Assistance history' },
            header: 'Assistance history',
            cell: ({ row }) => {
                const delivered = row.original.delivered_count ?? 0;
                const cash = row.original.cash_received ?? 0;

                return (
                    <div>
                        <span className="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] text-emerald-700 dark:text-emerald-400">
                            Received {delivered}{' '}
                            {delivered === 1 ? 'time' : 'times'}
                        </span>
                        <p className="mt-1 font-mono text-xs tabular-nums">
                            {cash > 0 ? formatPeso(cash) : '-'}
                            {(row.original.goods_received ?? 0) > 0
                                ? ` · ${row.original.goods_received} in-kind`
                                : ''}
                        </p>
                    </div>
                );
            },
        },
        {
            id: 'actions',
            meta: { title: 'Actions' },
            header: 'Actions',
            cell: ({ row }) => (
                <Button variant="outline" size="sm" asChild>
                    <Link
                        href={queueIndex.url(departmentSlug, {
                            query: { search: row.original.name },
                        })}
                    >
                        Assist
                    </Link>
                </Button>
            ),
        },
    ];
}
