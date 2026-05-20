'use client';

import {
    getColumnSortDirection,
    useDataTableSorting,
} from '@/components/data-table/data-table-sorting-context';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { Column } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown, EyeOff } from 'lucide-react';

interface DataTableColumnHeaderProps<TData, TValue>
    extends React.HTMLAttributes<HTMLDivElement> {
    column: Column<TData, TValue>;
    title: string;
}

export function DataTableColumnHeader<TData, TValue>({
    column,
    title,
    className,
}: DataTableColumnHeaderProps<TData, TValue>) {
    const { sorting, onSortChange } = useDataTableSorting();
    const sorted = getColumnSortDirection(sorting, column.id);

    if (!column.getCanSort()) {
        return <div className={cn(className)}>{title}</div>;
    }

    const applySort = (direction: 'asc' | 'desc') => {
        if (onSortChange) {
            onSortChange(column.id, direction);

            return;
        }

        column.toggleSorting(direction === 'desc');
    };

    return (
        <div className={cn('flex items-center space-x-2', className)}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-3 h-8 data-[state=open]:bg-accent"
                    >
                        <span>{title}</span>
                        {sorted === 'desc' ? (
                            <ArrowDown className="ml-2 size-4" />
                        ) : sorted === 'asc' ? (
                            <ArrowUp className="ml-2 size-4" />
                        ) : (
                            <ChevronsUpDown className="ml-2 size-4 text-muted-foreground" />
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem
                        onSelect={(event) => {
                            event.preventDefault();
                            applySort('asc');
                        }}
                    >
                        <ArrowUp className="mr-2 h-3.5 w-3.5 text-muted-foreground/70" />
                        Asc
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onSelect={(event) => {
                            event.preventDefault();
                            applySort('desc');
                        }}
                    >
                        <ArrowDown className="mr-2 h-3.5 w-3.5 text-muted-foreground/70" />
                        Desc
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onSelect={(event) => {
                            event.preventDefault();
                            column.toggleVisibility(false);
                        }}
                    >
                        <EyeOff className="mr-2 h-3.5 w-3.5 text-muted-foreground/70" />
                        Hide
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
