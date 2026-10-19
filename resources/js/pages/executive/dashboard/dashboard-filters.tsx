import { router } from '@inertiajs/react';
import { useCallback } from 'react';
import { DashboardFiltersDrawer } from '@/pages/user/dashboard/dashboard-filters-drawer';
import type { DashboardDrawerField } from '@/pages/user/dashboard/dashboard-filters-drawer';
import { dashboard as administratorDashboard } from '@/routes/administrator';
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

type ExecutiveDashboardFilters = DashboardFilters & {
    department: string[];
};

type ExecutiveFilterOptions = DashboardFilterOptions & {
    departments: DashboardFilterOption[];
};

type DashboardFiltersProps = {
    filters: ExecutiveDashboardFilters;
    filterOptions: ExecutiveFilterOptions;
    audience?: 'executive' | 'administrator';
};

function buildExecutiveDashboardQuery(
    filters: ExecutiveDashboardFilters,
): Record<string, string | string[]> {
    const query = buildDashboardQuery(filters);

    if (filters.department.length > 0) {
        query.department = filters.department;
    }

    return query;
}

const PERIOD_FIELDS: DashboardDrawerField<keyof ExecutiveDashboardFilters>[] = [
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

const DEMOGRAPHIC_FIELDS: DashboardDrawerField<
    keyof ExecutiveDashboardFilters
>[] = [
    { key: 'sex', label: 'Sex', optionsKey: 'sex' },
    { key: 'pwd', label: 'PWD', optionsKey: 'pwd' },
    { key: 'four_ps', label: '4Ps', optionsKey: 'four_ps' },
    { key: 'solo_parent', label: 'Solo parent', optionsKey: 'solo_parent' },
    { key: 'indigenous', label: 'Indigenous', optionsKey: 'indigenous' },
];

export function DashboardFiltersButton({
    filters,
    filterOptions,
    audience = 'executive',
}: DashboardFiltersProps) {
    const dashboard =
        audience === 'administrator' ? administratorDashboard : executiveDashboard;

    const navigateWithFilters = useCallback(
        (overrides: Partial<ExecutiveDashboardFilters> = {}) => {
            const next = { ...filters, ...overrides };

            router.get(
                dashboard.url({
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
        [dashboard, filters],
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
            onReset={() =>
                navigateWithFilters({
                    ...getDefaultDashboardFilters(),
                    department: [],
                })
            }
            hasActiveFilters={
                hasActiveDashboardFilters(filters) ||
                filters.department.length > 0
            }
        />
    );
}
