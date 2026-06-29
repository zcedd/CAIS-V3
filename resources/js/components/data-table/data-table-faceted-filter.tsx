'use client';

import { parseFacetedFilterValue } from '@/components/data-table/parse-faceted-filter-value';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { Column } from '@tanstack/react-table';
import { PlusCircle } from 'lucide-react';
import { useMemo } from 'react';

interface DataTableFacetedFilterProps<TData, TValue> {
    column?: Column<TData, TValue>;
    filterValue: unknown;
    title?: string;
    options: {
        label: string;
        value: string;
        icon?: React.ComponentType<{ className?: string }>;
    }[];
    onFilterChange?: (values: string[]) => void;
}

export function DataTableFacetedFilter<TData, TValue>({
    column,
    filterValue,
    title,
    options,
    onFilterChange,
}: DataTableFacetedFilterProps<TData, TValue>) {
    const facets = column?.getFacetedUniqueValues();

    const applyFilterValues = (values: string[]) => {
        if (onFilterChange) {
            onFilterChange(values);

            return;
        }

        column?.setFilterValue(values.length ? values : undefined);
    };
    const selectedValues = useMemo(
        () => new Set(parseFacetedFilterValue(filterValue)),
        [filterValue],
    );
    const hasSelection = selectedValues.size > 0;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className={cn(
                        'h-8 border-dashed',
                        hasSelection &&
                            'border-primary bg-primary/5 ring-1 ring-primary/30',
                    )}
                >
                    <PlusCircle className="mr-2 size-4" />
                    {title}
                    {hasSelection ? (
                        <>
                            <Separator orientation="vertical" className="mx-2 h-4" />
                            <Badge
                                variant="secondary"
                                className="rounded-sm px-1 font-normal md:hidden"
                            >
                                {selectedValues.size}
                            </Badge>
                            <div className="hidden gap-1 md:flex">
                                {selectedValues.size > 2 ? (
                                    <Badge
                                        variant="secondary"
                                        className="rounded-sm px-1 font-normal"
                                    >
                                        {selectedValues.size} selected
                                    </Badge>
                                ) : (
                                    options
                                        .filter((option) =>
                                            selectedValues.has(option.value),
                                        )
                                        .map((option) => (
                                            <Badge
                                                variant="secondary"
                                                key={option.value}
                                                className="rounded-sm px-1 font-normal"
                                            >
                                                {option.label}
                                            </Badge>
                                        ))
                                )}
                            </div>
                        </>
                    ) : null}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[200px] p-0" align="start">
                <Command>
                    <CommandInput placeholder={title} />
                    <CommandList>
                        <CommandEmpty>No results found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const isSelected = selectedValues.has(
                                    option.value,
                                );

                                return (
                                    <CommandItem
                                        key={option.value}
                                        value={option.label}
                                        onSelect={() => {
                                            const next = new Set(selectedValues);

                                            if (isSelected) {
                                                next.delete(option.value);
                                            } else {
                                                next.add(option.value);
                                            }

                                            applyFilterValues(Array.from(next));
                                        }}
                                    >
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={() => {}}
                                            tabIndex={-1}
                                            aria-label={`${option.label} filter`}
                                            className="pointer-events-none mr-2"
                                        />
                                        {option.icon ? (
                                            <option.icon className="mr-2 size-4 text-muted-foreground" />
                                        ) : null}
                                        <span className="flex-1">
                                            {option.label}
                                        </span>
                                        {facets?.get(option.value) ? (
                                            <span className="ml-auto font-mono text-xs text-muted-foreground">
                                                {facets.get(option.value)}
                                            </span>
                                        ) : null}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                        {hasSelection ? (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem
                                        value="clear-filters"
                                        onSelect={() => applyFilterValues([])}
                                        className="justify-center text-center"
                                    >
                                        Clear filters
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        ) : null}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
