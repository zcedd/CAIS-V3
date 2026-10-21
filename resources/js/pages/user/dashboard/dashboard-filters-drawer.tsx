import { ChevronDown, RotateCcw, SlidersHorizontal, X } from 'lucide-react';
import { useMemo } from 'react';
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
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { DashboardFilterOption } from '@/types/dashboard';

export type DashboardDrawerField<TKey extends string> = {
    key: TKey;
    label: string;
    optionsKey: string;
};

function optionLabel(options: DashboardFilterOption[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

function FilterChip({
    fieldLabel,
    label,
    onRemove,
}: {
    fieldLabel: string;
    label: string;
    onRemove: () => void;
}) {
    return (
        <Badge
            variant="secondary"
            className="gap-1 rounded-md py-0.5 pr-1 pl-2 font-normal"
        >
            <span className="text-muted-foreground">{fieldLabel}:</span>
            {label}
            <button
                type="button"
                className="rounded-md p-0.5 hover:bg-muted-foreground/15"
                aria-label={`Remove ${fieldLabel} ${label}`}
                onClick={onRemove}
            >
                <X className="size-3" />
            </button>
        </Badge>
    );
}

function FilterSelect({
    label,
    options,
    values,
    onChange,
}: {
    label: string;
    options: DashboardFilterOption[];
    values: string[];
    onChange: (values: string[]) => void;
}) {
    const selected = useMemo(() => new Set(values), [values]);
    const hasSelection = selected.size > 0;
    const summary = !hasSelection
        ? 'All'
        : selected.size === 1
          ? optionLabel(options, values[0])
          : `${selected.size} selected`;

    return (
        <div className="flex min-w-0 flex-col gap-1">
            <span className="text-xs text-muted-foreground">{label}</span>
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        aria-label={`${label}: ${summary}`}
                        className={
                            hasSelection
                                ? 'w-full justify-between gap-1.5 border-primary/40 bg-primary/5 font-normal text-foreground'
                                : 'w-full justify-between gap-1.5 border-dashed font-normal'
                        }
                    >
                        <span className="truncate text-left font-medium">
                            {summary}
                        </span>
                        <ChevronDown className="size-3.5 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-60 p-0" align="start">
                    <Command>
                        <CommandInput
                            placeholder={`Search ${label.toLowerCase()}…`}
                        />
                        <CommandList>
                            <CommandEmpty>No results found.</CommandEmpty>
                            <CommandGroup>
                                {options.map((option) => {
                                    const isSelected = selected.has(
                                        option.value,
                                    );

                                    return (
                                        <CommandItem
                                            key={option.value}
                                            value={option.label}
                                            onSelect={() => {
                                                const next = new Set(selected);

                                                if (isSelected) {
                                                    next.delete(option.value);
                                                } else {
                                                    next.add(option.value);
                                                }

                                                onChange(Array.from(next));
                                            }}
                                        >
                                            <Checkbox
                                                checked={isSelected}
                                                onCheckedChange={() => {}}
                                                tabIndex={-1}
                                                aria-label={option.label}
                                                className="pointer-events-none mr-2"
                                            />
                                            <span className="flex-1 truncate">
                                                {option.label}
                                            </span>
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
                                            onSelect={() => onChange([])}
                                            className="justify-center text-center"
                                        >
                                            Clear {label.toLowerCase()}
                                        </CommandItem>
                                    </CommandGroup>
                                </>
                            ) : null}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}

export function DashboardFiltersDrawer<TKey extends string>({
    fields,
    demographicFields,
    filters,
    options,
    showDemographics,
    onChange,
    onReset,
    hasActiveFilters,
}: {
    fields: DashboardDrawerField<TKey>[];
    demographicFields: DashboardDrawerField<TKey>[];
    filters: Record<TKey, string[]>;
    options: { [key: string]: DashboardFilterOption[] | undefined };
    showDemographics: boolean;
    onChange: (key: TKey, values: string[]) => void;
    onReset: () => void;
    hasActiveFilters: boolean;
}) {
    const activeChips = useMemo(() => {
        const visibleFields = showDemographics
            ? [...fields, ...demographicFields]
            : fields;
        const chips: {
            key: TKey;
            value: string;
            label: string;
            fieldLabel: string;
        }[] = [];

        for (const field of visibleFields) {
            const values = parseFacetedFilterValue(filters[field.key]);
            const fieldOptions = options[field.optionsKey] ?? [];

            for (const value of values) {
                chips.push({
                    key: field.key,
                    value,
                    label: optionLabel(fieldOptions, value),
                    fieldLabel: field.label,
                });
            }
        }

        return chips;
    }, [demographicFields, fields, filters, options, showDemographics]);

    return (
        <div className="flex flex-wrap items-center justify-end gap-2">
            {activeChips.map((chip) => (
                <FilterChip
                    key={`${chip.key}-${chip.value}`}
                    fieldLabel={chip.fieldLabel}
                    label={chip.label}
                    onRemove={() =>
                        onChange(
                            chip.key,
                            filters[chip.key].filter(
                                (entry) => entry !== chip.value,
                            ),
                        )
                    }
                />
            ))}
            {hasActiveFilters ? (
                <Button variant="ghost" onClick={onReset}>
                    <RotateCcw data-icon="inline-start" />
                    Reset
                </Button>
            ) : null}
            <Drawer direction="right">
                <DrawerTrigger asChild>
                    <Button variant="outline" data-tour="dashboard-filters">
                        <SlidersHorizontal data-icon="inline-start" />
                        Filters
                        {activeChips.length > 0 ? (
                            <Badge
                                variant="secondary"
                                className="rounded-md px-1.5 font-normal"
                            >
                                {activeChips.length}
                            </Badge>
                        ) : null}
                    </Button>
                </DrawerTrigger>
                <DrawerContent>
                    <DrawerHeader>
                        <DrawerTitle>Filters</DrawerTitle>
                        <DrawerDescription>
                            Choose what the dashboard numbers cover.
                        </DrawerDescription>
                    </DrawerHeader>
                    <div className="flex flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4">
                        {activeChips.length > 0 ? (
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Applied
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {activeChips.map((chip) => (
                                        <FilterChip
                                            key={`${chip.key}-${chip.value}`}
                                            fieldLabel={chip.fieldLabel}
                                            label={chip.label}
                                            onRemove={() =>
                                                onChange(
                                                    chip.key,
                                                    filters[chip.key].filter(
                                                        (entry) =>
                                                            entry !==
                                                            chip.value,
                                                    ),
                                                )
                                            }
                                        />
                                    ))}
                                </div>
                            </div>
                        ) : null}
                        <div className="flex flex-col gap-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                Period & program
                            </p>
                            {fields.map((field) => (
                                <FilterSelect
                                    key={field.key}
                                    label={field.label}
                                    options={options[field.optionsKey] ?? []}
                                    values={filters[field.key]}
                                    onChange={(values) =>
                                        onChange(field.key, values)
                                    }
                                />
                            ))}
                        </div>
                        {showDemographics ? (
                            <div className="flex flex-col gap-3">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Demographics
                                </p>
                                {demographicFields.map((field) => (
                                    <FilterSelect
                                        key={field.key}
                                        label={field.label}
                                        options={
                                            options[field.optionsKey] ?? []
                                        }
                                        values={filters[field.key]}
                                        onChange={(values) =>
                                            onChange(field.key, values)
                                        }
                                    />
                                ))}
                            </div>
                        ) : null}
                    </div>
                    <DrawerFooter>
                        <Button
                            variant="outline"
                            disabled={!hasActiveFilters}
                            onClick={onReset}
                        >
                            <RotateCcw data-icon="inline-start" />
                            Reset all
                        </Button>
                        <DrawerClose asChild>
                            <Button type="button">Done</Button>
                        </DrawerClose>
                    </DrawerFooter>
                </DrawerContent>
            </Drawer>
        </div>
    );
}
