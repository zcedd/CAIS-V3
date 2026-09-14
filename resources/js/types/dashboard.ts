export type DashboardFilterOption = {
    label: string;
    value: string;
};

export type DashboardFilters = {
    year: string[];
    quarter: string[];
    program: string[];
    beneficiary_type: string[];
    sex: string[];
    pwd: string[];
    four_ps: string[];
    solo_parent: string[];
    indigenous: string[];
};

export type DashboardFilterOptions = {
    year: DashboardFilterOption[];
    quarter: DashboardFilterOption[];
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
    in_progress_requests: number;
    denied_requests: number;
    unique_beneficiaries: number;
    closed_programs: number;
    avg_days_to_deliver: number | null;
    avg_days_to_verify: number | null;
    repeat_beneficiaries: number;
    one_time_beneficiaries: number;
    avg_requests_per_beneficiary: number;
};

export type RequestStatusChartPoint = {
    status: string;
    count: number;
};

export type DeliveredItemsChartPoint = {
    item: string;
    unit: string;
    count: number;
    quantity: number;
};

export type UnspscReleasedChartPoint = {
    segment: string;
    code: string;
    quantity: number;
};

export type BeneficiaryTypeChartPoint = {
    type: string;
    label: string;
    count: number;
};

export type DemographicCount = {
    label: string;
    count: number;
};

export type DashboardDemographics = {
    sex: DemographicCount[];
    age: DemographicCount[];
    civil_status: DemographicCount[];
    pwd: DemographicCount[];
    four_ps: DemographicCount[];
    solo_parent: DemographicCount[];
    indigenous: DemographicCount[];
};

export type RequestsTrendPoint = {
    date: string;
    count: number;
};

export type DashboardInsights = {
    backlog_aging: DemographicCount[];
    pending_items: number;
    received_items: number;
    distinct_barangays: number;
};

export type TopBarangayPoint = {
    barangay: string;
    count: number;
};

export type DashboardProgramRow = {
    id: number;
    name: string;
    type: 'individual' | 'organization';
    status: 'open' | 'closed';
    kind?: string;
    total_requests: number;
    delivered: number;
    in_progress: number;
    denied: number;
    delivery_rate: number;
    batches?: DashboardProgramBatchRow[];
};

export type DashboardProgramBatchRow = {
    id: number;
    name: string;
    status: 'open' | 'closed';
    total_requests: number;
    delivered: number;
    in_progress: number;
    denied: number;
    delivery_rate: number;
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
    'unspscReleasedChart',
    'beneficiaryTypeChart',
    'demographics',
    'requestsTrend',
    'insights',
    'topBarangays',
    'modeOfRequestChart',
    'programsTable',
    'filters',
] as const;

export const DASHBOARD_CHART_DEFER_PROPS = [
    'requestStatusChart',
    'deliveredItemsChart',
    'unspscReleasedChart',
    'beneficiaryTypeChart',
    'requestsTrend',
] as const;

export const DASHBOARD_INSIGHT_DEFER_PROPS = [
    'insights',
    'topBarangays',
    'modeOfRequestChart',
] as const;

export function buildDashboardQuery(
    filters: DashboardFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};

    if (filters.year.length > 0) {
        query.year = filters.year;
    }

    if (filters.quarter.length > 0) {
        query.quarter = filters.quarter;
    }

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

export function getDefaultDashboardFilters(): DashboardFilters {
    return {
        year: [String(new Date().getFullYear())],
        quarter: [],
        program: [],
        beneficiary_type: [],
        sex: [],
        pwd: [],
        four_ps: [],
        solo_parent: [],
        indigenous: [],
    };
}

export function hasActiveDashboardFilters(filters: DashboardFilters): boolean {
    const defaults = getDefaultDashboardFilters();

    return (Object.keys(defaults) as (keyof DashboardFilters)[]).some(
        (key) => {
            const current = [...filters[key]].sort();
            const defaultValues = [...defaults[key]].sort();

            return (
                current.length !== defaultValues.length ||
                current.some((value, index) => value !== defaultValues[index])
            );
        },
    );
}

export type TrendPeriod = 'day' | 'week' | 'month';

export function aggregateRequestsTrend(
    points: RequestsTrendPoint[],
    period: TrendPeriod,
): { label: string; count: number }[] {
    if (period === 'day') {
        return points.map((point) => ({
            label: point.date,
            count: point.count,
        }));
    }

    const buckets = new Map<string, number>();

    for (const point of points) {
        const date = new Date(`${point.date}T00:00:00`);

        if (Number.isNaN(date.getTime())) {
            continue;
        }

        let key: string;

        if (period === 'week') {
            const day = date.getDay();
            const diff = day === 0 ? -6 : 1 - day;
            const monday = new Date(date);
            monday.setDate(date.getDate() + diff);
            key = monday.toISOString().slice(0, 10);
        } else {
            key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        }

        buckets.set(key, (buckets.get(key) ?? 0) + point.count);
    }

    return [...buckets.entries()]
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([label, count]) => ({ label, count }));
}
