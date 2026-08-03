'use client';

import type { ServerPaginationMeta } from '@/components/data-table/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Link } from '@inertiajs/react';
import { RowSelectionState, Table } from '@tanstack/react-table';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const PAGE_SIZE_OPTIONS = [10, 15, 20, 25, 30, 40, 50] as const;

function clampPage(value: number, lastPage: number): number {
    if (!Number.isFinite(value)) {
        return 1;
    }

    return Math.min(lastPage, Math.max(1, Math.trunc(value)));
}

interface DataTablePaginationProps<TData> {
    table: Table<TData>;
    rowSelection?: RowSelectionState;
    serverPagination?: ServerPaginationMeta;
    onPerPageChange?: (perPage: number) => void;
    onPageChange?: (page: number) => void;
    partialReloadOnly?: string[];
}

export function DataTablePagination<TData>({
    table,
    rowSelection,
    serverPagination,
    onPerPageChange,
    onPageChange,
    partialReloadOnly,
}: DataTablePaginationProps<TData>) {
    const partialReloadProps = partialReloadOnly
        ? {
              only: partialReloadOnly,
              preserveState: true,
              preserveScroll: true,
          }
        : undefined;
    const selectedCount = rowSelection
        ? Object.values(rowSelection).filter(Boolean).length
        : table.getFilteredSelectedRowModel().rows.length;
    const filteredCount = serverPagination
        ? serverPagination.total
        : table.getFilteredRowModel().rows.length;

    const perPage = serverPagination
        ? serverPagination.per_page
        : table.getState().pagination.pageSize;

    const currentPage = serverPagination
        ? serverPagination.current_page
        : table.getState().pagination.pageIndex + 1;

    const lastPage = serverPagination
        ? serverPagination.last_page
        : table.getPageCount();

    const [jumpValue, setJumpValue] = useState(String(currentPage));

    useEffect(() => {
        setJumpValue(String(currentPage));
    }, [currentPage]);

    const canGoPrevious = serverPagination
        ? serverPagination.prev_page_url !== null
        : table.getCanPreviousPage();

    const canGoNext = serverPagination
        ? serverPagination.next_page_url !== null
        : table.getCanNextPage();

    const jumpToPage = () => {
        const nextPage = clampPage(Number(jumpValue), Math.max(1, lastPage));

        setJumpValue(String(nextPage));

        if (nextPage === currentPage) {
            return;
        }

        if (serverPagination && onPageChange) {
            onPageChange(nextPage);

            return;
        }

        table.setPageIndex(nextPage - 1);
    };

    return (
        <div className="flex flex-col gap-3 px-2 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex-1 text-sm text-muted-foreground">
                {selectedCount} of {filteredCount} row(s) selected.
            </div>
            <div className="flex flex-wrap items-center gap-4 lg:gap-6">
                <div className="flex items-center space-x-2">
                    <p className="text-sm font-medium">Rows per page</p>
                    {serverPagination && onPerPageChange ? (
                        <Select
                            value={`${perPage}`}
                            onValueChange={(value) => {
                                onPerPageChange(Number(value));
                            }}
                        >
                            <SelectTrigger className="h-8 w-[70px]">
                                <SelectValue placeholder={perPage} />
                            </SelectTrigger>
                            <SelectContent side="top">
                                {PAGE_SIZE_OPTIONS.map((pageSize) => (
                                    <SelectItem
                                        key={pageSize}
                                        value={`${pageSize}`}
                                    >
                                        {pageSize}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
                        <Select
                            value={`${perPage}`}
                            onValueChange={(value) => {
                                table.setPageSize(Number(value));
                            }}
                        >
                            <SelectTrigger className="h-8 w-[70px]">
                                <SelectValue placeholder={perPage} />
                            </SelectTrigger>
                            <SelectContent side="top">
                                {PAGE_SIZE_OPTIONS.map((pageSize) => (
                                    <SelectItem
                                        key={pageSize}
                                        value={`${pageSize}`}
                                    >
                                        {pageSize}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>
                <div className="flex w-[100px] items-center justify-center text-sm font-medium">
                    Page {currentPage} of {lastPage}
                </div>
                <div className="flex items-center space-x-2">
                    {serverPagination ? (
                        <>
                            {onPageChange ? (
                                <Button
                                    variant="outline"
                                    className="size-8"
                                    disabled={!canGoPrevious}
                                    onClick={() => onPageChange(1)}
                                    aria-label="Go to first page"
                                >
                                    <ChevronsLeft className="size-4" />
                                </Button>
                            ) : null}
                            {onPageChange ? (
                                <Button
                                    variant="outline"
                                    className="size-8"
                                    disabled={!canGoPrevious}
                                    onClick={() =>
                                        onPageChange(currentPage - 1)
                                    }
                                    aria-label="Go to previous page"
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    className="size-8"
                                    disabled={
                                        serverPagination.prev_page_url === null
                                    }
                                    asChild={
                                        serverPagination.prev_page_url !== null
                                    }
                                >
                                    {serverPagination.prev_page_url ? (
                                        <Link
                                            href={
                                                serverPagination.prev_page_url
                                            }
                                            preserveScroll
                                            {...partialReloadProps}
                                        >
                                            <span className="sr-only">
                                                Go to previous page
                                            </span>
                                            <ChevronLeft className="size-4" />
                                        </Link>
                                    ) : (
                                        <span>
                                            <span className="sr-only">
                                                Go to previous page
                                            </span>
                                            <ChevronLeft className="size-4" />
                                        </span>
                                    )}
                                </Button>
                            )}
                            {onPageChange ? (
                                <Button
                                    variant="outline"
                                    className="size-8"
                                    disabled={!canGoNext}
                                    onClick={() =>
                                        onPageChange(currentPage + 1)
                                    }
                                    aria-label="Go to next page"
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    className="size-8"
                                    disabled={
                                        serverPagination.next_page_url === null
                                    }
                                    asChild={
                                        serverPagination.next_page_url !== null
                                    }
                                >
                                    {serverPagination.next_page_url ? (
                                        <Link
                                            href={
                                                serverPagination.next_page_url
                                            }
                                            preserveScroll
                                            {...partialReloadProps}
                                        >
                                            <span className="sr-only">
                                                Go to next page
                                            </span>
                                            <ChevronRight className="size-4" />
                                        </Link>
                                    ) : (
                                        <span>
                                            <span className="sr-only">
                                                Go to next page
                                            </span>
                                            <ChevronRight className="size-4" />
                                        </span>
                                    )}
                                </Button>
                            )}
                            {onPageChange ? (
                                <Button
                                    variant="outline"
                                    className="size-8"
                                    disabled={!canGoNext}
                                    onClick={() => onPageChange(lastPage)}
                                    aria-label="Go to last page"
                                >
                                    <ChevronsRight className="size-4" />
                                </Button>
                            ) : null}
                        </>
                    ) : (
                        <>
                            <Button
                                variant="outline"
                                className="size-8"
                                onClick={() => table.setPageIndex(0)}
                                disabled={!table.getCanPreviousPage()}
                                aria-label="Go to first page"
                            >
                                <ChevronsLeft className="size-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="size-8"
                                onClick={() => table.previousPage()}
                                disabled={!table.getCanPreviousPage()}
                            >
                                <span className="sr-only">
                                    Go to previous page
                                </span>
                                <ChevronLeft className="size-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="size-8"
                                onClick={() => table.nextPage()}
                                disabled={!table.getCanNextPage()}
                            >
                                <span className="sr-only">Go to next page</span>
                                <ChevronRight className="size-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="size-8"
                                onClick={() =>
                                    table.setPageIndex(table.getPageCount() - 1)
                                }
                                disabled={!table.getCanNextPage()}
                                aria-label="Go to last page"
                            >
                                <ChevronsRight className="size-4" />
                            </Button>
                        </>
                    )}
                </div>

                {lastPage > 1 && (!serverPagination || onPageChange) ? (
                    <form
                        className="flex items-center gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            jumpToPage();
                        }}
                    >
                        <label
                            htmlFor="data-table-pagination-jump"
                            className="text-sm font-medium"
                        >
                            Jump
                        </label>
                        <Input
                            id="data-table-pagination-jump"
                            type="number"
                            min={1}
                            max={lastPage}
                            inputMode="numeric"
                            value={jumpValue}
                            onChange={(event) =>
                                setJumpValue(event.target.value)
                            }
                            onBlur={jumpToPage}
                            className="h-8 w-16 rounded-md px-2 text-center tabular-nums"
                            aria-label={`Jump to page, 1 to ${lastPage}`}
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            className="h-8 px-2.5"
                        >
                            Go
                        </Button>
                    </form>
                ) : null}
            </div>
        </div>
    );
}
