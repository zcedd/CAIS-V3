'use client';

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
} from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard, StatCardSkeleton } from '@/components/user/stat-card';
import { createBeneficiaryColumns } from '@/pages/user/beneficiaries/beneficiary-columns';
import {
    BeneficiaryDataTableToolbar
    
} from '@/pages/user/beneficiaries/beneficiary-toolbar';
import type {BeneficiaryTableFilters} from '@/pages/user/beneficiaries/beneficiary-toolbar';
import {
    create as beneficiariesCreate,
    index as beneficiariesIndex,
} from '@/routes/user/beneficiaries';
import type { BreadcrumbItem } from '@/types';
import type {
    BeneficiaryRegistryStats,
    DepartmentSummary,
    PaginatedBeneficiaries,
} from '@/types/beneficiary';

const BENEFICIARIES_TABLE_PARTIAL_PROPS = [
    'beneficiaries',
    'search',
    'type',
    'city',
    'sector',
    'sort',
] as const;
const BENEFICIARIES_TABLE_SKELETON_COLUMNS = 8;
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

    if (filters.city !== '') {
        query.city = filters.city;
    }

    if (filters.sector !== '') {
        query.sector = filters.sector;
    }

    if (filters.sort !== '' && filters.sort !== 'name') {
        query.sort = filters.sort;
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
            {registryStatCards.map((card) => (
                <StatCard
                    key={card.key}
                    label={card.label}
                    value={stats[card.key].toLocaleString()}
                    description={descriptionFor(card.key)}
                    icon={card.icon}
                />
            ))}
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
                <StatCardSkeleton key={card.key} />
            ))}
        </div>
    );
}

export default function UserBeneficiariesIndex({
    beneficiaries,
    department,
    search: initialSearch,
    type: initialType,
    city: initialCity = '',
    sector: initialSector = '',
    sort: initialSort = 'name',
    city_options: cityOptions = [],
    stats,
}: {
    beneficiaries: PaginatedBeneficiaries;
    department: DepartmentSummary;
    search: string;
    type: string[];
    city?: string;
    sector?: string;
    sort?: string;
    city_options?: { value: string; label: string }[];
    stats?: BeneficiaryRegistryStats;
}) {
    const [tableState, setTableState] = useState({
        search: initialSearch,
        type: initialType,
        city: initialCity,
        sector: initialSector,
        sort: initialSort,
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
            city: initialCity,
            sector: initialSector,
            sort: initialSort,
            per_page: beneficiaries.per_page,
        }));
    }, [
        initialSearch,
        initialType,
        initialCity,
        initialSector,
        initialSort,
        beneficiaries.per_page,
    ]);

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
                city: overrides.city ?? tableStateRef.current.city,
                sector: overrides.sector ?? tableStateRef.current.sector,
                sort: overrides.sort ?? tableStateRef.current.sort,
                page: overrides.page ?? 1,
                per_page: overrides.per_page ?? tableStateRef.current.per_page,
            };

            setTableState({
                search: next.search,
                type: next.type,
                city: next.city,
                sector: next.sector,
                sort: next.sort,
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
        city: tableState.city,
        sector: tableState.sector,
        sort: tableState.sort,
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
                                        cityOptions={cityOptions}
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
