'use client';

import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { show as assistanceShow } from '@/routes/user/assistances';
import { show as beneficiaryShow } from '@/routes/user/beneficiaries';
import type {
    BeneficiaryAssistanceRow,
    IndividualOrganizationMembership,
    OrganizationMember,
} from '@/types/beneficiary';
import { Link } from '@inertiajs/react';
import type { ColumnDef, Table, VisibilityState } from '@tanstack/react-table';

const STATUS_BADGE_CLASSES: Record<string, string> = {
    Delivered:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    Verified:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
    Pending:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    Denied: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
};

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

export type MembershipRow =
    | OrganizationMember
    | IndividualOrganizationMembership;

export function createMembershipColumns({
    departmentSlug,
    entityLabel,
}: {
    departmentSlug: string;
    entityLabel: string;
}): ColumnDef<MembershipRow>[] {
    return [
        {
            accessorKey: 'cais_number',
            meta: { title: 'CAIS Number' },
            header: 'CAIS Number',
            cell: ({ row }) =>
                row.original.beneficiary_id ? (
                    <Link
                        href={beneficiaryShow.url({
                            department: departmentSlug,
                            beneficiary: row.original.beneficiary_id,
                        })}
                        className="font-medium text-primary hover:underline"
                    >
                        {row.original.cais_number}
                    </Link>
                ) : (
                    row.original.cais_number
                ),
        },
        {
            accessorKey: 'name',
            meta: { title: entityLabel },
            header: entityLabel,
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'is_president',
            meta: { title: 'Role' },
            header: 'Role',
            cell: ({ row }) =>
                row.original.is_president ? (
                    <Badge variant="secondary">President</Badge>
                ) : (
                    <Badge variant="outline">Member</Badge>
                ),
        },
    ];
}

export function createBeneficiaryAssistanceColumns(): ColumnDef<BeneficiaryAssistanceRow>[] {
    return [
        {
            accessorKey: 'program_name',
            meta: { title: 'Program' },
            header: 'Program',
            cell: ({ row }) =>
                row.original.department_slug ? (
                    <Link
                        href={assistanceShow.url({
                            department: row.original.department_slug,
                            program: row.original.program_id,
                            assistance: row.original.id,
                        })}
                        className="font-medium text-primary hover:underline"
                    >
                        {row.original.program_name}
                    </Link>
                ) : (
                    <span className="font-medium">
                        {row.original.program_name}
                    </span>
                ),
        },
        {
            accessorKey: 'department_name',
            meta: { title: 'Department' },
            header: 'Department',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.department_name}
                </span>
            ),
        },
        {
            accessorKey: 'mode_of_request',
            meta: { title: 'Mode' },
            header: 'Mode',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.mode_of_request}
                </span>
            ),
        },
        {
            accessorKey: 'date_requested',
            meta: {
                title: 'Requested',
                cellClassName: 'tabular-nums text-muted-foreground',
            },
            header: 'Requested',
            cell: ({ row }) => formatDate(row.original.date_requested),
        },
        {
            accessorKey: 'status',
            meta: { title: 'Status' },
            header: 'Status',
            cell: ({ row }) => (
                <Badge
                    variant="outline"
                    className={cn(
                        STATUS_BADGE_CLASSES[row.original.status] ?? '',
                    )}
                >
                    {row.original.status}
                </Badge>
            ),
        },
    ];
}

export function BeneficiaryShowTableToolbar<TData>({
    table,
    columnVisibility,
}: {
    table: Table<TData>;
    columnVisibility: VisibilityState;
}) {
    return (
        <div className="flex items-center justify-end">
            <DataTableViewOptions
                table={table}
                columnVisibility={columnVisibility}
            />
        </div>
    );
}
