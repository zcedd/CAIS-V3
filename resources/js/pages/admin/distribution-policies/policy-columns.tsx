'use client';

import type { ColumnDef } from '@tanstack/react-table';
import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { Badge } from '@/components/ui/badge';
import { DistributionPolicyRowActions } from '@/pages/admin/distribution-policies/policy-row-actions';

export type DistributionPolicyStage = {
    id?: number;
    name: string;
    sequence: number;
    role_name: string | null;
    user_id: number | null;
    has_tasks?: boolean;
};

export type DistributionPolicy = {
    id: number;
    name: string;
    department_id: number | null;
    program_id: number | null;
    department: string | null;
    program: string | null;
    approval_mode: string | null;
    strategy: string;
    is_active: boolean;
    allow_partial_finalization: boolean;
    allow_amount_modification: boolean;
    allow_self_approval: boolean;
    reschedule_requires_reapproval: boolean;
    allow_resubmit_after_reject: boolean;
    block_active_batch_reuse: boolean;
    min_amount: string | null;
    max_amount: string | null;
    stages: DistributionPolicyStage[];
};

type Option = { value: string; label: string };

function labelFor(options: Option[], value: string | null): string {
    if (value === null || value === '') {
        return '';
    }

    return options.find((option) => option.value === value)?.label ?? value;
}

export function createDistributionPolicyColumns({
    strategies,
    modes,
    onEdit,
}: {
    strategies: Option[];
    modes: Option[];
    onEdit: (policy: DistributionPolicy) => void;
}): ColumnDef<DistributionPolicy>[] {
    return [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Policy" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            id: 'strategy',
            accessorFn: (row) => labelFor(strategies, row.strategy),
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Strategy" />
            ),
        },
        {
            id: 'approval_mode',
            accessorFn: (row) =>
                labelFor(modes, row.approval_mode) || 'Any mode',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Mode" />
            ),
        },
        {
            id: 'department',
            accessorFn: (row) => row.department ?? 'Any department',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Department" />
            ),
        },
        {
            id: 'program',
            accessorFn: (row) => row.program ?? 'Any program',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Program" />
            ),
        },
        {
            id: 'status',
            accessorFn: (row) => (row.is_active ? 'Active' : 'Inactive'),
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <Badge variant="outline">
                    {row.original.is_active ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            id: 'stages',
            enableSorting: false,
            meta: { cellClassName: 'whitespace-normal' },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Stages" />
            ),
            cell: ({ row }) =>
                row.original.stages
                    .map(
                        (stage) =>
                            `${stage.sequence}. ${stage.name} (${stage.role_name ?? 'user'})`,
                    )
                    .join(', '),
        },
        {
            id: 'actions',
            enableHiding: false,
            enableSorting: false,
            meta: { cellClassName: 'text-right' },
            cell: ({ row }) => (
                <DistributionPolicyRowActions
                    policy={row.original}
                    onEdit={onEdit}
                />
            ),
        },
    ];
}
