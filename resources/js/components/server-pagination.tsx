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
import { cn } from '@/lib/utils';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';

export const PROGRAM_PAGE_SIZE_OPTIONS = [8, 12, 16, 20, 24, 32, 48] as const;

type PageItem = number | 'ellipsis';

function buildPageItems(current: number, last: number): PageItem[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const items: PageItem[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) {
        items.push('ellipsis');
    }

    for (let page = start; page <= end; page++) {
        items.push(page);
    }

    if (end < last - 1) {
        items.push('ellipsis');
    }

    items.push(last);

    return items;
}

type ServerPaginationProps = {
    pagination: ServerPaginationMeta;
    onPageChange: (page: number) => void;
    onPerPageChange?: (perPage: number) => void;
    perPageOptions?: readonly number[];
    className?: string;
};

export function ServerPagination({
    pagination,
    onPageChange,
    onPerPageChange,
    perPageOptions = PROGRAM_PAGE_SIZE_OPTIONS,
    className,
}: ServerPaginationProps) {
    const {
        current_page: currentPage,
        last_page: lastPage,
        per_page: perPage,
        total,
        from,
        to,
    } = pagination;

    if (total === 0) {
        return null;
    }

    const pageItems = buildPageItems(currentPage, lastPage);
    const canGoPrevious = currentPage > 1;
    const canGoNext = currentPage < lastPage;

    return (
        <div
            className={cn(
                'flex flex-col gap-3 border-t border-border pt-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <p className="text-xs text-muted-foreground tabular-nums">
                {from ?? 0} to {to ?? 0} of {total}
            </p>

            <div className="flex flex-wrap items-center gap-3">
                {onPerPageChange ? (
                    <div className="flex items-center gap-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            Per page
                        </p>
                        <Select
                            value={`${perPage}`}
                            onValueChange={(value) =>
                                onPerPageChange(Number(value))
                            }
                        >
                            <SelectTrigger className="h-8 w-[72px]">
                                <SelectValue placeholder={perPage} />
                            </SelectTrigger>
                            <SelectContent side="top">
                                {perPageOptions.map((size) => (
                                    <SelectItem key={size} value={`${size}`}>
                                        {size}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                ) : null}

                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        className="size-8"
                        disabled={!canGoPrevious}
                        onClick={() => onPageChange(1)}
                        aria-label="First page"
                    >
                        <ChevronsLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        className="size-8"
                        disabled={!canGoPrevious}
                        onClick={() => onPageChange(currentPage - 1)}
                        aria-label="Previous page"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>

                    {pageItems.map((item, index) =>
                        item === 'ellipsis' ? (
                            <span
                                key={`ellipsis-${index}`}
                                className="px-1.5 text-xs text-muted-foreground"
                            >
                                …
                            </span>
                        ) : (
                            <Button
                                key={item}
                                type="button"
                                variant={
                                    item === currentPage ? 'default' : 'outline'
                                }
                                size="icon-sm"
                                className="size-8 tabular-nums"
                                onClick={() => onPageChange(item)}
                                aria-label={`Page ${item}`}
                                aria-current={
                                    item === currentPage ? 'page' : undefined
                                }
                            >
                                {item}
                            </Button>
                        ),
                    )}

                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        className="size-8"
                        disabled={!canGoNext}
                        onClick={() => onPageChange(currentPage + 1)}
                        aria-label="Next page"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        className="size-8"
                        disabled={!canGoNext}
                        onClick={() => onPageChange(lastPage)}
                        aria-label="Last page"
                    >
                        <ChevronsRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
