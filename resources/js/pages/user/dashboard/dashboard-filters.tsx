import { router } from '@inertiajs/react';
import { useCallback } from 'react';
import { DashboardFiltersDrawer } from '@/pages/user/dashboard/dashboard-filters-drawer';
import type { DashboardDrawerField } from '@/pages/user/dashboard/dashboard-filters-drawer';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import {
    buildDashboardQuery,
    DASHBOARD_PARTIAL_PROPS,
    getDefaultDashboardFilters,
    hasActiveDashboardFilters,
} from '@/types/dashboard';
import type {
    DashboardFilterOptions,
    DashboardFilters,
    DepartmentSummary,
} from '@/types/dashboard';

type DashboardFiltersProps = {
    department: DepartmentSummary;
    filters: DashboardFilters;
    filterOptions: DashboardFilterOptions;
};

const PERIOD_FIELDS: DashboardDrawerField<keyof DashboardFilters>[] = [
    { key: 'year', label: 'Year', optionsKey: 'year' },
    { key: 'quarter', label: 'Quarter', optionsKey: 'quarter' },
    { key: 'program', label: 'Program', optionsKey: 'programs' },
    {
        key: 'beneficiary_type',
        label: 'Beneficiary',
        optionsKey: 'beneficiary_type',
    },
];

const DEMOGRAPHIC_FIELDS: DashboardDrawerField<keyof DashboardFilters>[] = [
    { key: 'sex', label: 'Sex', optionsKey: 'sex' },
    { key: 'pwd', label: 'PWD', optionsKey: 'pwd' },
    { key: 'four_ps', label: '4Ps', optionsKey: 'four_ps' },
    { key: 'solo_parent', label: 'Solo parent', optionsKey: 'solo_parent' },
    { key: 'indigenous', label: 'Indigenous', optionsKey: 'indigenous' },
];

export function DashboardFiltersButton({
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

    const organizationOnly =
        filters.beneficiary_type.length === 1 &&
        filters.beneficiary_type[0] === 'organization';

    return (
        <DashboardFiltersDrawer
            fields={PERIOD_FIELDS}
            demographicFields={DEMOGRAPHIC_FIELDS}
            filters={filters}
            options={filterOptions}
            showDemographics={!organizationOnly}
            onChange={(key, values) => navigateWithFilters({ [key]: values })}
            onReset={() => navigateWithFilters(getDefaultDashboardFilters())}
            hasActiveFilters={hasActiveDashboardFilters(filters)}
        />
    );
}
