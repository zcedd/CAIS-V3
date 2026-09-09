'use client';

import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { createBeneficiaryColumns } from '@/pages/user/beneficiaries/beneficiary-columns';
import {
    BeneficiaryDataTableToolbar,
    type BeneficiaryTableFilters,
} from '@/pages/user/beneficiaries/beneficiary-toolbar';
import {
    create as beneficiariesCreate,
    index as beneficiariesIndex,
} from '@/routes/user/beneficiaries';
import type {
    BeneficiaryRegistryStats,
    DepartmentSummary,
    PaginatedBeneficiaries,
} from '@/types/beneficiary';
import type { BreadcrumbItem } from '@/types';
import {
    Head,
    Link,
    router,
    setLayoutProps,
    WhenVisible,
} from '@inertiajs/react';
import {
    Building2,
    HeartHandshake,
    Plus,
    UserRound,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const BENEFICIARIES_TABLE_PARTIAL_PROPS = [
    'beneficiaries',
    'search',
    'type',
] as const;
const BENEFICIARIES_TABLE_SKELETON_COLUMNS = 7;
const DEFAULT_PER_PAGE = 25;

type BeneficiaryListFilters = BeneficiaryTableFilters & {
    page: number;
    per_page: number;
};

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

function isBeneficiariesPartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        BENEFICIARIES_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof BENEFICIARIES_TABLE_PARTIAL_PROPS)[number],
        ),
    );
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
        stats.total > 0
            ? Math.round((stats.assisted / stats.total) * 100)
            : null;

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
    const [tableState, setTableState] = useState({
        search: initialSearch,
        type: initialType,
        per_page: beneficiaries.per_page,
    });
    const [isTableReloading, setIsTableReloading] = useState(false);
    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        setTableState((previous) => ({
            ...previous,
            search: initialSearch,
            type: initialType,
            per_page: beneficiaries.per_page,
        }));
    }, [initialSearch, initialType, beneficiaries.per_page]);

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
        const removeStart = router.on('start', (event) => {
            if (isBeneficiariesPartialVisit(event.detail.visit.only)) {
                setIsTableReloading(true);
            }
        });

        const removeFinish = router.on('finish', () => {
            setIsTableReloading(false);
        });

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    const visitTable = useCallback(
        (
            overrides: Partial<
                BeneficiaryTableFilters & {
                    per_page: number;
                    page: number;
                }
            > = {},
        ) => {
            const next: BeneficiaryListFilters = {
                search: overrides.search ?? tableStateRef.current.search,
                type: overrides.type ?? tableStateRef.current.type,
                page: overrides.page ?? 1,
                per_page: overrides.per_page ?? tableStateRef.current.per_page,
            };

            setTableState({
                search: next.search,
                type: next.type,
                per_page: next.per_page,
            });

            router.cancelAll();
            router.get(
                beneficiariesIndex.url(department.slug, {
                    query: buildBeneficiariesQuery(next),
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: [...BENEFICIARIES_TABLE_PARTIAL_PROPS],
                },
            );
        },
        [department.slug],
    );

    const tableFilters: BeneficiaryTableFilters = {
        search: tableState.search,
        type: tableState.type,
    };

    const beneficiaryColumns = useMemo(
        () =>
            createBeneficiaryColumns({
                departmentSlug: department.slug,
            }),
        [department.slug],
    );

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
                            People and organizations in your registry.
                        </p>
                    </div>
                    <Button asChild data-tour="beneficiaries-create">
                        <Link href={beneficiariesCreate.url(department.slug)}>
                            <Plus className="size-4" />
                            Add beneficiary
                        </Link>
                    </Button>
                </div>

                <div data-tour="beneficiaries-kpis">
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
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Registry</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div data-tour="beneficiaries-table">
                            <DataTable
                                columns={beneficiaryColumns}
                                data={beneficiaries.data}
                                emptyMessage="No beneficiaries found."
                                manualPagination
                                manualFiltering
                                serverPagination={beneficiaries}
                                partialReloadOnly={[
                                    ...BENEFICIARIES_TABLE_PARTIAL_PROPS,
                                ]}
                                isLoading={isTableReloading}
                                loadingFallback={
                                    <DataTableSkeleton
                                        columnCount={
                                            BENEFICIARIES_TABLE_SKELETON_COLUMNS
                                        }
                                        rowCount={tableState.per_page}
                                    />
                                }
                                onPerPageChange={(nextPerPage) => {
                                    visitTable({
                                        per_page: nextPerPage,
                                        page: 1,
                                    });
                                }}
                                onPageChange={(page) => {
                                    visitTable({ page });
                                }}
                                toolbar={(table, columnVisibility) => (
                                    <BeneficiaryDataTableToolbar
                                        table={table}
                                        columnVisibility={columnVisibility}
                                        filters={tableFilters}
                                        onFiltersChange={visitTable}
                                    />
                                )}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
