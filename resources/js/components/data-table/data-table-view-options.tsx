'use client';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Table, VisibilityState } from '@tanstack/react-table';
import { Settings2 } from 'lucide-react';

function columnLabel(columnId: string): string {
    return columnId
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function isColumnVisible(
    columnVisibility: VisibilityState,
    columnId: string,
): boolean {
    return columnVisibility[columnId] !== false;
}

export function DataTableViewOptions<TData>({
    table,
    columnVisibility,
}: {
    table: Table<TData>;
    columnVisibility: VisibilityState;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="ml-auto h-8">
                    <Settings2 className="mr-2 size-4" />
                    View
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-[200px]">
                <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {table
                    .getAllLeafColumns()
                    .filter((column) => column.getCanHide())
                    .map((column) => {
                        const meta = column.columnDef.meta as
                            | { title?: string }
                            | undefined;
                        const isVisible = isColumnVisible(
                            columnVisibility,
                            column.id,
                        );

                        return (
                            <DropdownMenuItem
                                key={column.id}
                                className="gap-2.5 capitalize"
                                onSelect={(event) => {
                                    event.preventDefault();
                                    table.setColumnVisibility((previous) => ({
                                        ...previous,
                                        [column.id]: !isVisible,
                                    }));
                                }}
                            >
                                <Checkbox
                                    checked={isVisible}
                                    onCheckedChange={() => {}}
                                    tabIndex={-1}
                                    aria-label={
                                        isVisible
                                            ? `${meta?.title ?? column.id} visible`
                                            : `${meta?.title ?? column.id} hidden`
                                    }
                                    className="pointer-events-none"
                                />
                                <span className="flex-1">
                                    {meta?.title ?? columnLabel(column.id)}
                                </span>
                            </DropdownMenuItem>
                        );
                    })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
