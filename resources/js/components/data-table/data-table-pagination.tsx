'use client';

import type { ServerPaginationMeta } from '@/components/data-table/types';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Link } from '@inertiajs/react';
import { Table } from '@tanstack/react-table';
import { ChevronLeft, ChevronRight } from 'lucide-react';

const PAGE_SIZE_OPTIONS = [10, 15, 20, 25, 30, 40, 50] as const;

interface DataTablePaginationProps<TData> {
    table: Table<TData>;
    serverPagination?: ServerPaginationMeta;
    onPerPageChange?: (perPage: number) => void;
}

export function DataTablePagination<TData>({
    table,
    serverPagination,
    onPerPageChange,
}: DataTablePaginationProps<TData>) {
    const selectedCount = table.getFilteredSelectedRowModel().rows.length;
    const filteredCount = serverPagination
        ? serverPagination.total
        : table.getFilteredRowModel().rows.length;

    const perPage = serverPagination
        ? serverPagination.per_page
        : table.getState().pagination.pageSize;

    return (
        <div className="flex items-center justify-between px-2">
            <div className="flex-1 text-sm text-muted-foreground">
                {selectedCount} of {filteredCount} row(s) selected.
            </div>
            <div className="flex items-center space-x-6 lg:space-x-8">
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
                    Page{' '}
                    {serverPagination
                        ? serverPagination.current_page
                        : table.getState().pagination.pageIndex + 1}{' '}
                    of{' '}
                    {serverPagination
                        ? serverPagination.last_page
                        : table.getPageCount()}
                </div>
                <div className="flex items-center space-x-2">
                    {serverPagination ? (
                        <>
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
                                        href={serverPagination.prev_page_url}
                                        preserveScroll
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
                                        href={serverPagination.next_page_url}
                                        preserveScroll
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
                        </>
                    ) : (
                        <>
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
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
