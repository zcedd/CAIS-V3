import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import { Button } from '@/components/ui/button';
import {
    buildDashboardQuery,
    DASHBOARD_PARTIAL_PROPS,
    type DashboardFilterOptions,
    type DashboardFilters,
    EMPTY_DASHBOARD_FILTERS,
    type DepartmentSummary,
} from '@/types/dashboard';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import { router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useCallback } from 'react';

type DashboardFiltersProps = {
    department: DepartmentSummary;
    filters: DashboardFilters;
    filterOptions: DashboardFilterOptions;
};

export function DashboardFiltersBar({
    department,
    filters,
    filterOptions,
}: DashboardFiltersProps) {
    const navigateWithFilters = useCallback(
        (overrides: Partial<DashboardFilters> = {}) => {
            const next = { ...filters, ...overrides };

            router.get(
                departmentDashboardIndex.url(department.slug, {
                    query: buildDashboardQuery(next),
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: [...DASHBOARD_PARTIAL_PROPS],
                },
            );
        },
        [department.slug, filters],
    );

    const hasActiveFilters = Object.values(filters).some(
        (values) => values.length > 0,
    );

    const organizationOnly =
        filters.beneficiary_type.length === 1 &&
        filters.beneficiary_type[0] === 'organization';

    return (
        <div
            className="flex flex-wrap items-center gap-2"
            id="filter-bar"
            data-tour="dashboard-filters"
        >
            <DataTableFacetedFilter
                title="Program"
                filterValue={filters.program}
                options={filterOptions.programs}
                onFilterChange={(values) =>
                    navigateWithFilters({ program: values })
                }
            />
            <DataTableFacetedFilter
                title="Beneficiary type"
                filterValue={filters.beneficiary_type}
                options={filterOptions.beneficiary_type}
                onFilterChange={(values) =>
                    navigateWithFilters({ beneficiary_type: values })
                }
            />
            <DataTableFacetedFilter
                title="Sex"
                filterValue={filters.sex}
                options={filterOptions.sex}
                onFilterChange={(values) =>
                    navigateWithFilters({ sex: values })
                }
            />
            {!organizationOnly && (
                <>
                    <DataTableFacetedFilter
                        title="PWD"
                        filterValue={filters.pwd}
                        options={filterOptions.pwd}
                        onFilterChange={(values) =>
                            navigateWithFilters({ pwd: values })
                        }
                    />
                    <DataTableFacetedFilter
                        title="4Ps"
                        filterValue={filters.four_ps}
                        options={filterOptions.four_ps}
                        onFilterChange={(values) =>
                            navigateWithFilters({ four_ps: values })
                        }
                    />
                    <DataTableFacetedFilter
                        title="Solo parent"
                        filterValue={filters.solo_parent}
                        options={filterOptions.solo_parent}
                        onFilterChange={(values) =>
                            navigateWithFilters({ solo_parent: values })
                        }
                    />
                    <DataTableFacetedFilter
                        title="Indigenous"
                        filterValue={filters.indigenous}
                        options={filterOptions.indigenous}
                        onFilterChange={(values) =>
                            navigateWithFilters({ indigenous: values })
                        }
                    />
                </>
            )}
            {hasActiveFilters && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigateWithFilters(EMPTY_DASHBOARD_FILTERS)}
                >
                    <RotateCcw className="size-4" />
                    Reset
                </Button>
            )}
        </div>
    );
}
