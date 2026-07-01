'use client';

import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    create as beneficiariesCreate,
    index as beneficiariesIndex,
    show as beneficiaryShow,
} from '@/routes/user/beneficiaries';
import type {
    BeneficiaryListRow,
    DepartmentSummary,
    PaginatedBeneficiaries,
} from '@/types/beneficiary';
import type { BreadcrumbItem } from '@/types';
import { Head, InfiniteScroll, Link, router, setLayoutProps } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

type BeneficiaryListFilters = {
    search: string;
    type: string[];
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

    return query;
}

function typeBadge(type: BeneficiaryListRow['type']) {
    return type === 'organization' ? (
        <Badge variant="secondary">Organization</Badge>
    ) : (
        <Badge variant="outline">Individual</Badge>
    );
}

function BeneficiaryTableLoadingRows() {
    return (
        <>
            {Array.from({ length: 3 }).map((_, index) => (
                <tr key={index} className="border-b last:border-0">
                    <td className="py-3 pr-4" colSpan={3}>
                        <div className="h-4 animate-pulse rounded bg-muted" />
                    </td>
                </tr>
            ))}
        </>
    );
}

export default function UserBeneficiariesIndex({
    beneficiaries,
    department,
    search: initialSearch,
    type: initialType,
}: {
    beneficiaries: PaginatedBeneficiaries;
    department: DepartmentSummary;
    search: string;
    type: string[];
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
                    reset: ['beneficiaries'],
                },
            );
        },
        [department.slug, initialType, searchQuery],
    );

    const hasMorePages = beneficiaries.current_page < beneficiaries.last_page;
    const loadedCount = beneficiaries.data.length;

    return (
        <>
            <Head title="Beneficiaries" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
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
                            {(initialSearch !== '' || initialType.length > 0) && (
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
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No beneficiaries found.
                            </p>
                        ) : (
                            <>
                                <InfiniteScroll
                                    data="beneficiaries"
                                    onlyNext
                                    buffer={200}
                                    itemsElement="#beneficiaries-table-body"
                                    next={({ loading }) =>
                                        loading ? (
                                            <table className="w-full text-sm">
                                                <tbody>
                                                    <BeneficiaryTableLoadingRows />
                                                </tbody>
                                            </table>
                                        ) : null
                                    }
                                >
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-left text-muted-foreground">
                                                    <th className="pb-3 pr-4 font-medium">
                                                        CAIS Number
                                                    </th>
                                                    <th className="pb-3 pr-4 font-medium">
                                                        Name
                                                    </th>
                                                    <th className="pb-3 font-medium">
                                                        Type
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="beneficiaries-table-body">
                                                {beneficiaries.data.map(
                                                    (row) => (
                                                        <tr
                                                            key={row.id}
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="py-3 pr-4">
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
                                                                    {
                                                                        row.cais_number
                                                                    }
                                                                </Link>
                                                            </td>
                                                            <td className="py-3 pr-4">
                                                                {row.name}
                                                            </td>
                                                            <td className="py-3">
                                                                {typeBadge(
                                                                    row.type,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </InfiniteScroll>
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Showing {loadedCount} of{' '}
                                    {beneficiaries.total} beneficiaries
                                    {hasMorePages
                                        ? ' — scroll for more'
                                        : null}
                                </p>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
