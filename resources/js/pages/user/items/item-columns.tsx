'use client';

import { Badge } from '@/components/ui/badge';
import { DataTableColumnHeader } from '@/components/data-table/data-table-column-header';
import { ItemDataTableRowActions } from '@/pages/user/items/item-row-actions';
import type { UnitMeasurementOption } from '@/pages/user/items/item-toolbar';
import { ITEM_KIND_LABELS, tracksInventory, type ItemKind } from '@/types/item';
import { ColumnDef } from '@tanstack/react-table';
import { EMPTY_CELL } from '@/lib/empty-cell';

export type UserDepartmentItemRow = {
    id: number;
    name: string;
    kind: ItemKind;
    item_unit_measurement_id: number | null;
    unit: string | null;
    unspsc_code_id: number | null;
    unspsc_code: string | null;
    unspsc_title: string | null;
    is_perishable: boolean;
    low_stock_threshold: number | null;
    on_hand: number;
    allocated: number;
    available: number;
    nearest_expiry: string | null;
    is_low_stock: boolean;
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
                <div className="flex items-center gap-2">
                    <span className="font-medium">{row.original.name}</span>
                    <Badge variant="outline">
                        {ITEM_KIND_LABELS[row.original.kind]}
                    </Badge>
                    {row.original.is_low_stock ? (
                        <Badge variant="destructive">Low stock</Badge>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'unit',
            accessorFn: (row) => row.unit ?? '',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Unit" />
            ),
            cell: ({ row }) => row.original.unit ?? EMPTY_CELL,
        },
        {
            id: 'on_hand',
            accessorFn: (row) => row.on_hand,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="On hand" />
            ),
            cell: ({ row }) =>
                tracksInventory(row.original.kind) ? (
                    <span className="tabular-nums">{row.original.on_hand}</span>
                ) : (
                    EMPTY_CELL
                ),
        },
        {
            id: 'allocated',
            accessorFn: (row) => row.allocated,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Allocated" />
            ),
            cell: ({ row }) =>
                tracksInventory(row.original.kind) ? (
                    <span className="tabular-nums">
                        {row.original.allocated}
                    </span>
                ) : (
                    EMPTY_CELL
                ),
        },
        {
            id: 'available',
            accessorFn: (row) => row.available,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Available" />
            ),
            cell: ({ row }) =>
                tracksInventory(row.original.kind) ? (
                    <span className="tabular-nums">
                        {row.original.available}
                    </span>
                ) : (
                    EMPTY_CELL
                ),
        },
        {
            id: 'unspsc',
            accessorFn: (row) => row.unspsc_code ?? '',
            enableSorting: false,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="UNSPSC" />
            ),
            cell: ({ row }) =>
                row.original.unspsc_code ? (
                    <span title={row.original.unspsc_title ?? undefined}>
                        {row.original.unspsc_code}
                    </span>
                ) : (
                    EMPTY_CELL
                ),
        },
        {
            id: 'nearest_expiry',
            accessorFn: (row) => row.nearest_expiry ?? '',
            enableSorting: false,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Nearest expiry" />
            ),
            cell: ({ row }) =>
                tracksInventory(row.original.kind)
                    ? (row.original.nearest_expiry ?? EMPTY_CELL)
                    : EMPTY_CELL,
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
