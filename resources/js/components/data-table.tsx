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
    RowSelectionState,
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

export type DataTableSelectionContext<TData> = {
    table: TanstackTable<TData>;
    rowSelection: RowSelectionState;
    selectedCount: number;
};

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
    onPageChange?: (page: number) => void;
    partialReloadOnly?: string[];
    isLoading?: boolean;
    loadingFallback?: React.ReactNode;
    toolbar?: (
        table: TanstackTable<TData>,
        columnVisibility: VisibilityState,
        columnFilters: ColumnFiltersState,
        rowSelection: RowSelectionState,
    ) => React.ReactNode;
    selectionActions?: (
        context: DataTableSelectionContext<TData>,
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

function DataTableSelectionActions<TData>({
    context,
    render,
}: {
    context: DataTableSelectionContext<TData>;
    render: (context: DataTableSelectionContext<TData>) => React.ReactNode;
}) {
    return <>{render(context)}</>;
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
    onPageChange,
    partialReloadOnly,
    isLoading = false,
    loadingFallback,
    toolbar,
    selectionActions,
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

    React.useEffect(() => {
        if (!enableRowSelection || !manualPagination) {
            return;
        }

        setRowSelection({});
    }, [enableRowSelection, manualPagination, serverPagination?.current_page]);

    const isAdvanced = Boolean(toolbar);

    const table = useReactTable<TData>({
        data,
        columns,
        getRowId: (originalRow, index) => {
            if (
                typeof originalRow === 'object' &&
                originalRow !== null &&
                'id' in originalRow
            ) {
                const rowId = (originalRow as { id: unknown }).id;

                if (
                    typeof rowId === 'number' ||
                    typeof rowId === 'string'
                ) {
                    return String(rowId);
                }
            }

            return String(index);
        },
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
        ...(manualPagination && serverPagination
            ? { rowCount: serverPagination.total }
            : {}),
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

    const selectedCount = Object.values(rowSelection).filter(Boolean).length;

    const selectionContext = React.useMemo(
        () => ({
            table,
            rowSelection,
            selectedCount,
        }),
        [table, rowSelection, selectedCount],
    );

    const skeletonMarkup = loadingFallback ?? (
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
                        <DataTablePagination
                            table={table}
                            rowSelection={
                                enableRowSelection ? rowSelection : undefined
                            }
                        />
                    ) : null}
                </div>
            </DataTableSortingContext.Provider>
        );
    }

    return (
        <DataTableSortingContext.Provider value={sortingContextValue}>
            <div className="space-y-4">
                {enableRowSelection && selectionActions ? (
                    <DataTableSelectionActions
                        context={selectionContext}
                        render={selectionActions}
                    />
                ) : null}
                {toolbar?.(table, columnVisibility, columnFilters, rowSelection)}
                {tableMarkup}
                <DataTablePagination
                    table={table}
                    rowSelection={
                        enableRowSelection ? rowSelection : undefined
                    }
                    serverPagination={
                        manualPagination ? serverPagination : undefined
                    }
                    onPerPageChange={
                        manualPagination ? onPerPageChange : undefined
                    }
                    onPageChange={manualPagination ? onPageChange : undefined}
                    partialReloadOnly={partialReloadOnly}
                />
            </div>
        </DataTableSortingContext.Provider>
    );
}
