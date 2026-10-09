import { router } from '@inertiajs/react';
import {
    ChevronDown,
    Filter,
    RotateCcw,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { parseFacetedFilterValue } from '@/components/data-table/parse-faceted-filter-value';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { dashboard as executiveDashboard } from '@/routes/executive';
import {
    buildDashboardQuery,
    DASHBOARD_PARTIAL_PROPS,
    getDefaultDashboardFilters,
    hasActiveDashboardFilters,
} from '@/types/dashboard';
import type {
    DashboardFilterOption,
    DashboardFilterOptions,
    DashboardFilters,
} from '@/types/dashboard';

function buildExecutiveDashboardQuery(
    filters: ExecutiveDashboardFilters,
): Record<string, string | string[]> {
    const query = buildDashboardQuery(filters);

    if (filters.department.length > 0) {
        query.department = filters.department;
    }

    return query;
}

type ExecutiveDashboardFilters = DashboardFilters & {
    department: string[];
};

type ExecutiveFilterOptions = DashboardFilterOptions & {
    departments: DashboardFilterOption[];
};

type DashboardFiltersProps = {
    filters: ExecutiveDashboardFilters;
    filterOptions: ExecutiveFilterOptions;
};

type FilterKey = keyof ExecutiveDashboardFilters;

type FilterFieldConfig = {
    key: FilterKey;
    label: string;
    optionsKey: keyof ExecutiveFilterOptions;
};

const PERIOD_FIELDS: FilterFieldConfig[] = [
    { key: 'department', label: 'Department', optionsKey: 'departments' },
    { key: 'year', label: 'Year', optionsKey: 'year' },
    { key: 'quarter', label: 'Quarter', optionsKey: 'quarter' },
    { key: 'program', label: 'Program', optionsKey: 'programs' },
    {
        key: 'beneficiary_type',
        label: 'Beneficiary',
        optionsKey: 'beneficiary_type',
    },
];

const DEMOGRAPHIC_FIELDS: FilterFieldConfig[] = [
    { key: 'sex', label: 'Sex', optionsKey: 'sex' },
    { key: 'pwd', label: 'PWD', optionsKey: 'pwd' },
    { key: 'four_ps', label: '4Ps', optionsKey: 'four_ps' },
    { key: 'solo_parent', label: 'Solo parent', optionsKey: 'solo_parent' },
    { key: 'indigenous', label: 'Indigenous', optionsKey: 'indigenous' },
];

function optionLabel(options: DashboardFilterOption[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

function FilterSelect({
    label,
    options,
    values,
    onChange,
    className,
}: {
    label: string;
    options: DashboardFilterOption[];
    values: string[];
    onChange: (values: string[]) => void;
    className?: string;
}) {
    const selected = useMemo(() => new Set(values), [values]);
    const hasSelection = selected.size > 0;

    const summary = !hasSelection
        ? 'All'
        : selected.size === 1
          ? optionLabel(options, values[0])
          : `${selected.size} selected`;

    return (
        <div className={cn('min-w-0 space-y-1.5', className)}>
            <label className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </label>
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        className={cn(
                            'h-10 w-full justify-between gap-2 px-3 font-normal',
                            hasSelection &&
                                'border-primary/40 bg-primary/5 text-foreground',
                        )}
                    >
                        <span className="truncate text-left">{summary}</span>
                        <ChevronDown className="size-4 shrink-0 opacity-50" />
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

export function DashboardFiltersBar({
    filters,
    filterOptions,
}: DashboardFiltersProps) {
    const [panelOpen, setPanelOpen] = useState(true);
    const [detailsOpen, setDetailsOpen] = useState(() =>
        DEMOGRAPHIC_FIELDS.some((field) => filters[field.key].length > 0),
    );

    const navigateWithFilters = useCallback(
        (overrides: Partial<DashboardFilters> = {}) => {
            const next = { ...filters, ...overrides };

            router.get(
                executiveDashboard.url({
                    query: buildExecutiveDashboardQuery(next),
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: [...DASHBOARD_PARTIAL_PROPS],
                },
            );
        },
        [filters],
    );

    const hasActiveFilters =
        hasActiveDashboardFilters(filters) || filters.department.length > 0;

    const organizationOnly =
        filters.beneficiary_type.length === 1 &&
        filters.beneficiary_type[0] === 'organization';

    const showDemographics = !organizationOnly;

    const activeChips = useMemo(() => {
        const fields = showDemographics
            ? [...PERIOD_FIELDS, ...DEMOGRAPHIC_FIELDS]
            : PERIOD_FIELDS;
        const chips: {
            key: FilterKey;
            value: string;
            label: string;
            fieldLabel: string;
        }[] = [];

        for (const field of fields) {
            const values = parseFacetedFilterValue(filters[field.key]);
            const options = filterOptions[field.optionsKey];

            for (const value of values) {
                chips.push({
                    key: field.key,
                    value,
                    label: optionLabel(options, value),
                    fieldLabel: field.label,
                });
            }
        }

        return chips;
    }, [filterOptions, filters, showDemographics]);

    const demographicActiveCount = DEMOGRAPHIC_FIELDS.reduce(
        (count, field) => count + filters[field.key].length,
        0,
    );

    const clearChip = (key: FilterKey, value: string) => {
        navigateWithFilters({
            [key]: filters[key].filter((entry) => entry !== value),
        });
    };

    return (
        <Collapsible
            open={panelOpen}
            onOpenChange={setPanelOpen}
            className="overflow-hidden rounded-2xl bg-card ring-1 ring-foreground/10"
            id="filter-bar"
            data-tour="dashboard-filters"
        >
            <div
                className={cn(
                    'flex flex-wrap items-center justify-between gap-3 px-4 py-3',
                    panelOpen && 'border-b border-border/60',
                )}
            >
                <CollapsibleTrigger asChild>
                    <button
                        type="button"
                        className="flex min-w-0 flex-1 items-center gap-2 rounded-lg text-left outline-none hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <Filter className="size-4 text-muted-foreground" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="text-sm font-medium">Filters</p>
                                {activeChips.length > 0 && (
                                    <Badge
                                        variant="secondary"
                                        className="rounded-md px-1.5 font-normal"
                                    >
                                        {activeChips.length} active
                                    </Badge>
                                )}
                            </div>
                            <p className="truncate text-xs text-muted-foreground">
                                {panelOpen
                                    ? 'Change what the numbers cover'
                                    : activeChips.length > 0
                                      ? activeChips
                                            .slice(0, 3)
                                            .map(
                                                (chip) =>
                                                    `${chip.fieldLabel}: ${chip.label}`,
                                            )
                                            .join(' · ') +
                                        (activeChips.length > 3
                                            ? ` · +${activeChips.length - 3} more`
                                            : '')
                                      : 'Click to expand filter options'}
                            </p>
                        </div>
                        <ChevronDown
                            className={cn(
                                'size-4 shrink-0 text-muted-foreground transition-transform',
                                panelOpen && 'rotate-180',
                            )}
                        />
                    </button>
                </CollapsibleTrigger>

                {hasActiveFilters && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="shrink-0"
                        onClick={() =>
                            navigateWithFilters({
                                ...getDefaultDashboardFilters(),
                                department: [],
                            })
                        }
                    >
                        <RotateCcw className="size-4" />
                        Reset all
                    </Button>
                )}
            </div>

            <CollapsibleContent>
                <div className="space-y-4 p-4">
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {PERIOD_FIELDS.map((field) => (
                            <FilterSelect
                                key={field.key}
                                label={field.label}
                                options={filterOptions[field.optionsKey]}
                                values={filters[field.key]}
                                onChange={(values) =>
                                    navigateWithFilters({ [field.key]: values })
                                }
                            />
                        ))}
                    </div>

                    {showDemographics && (
                        <Collapsible
                            open={detailsOpen}
                            onOpenChange={setDetailsOpen}
                        >
                            <div className="flex items-center justify-between gap-3">
                                <CollapsibleTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="-ml-2 text-muted-foreground"
                                    >
                                        <SlidersHorizontal className="size-4" />
                                        Beneficiary details
                                        {demographicActiveCount > 0 && (
                                            <Badge
                                                variant="secondary"
                                                className="rounded-md px-1.5 font-normal"
                                            >
                                                {demographicActiveCount}
                                            </Badge>
                                        )}
                                        <ChevronDown
                                            className={cn(
                                                'size-4 transition-transform',
                                                detailsOpen && 'rotate-180',
                                            )}
                                        />
                                    </Button>
                                </CollapsibleTrigger>
                            </div>
                            <CollapsibleContent className="pt-3">
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                    {DEMOGRAPHIC_FIELDS.map((field) => (
                                        <FilterSelect
                                            key={field.key}
                                            label={field.label}
                                            options={
                                                filterOptions[field.optionsKey]
                                            }
                                            values={filters[field.key]}
                                            onChange={(values) =>
                                                navigateWithFilters({
                                                    [field.key]: values,
                                                })
                                            }
                                        />
                                    ))}
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    )}

                    {activeChips.length > 0 && (
                        <>
                            <Separator />
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Active
                                </span>
                                {activeChips.map((chip) => (
                                    <Badge
                                        key={`${chip.key}-${chip.value}`}
                                        variant="secondary"
                                        className="gap-1 rounded-lg py-1 pr-1 pl-2 font-normal"
                                    >
                                        <span className="text-muted-foreground">
                                            {chip.fieldLabel}:
                                        </span>
                                        {chip.label}
                                        <button
                                            type="button"
                                            className="rounded-md p-0.5 hover:bg-muted-foreground/15"
                                            aria-label={`Remove ${chip.fieldLabel} ${chip.label}`}
                                            onClick={() =>
                                                clearChip(chip.key, chip.value)
                                            }
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </Badge>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
