export type DashboardFilterOption = {
    label: string;
    value: string;
};

export type DashboardFilters = {
    program: string[];
    beneficiary_type: string[];
    sex: string[];
    pwd: string[];
    four_ps: string[];
    solo_parent: string[];
    indigenous: string[];
};

export type DashboardFilterOptions = {
    programs: DashboardFilterOption[];
    beneficiary_type: DashboardFilterOption[];
    sex: DashboardFilterOption[];
    pwd: DashboardFilterOption[];
    four_ps: DashboardFilterOption[];
    solo_parent: DashboardFilterOption[];
    indigenous: DashboardFilterOption[];
};

export type DashboardSummary = {
    total_requests: number;
    delivered_requests: number;
    total_delivered_items: number;
    active_programs: number;
};

export type RequestStatusChartPoint = {
    status: string;
    count: number;
};

export type DeliveredItemsChartPoint = {
    item: string;
    unit: string;
    quantity: number;
};

export type DashboardProgramRow = {
    id: number;
    name: string;
    type: 'individual' | 'organization';
    status: 'open' | 'closed';
    total_requests: number;
    delivered: number;
    in_progress: number;
};

export type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

export const DASHBOARD_PARTIAL_PROPS = [
    'summary',
    'requestStatusChart',
    'deliveredItemsChart',
    'programsTable',
    'filters',
    'filterOptions',
] as const;

export function buildDashboardQuery(
    filters: DashboardFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};

    if (filters.program.length > 0) {
        query.program = filters.program;
    }

    if (filters.beneficiary_type.length > 0) {
        query.beneficiary_type = filters.beneficiary_type;
    }

    if (filters.sex.length > 0) {
        query.sex = filters.sex;
    }

    if (filters.pwd.length > 0) {
        query.pwd = filters.pwd;
    }

    if (filters.four_ps.length > 0) {
        query.four_ps = filters.four_ps;
    }

    if (filters.solo_parent.length > 0) {
        query.solo_parent = filters.solo_parent;
    }

    if (filters.indigenous.length > 0) {
        query.indigenous = filters.indigenous;
    }

    return query;
}

export const EMPTY_DASHBOARD_FILTERS: DashboardFilters = {
    program: [],
    beneficiary_type: [],
    sex: [],
    pwd: [],
    four_ps: [],
    solo_parent: [],
    indigenous: [],
};
