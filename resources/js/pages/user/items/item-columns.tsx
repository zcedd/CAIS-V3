'use client';

import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { ItemDataTableRowActions } from '@/pages/user/items/item-row-actions';
import type { UnitMeasurementOption } from '@/pages/user/items/item-toolbar';
import { ColumnDef } from '@tanstack/react-table';

export type UserDepartmentItemRow = {
    id: number;
    name: string;
    item_unit_measurement_id: number | null;
    unit: string | null;
};

export type UserDepartmentItemTableContext = {
    departmentSlug: string;
    unitMeasurements: UnitMeasurementOption[];
    onItemUpdated?: () => void;
};

export function createUserDepartmentItemColumns({
    departmentSlug,
    unitMeasurements,
    onItemUpdated,
}: UserDepartmentItemTableContext): ColumnDef<UserDepartmentItemRow>[] {
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
            id: 'unit',
            accessorFn: (row) => row.unit ?? '',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Unit" />
            ),
            cell: ({ row }) => row.original.unit ?? '—',
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <ItemDataTableRowActions
                    row={row}
                    departmentSlug={departmentSlug}
                    unitMeasurements={unitMeasurements}
                    onItemUpdated={onItemUpdated}
                />
            ),
        },
    ];
}
