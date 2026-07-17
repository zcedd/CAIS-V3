'use client';

import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { ServerPagination } from '@/components/server-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    create as beneficiariesCreate,
    index as beneficiariesIndex,
    show as beneficiaryShow,
} from '@/routes/user/beneficiaries';
import type {
    BeneficiaryListRow,
    BeneficiaryRegistryStats,
    DepartmentSummary,
    PaginatedBeneficiaries,
} from '@/types/beneficiary';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, setLayoutProps, WhenVisible } from '@inertiajs/react';
import {
    Building2,
    HeartHandshake,
    MapPin,
    Phone,
    Plus,
    SearchX,
    UserRound,
    Users,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

const DEFAULT_PER_PAGE = 25;
const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100] as const;

type BeneficiaryListFilters = {
    search: string;
    type: string[];
    page: number;
    per_page: number;
};

const beneficiaryTypeOptions = [
    { label: 'Individual', value: 'individual' },
    { label: 'Organization', value: 'organization' },
] as const;

function buildBeneficiariesQuery(
    filters: BeneficiaryListFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};
    const search = filters.search.trim();

    if (search !== '') {
        query.search = search;
    }

    if (filters.type.length > 0) {
        query.type = filters.type;
    }

    if (filters.page > 1) {
        query.page = `${filters.page}`;
    }

    if (filters.per_page !== DEFAULT_PER_PAGE) {
        query.per_page = `${filters.per_page}`;
    }

    return query;
}

function typeBadge(type: BeneficiaryListRow['type']) {
    return type === 'organization' ? (
        <Badge variant="secondary">
            <Building2 aria-hidden />
            Organization
        </Badge>
    ) : (
        <Badge variant="outline">
            <UserRound aria-hidden />
            Individual
        </Badge>
    );
}

function formatRegisteredDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

const registryStatCards: {
    key: keyof BeneficiaryRegistryStats;
    label: string;
    icon: LucideIcon;
}[] = [
    { key: 'total', label: 'Total beneficiaries', icon: Users },
    { key: 'individuals', label: 'Individuals', icon: UserRound },
    { key: 'organizations', label: 'Organizations', icon: Building2 },
    { key: 'assisted', label: 'With assistance', icon: HeartHandshake },
];

function RegistryStatCards({ stats }: { stats: BeneficiaryRegistryStats }) {
    const coverage =
        stats.total > 0 ? Math.round((stats.assisted / stats.total) * 100) : null;

    const descriptionFor = (key: keyof BeneficiaryRegistryStats): string => {
        if (key === 'total') {
            return stats.new_this_month > 0
                ? `+${stats.new_this_month.toLocaleString()} registered this month`
                : 'No new registrations this month';
        }

        if (key === 'assisted' && coverage !== null) {
            return `${coverage}% of the registry has received assistance`;
        }

        if (key === 'individuals') {
            return 'Registered as individuals';
        }

        return 'Registered as organizations';
    };

    return (
        <div
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            data-tour="beneficiaries-stats"
        >
            {registryStatCards.map((card) => {
                const Icon = card.icon;

                return (
                    <div
                        key={card.key}
                        className="rounded-xl border border-border bg-card p-4"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-xs text-muted-foreground">
                                {card.label}
                            </p>
                            <Icon className="size-4 shrink-0 text-muted-foreground" />
                        </div>
                        <p className="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums">
                            {stats[card.key].toLocaleString()}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {descriptionFor(card.key)}
                        </p>
                    </div>
                );
            })}
        </div>
    );
}

function RegistryStatCardsSkeleton() {
    return (
        <div
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            data-tour="beneficiaries-stats"
            aria-busy="true"
            aria-label="Loading beneficiary statistics"
        >
            {registryStatCards.map((card) => (
                <div
                    key={card.key}
                    className="rounded-xl border border-border bg-card p-4"
                >
                    <div className="flex items-center justify-between gap-2">
                        <Skeleton className="h-3 w-24 rounded-sm" />
                        <Skeleton className="size-4 rounded-sm" />
                    </div>
                    <Skeleton className="mt-2 h-8 w-16 rounded-sm" />
                    <Skeleton className="mt-2 h-3 w-32 rounded-sm" />
                </div>
            ))}
        </div>
    );
}

export default function UserBeneficiariesIndex({
    beneficiaries,
    department,
    search: initialSearch,
    type: initialType,
    stats,
}: {
    beneficiaries: PaginatedBeneficiaries;
    department: DepartmentSummary;
    search: string;
    type: string[];
    stats?: BeneficiaryRegistryStats;
}) {
    const [searchQuery, setSearchQuery] = useState(initialSearch);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Beneficiaries',
                    href: beneficiariesIndex.url(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    useEffect(() => {
        setSearchQuery(initialSearch);
    }, [initialSearch]);

    const navigateWithFilters = useCallback(
        (overrides: Partial<BeneficiaryListFilters> = {}) => {
            const next: BeneficiaryListFilters = {
                search: overrides.search ?? searchQuery,
                type: overrides.type ?? initialType,
                // Filter changes reset to the first page unless a page is given.
                page: overrides.page ?? 1,
                per_page: overrides.per_page ?? beneficiaries.per_page,
            };

            router.get(
                beneficiariesIndex.url(department.slug, {
                    query: buildBeneficiariesQuery(next),
                }),
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['beneficiaries', 'search', 'type'],
                },
            );
        },
        [department.slug, initialType, searchQuery, beneficiaries.per_page],
    );

    const hasActiveFilters = initialSearch !== '' || initialType.length > 0;

    return (
        <>
            <Head title="Beneficiaries" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Beneficiaries
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage individual and organization beneficiaries.
                        </p>
                    </div>
                    <Button asChild data-tour="beneficiaries-create">
                        <Link href={beneficiariesCreate.url(department.slug)}>
                            <Plus className="size-4" />
                            Add beneficiary
                        </Link>
                    </Button>
                </div>

                <WhenVisible
                    data="stats"
                    buffer={200}
                    fallback={<RegistryStatCardsSkeleton />}
                >
                    {stats ? (
                        <RegistryStatCards stats={stats} />
                    ) : (
                        <RegistryStatCardsSkeleton />
                    )}
                </WhenVisible>

                <Card>
                    <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle className="text-lg">Registry</CardTitle>
                        <div
                            className="flex flex-wrap items-center gap-2"
                            data-tour="beneficiaries-filters"
                        >
                            <Input
                                value={searchQuery}
                                onChange={(event) =>
                                    setSearchQuery(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        navigateWithFilters({
                                            search: searchQuery,
                                        });
                                    }
                                }}
                                placeholder="Search by name or CAIS number..."
                                className="h-9 w-full sm:w-64"
                            />
                            <DataTableFacetedFilter
                                filterValue={initialType}
                                title="Type"
                                options={[...beneficiaryTypeOptions]}
                                onFilterChange={(values) =>
                                    navigateWithFilters({ type: values })
                                }
                            />
                            {hasActiveFilters && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setSearchQuery('');
                                        navigateWithFilters({
                                            search: '',
                                            type: [],
                                        });
                                    }}
                                >
                                    <X className="mr-1 size-4" />
                                    Reset
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {beneficiaries.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-12 text-center">
                                <SearchX className="size-8 text-muted-foreground/60" />
                                <p className="text-sm font-medium">
                                    No beneficiaries found
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {hasActiveFilters
                                        ? 'Try adjusting your search or filters.'
                                        : 'Add a beneficiary to get started.'}
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-hidden rounded-lg border border-border">
                                    <Table>
                                        <TableHeader className="bg-muted/50">
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead className="pl-4 text-xs tracking-wide text-muted-foreground uppercase">
                                                    CAIS Number
                                                </TableHead>
                                                <TableHead className="text-xs tracking-wide text-muted-foreground uppercase">
                                                    Beneficiary
                                                </TableHead>
                                                <TableHead className="text-xs tracking-wide text-muted-foreground uppercase">
                                                    Type
                                                </TableHead>
                                                <TableHead className="text-xs tracking-wide text-muted-foreground uppercase">
                                                    Contact
                                                </TableHead>
                                                <TableHead className="text-right text-xs tracking-wide text-muted-foreground uppercase">
                                                    Requests
                                                </TableHead>
                                                <TableHead className="text-right text-xs tracking-wide text-muted-foreground uppercase">
                                                    Last assisted
                                                </TableHead>
                                                <TableHead className="pr-4 text-right text-xs tracking-wide text-muted-foreground uppercase">
                                                    Registered
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {beneficiaries.data.map((row) => (
                                                <TableRow key={row.id}>
                                                    <TableCell className="py-3 pl-4">
                                                        <Link
                                                            href={beneficiaryShow.url(
                                                                {
                                                                    department:
                                                                        department.slug,
                                                                    beneficiary:
                                                                        row.id,
                                                                },
                                                            )}
                                                            className="font-medium text-primary hover:underline"
                                                        >
                                                            {row.cais_number}
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell className="py-3">
                                                        <div className="flex flex-col gap-0.5">
                                                            <span className="font-medium">
                                                                {row.name}
                                                            </span>
                                                            {row.address ? (
                                                                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                                                    <MapPin
                                                                        className="size-3 shrink-0"
                                                                        aria-hidden
                                                                    />
                                                                    {
                                                                        row.address
                                                                    }
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="py-3">
                                                        {typeBadge(row.type)}
                                                    </TableCell>
                                                    <TableCell className="py-3">
                                                        {row.contact ? (
                                                            <span className="flex items-center gap-1.5 tabular-nums">
                                                                <Phone
                                                                    className="size-3.5 shrink-0 text-muted-foreground"
                                                                    aria-hidden
                                                                />
                                                                {row.contact}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground/60">
                                                                —
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="py-3 text-right tabular-nums">
                                                        {row.assistances_count >
                                                        0 ? (
                                                            row.assistances_count.toLocaleString()
                                                        ) : (
                                                            <span className="text-muted-foreground/60">
                                                                0
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="py-3 text-right tabular-nums text-muted-foreground">
                                                        {row.last_assisted_at ? (
                                                            formatRegisteredDate(
                                                                row.last_assisted_at,
                                                            )
                                                        ) : (
                                                            <span className="text-muted-foreground/60">
                                                                Never
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="py-3 pr-4 text-right tabular-nums text-muted-foreground">
                                                        {formatRegisteredDate(
                                                            row.registered_at,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                                <ServerPagination
                                    pagination={beneficiaries}
                                    onPageChange={(page) =>
                                        navigateWithFilters({ page })
                                    }
                                    onPerPageChange={(perPage) =>
                                        navigateWithFilters({
                                            per_page: perPage,
                                            page: 1,
                                        })
                                    }
                                    perPageOptions={PER_PAGE_OPTIONS}
                                    className="mt-4"
                                />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
