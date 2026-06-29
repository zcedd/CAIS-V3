'use client';

import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { DataTableSortingContext } from '@/components/data-table/data-table-sorting-context';
import type {
    ServerPaginationMeta,
    ServerSortingState,
} from '@/components/data-table/types';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFacetedRowModel,
    getFacetedUniqueValues,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    Table as TanstackTable,
    useReactTable,
} from '@tanstack/react-table';
import React from 'react';

export type {
    ServerPaginationMeta,
    ServerSortingState,
} from '@/components/data-table/types';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    emptyMessage?: string;
    manualPagination?: boolean;
    manualSorting?: boolean;
    manualFiltering?: boolean;
    serverPagination?: ServerPaginationMeta;
    serverSorting?: ServerSortingState;
    onServerSortingChange?: (
        columnId: string,
        direction: 'asc' | 'desc',
    ) => void;
    onPerPageChange?: (perPage: number) => void;
    partialReloadOnly?: string[];
    isLoading?: boolean;
    loadingFallback?: React.ReactNode;
    toolbar?: (
        table: TanstackTable<TData>,
        columnVisibility: VisibilityState,
        columnFilters: ColumnFiltersState,
    ) => React.ReactNode;
    initialColumnVisibility?: VisibilityState;
    enableRowSelection?: boolean;
}

function sortingStateFromServer(
    serverSorting?: ServerSortingState,
): SortingState {
    if (!serverSorting) {
        return [];
    }

    return [
        {
            id: serverSorting.sort,
            desc: serverSorting.direction === 'desc',
        },
    ];
}

export function DataTable<TData, TValue>({
    columns,
    data,
    emptyMessage = 'No results.',
    manualPagination = false,
    manualSorting = false,
    manualFiltering = false,
    serverPagination,
    serverSorting,
    onServerSortingChange,
    onPerPageChange,
    partialReloadOnly,
    isLoading = false,
    loadingFallback,
    toolbar,
    initialColumnVisibility,
    enableRowSelection = false,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>(() =>
        sortingStateFromServer(serverSorting),
    );
    const [rowSelection, setRowSelection] = React.useState({});
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>(initialColumnVisibility ?? {});
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);

    React.useEffect(() => {
        if (manualSorting && serverSorting) {
            setSorting(sortingStateFromServer(serverSorting));
        }
    }, [manualSorting, serverSorting?.sort, serverSorting?.direction]);

    const isAdvanced = Boolean(toolbar);

    const table = useReactTable<TData>({
        data,
        columns,
        state: {
            sorting,
            columnVisibility,
            rowSelection,
            columnFilters,
        },
        initialState: {
            pagination: {
                pageSize: serverPagination?.per_page ?? 25,
            },
        },
        enableRowSelection: enableRowSelection || isAdvanced,
        manualSorting,
        manualFiltering,
        onRowSelectionChange: setRowSelection,
        onSortingChange: manualSorting ? undefined : setSorting,
        onColumnFiltersChange: manualFiltering ? undefined : setColumnFilters,
        onColumnVisibilityChange: setColumnVisibility,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel:
            isAdvanced && !manualFiltering ? getFilteredRowModel() : undefined,
        ...(manualSorting ? {} : { getSortedRowModel: getSortedRowModel() }),
        ...(manualPagination
            ? {}
            : { getPaginationRowModel: getPaginationRowModel() }),
        ...(isAdvanced && !manualFiltering
            ? {
                  getFacetedRowModel: getFacetedRowModel(),
                  getFacetedUniqueValues: getFacetedUniqueValues(),
              }
            : {}),
    });

    const sortingContextValue = React.useMemo(
        () => ({
            sorting,
            onSortChange: manualSorting ? onServerSortingChange : undefined,
        }),
        [sorting, manualSorting, onServerSortingChange],
    );

    const skeletonMarkup =
        loadingFallback ?? (
            <DataTableSkeleton
                columnCount={columns.length}
                rowCount={serverPagination?.per_page ?? 8}
            />
        );

    const tableMarkup = isLoading ? (
        skeletonMarkup
    ) : (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <TableHead key={header.id}>
                                    {header.isPlaceholder
                                        ? null
                                        : flexRender(
                                              header.column.columnDef.header,
                                              header.getContext(),
                                          )}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows?.length ? (
                        table.getRowModel().rows.map((row) => (
                            <TableRow
                                key={row.id}
                                data-state={
                                    row.getIsSelected() ? 'selected' : undefined
                                }
                            >
                                {row.getVisibleCells().map((cell) => {
                                    const meta = cell.column.columnDef.meta as
                                        | { cellClassName?: string }
                                        | undefined;

                                    return (
                                        <TableCell
                                            key={cell.id}
                                            className={meta?.cellClassName}
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    );
                                })}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length}
                                className="h-24 text-center"
                            >
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );

    if (!isAdvanced) {
        return (
            <DataTableSortingContext.Provider value={sortingContextValue}>
                <div className="space-y-4">
                    {tableMarkup}
                    {!manualPagination ? (
                        <DataTablePagination table={table} />
                    ) : null}
                </div>
            </DataTableSortingContext.Provider>
        );
    }

    return (
        <DataTableSortingContext.Provider value={sortingContextValue}>
            <div className="space-y-4">
                {toolbar?.(table, columnVisibility, columnFilters)}
                {tableMarkup}
                <DataTablePagination
                    table={table}
                    serverPagination={
                        manualPagination ? serverPagination : undefined
                    }
                    onPerPageChange={
                        manualPagination ? onPerPageChange : undefined
                    }
                    partialReloadOnly={partialReloadOnly}
                />
            </div>
        </DataTableSortingContext.Provider>
    );
}
