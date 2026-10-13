import { Skeleton } from '@/components/ui/skeleton';
import {
    DashboardChartCard,
    DashboardChartCardSkeleton,
    DonutBreakdownChart,
    PopulationPyramidChart,
    RankedBarChart,
    ShareBarList,
} from '@/pages/user/dashboard/dashboard-charts';
import type { ShareBarItem } from '@/pages/user/dashboard/dashboard-charts';
import type {
    BeneficiaryTypeChartPoint,
    DashboardDemographics,
    DemographicCount,
} from '@/types/dashboard';

type BeneficiaryBreakdownProps = {
    data: BeneficiaryTypeChartPoint[];
};

export function BeneficiaryBreakdown({ data }: BeneficiaryBreakdownProps) {
    const points: DemographicCount[] = data.map((row) => ({
        label: row.label,
        count: row.count,
    }));

    return (
        <DashboardChartCard
            title="Requests by beneficiary type"
            description="Requests from individuals and organizations"
        >
            <DonutBreakdownChart data={points} valueLabel="Requests" />
        </DashboardChartCard>
    );
}

type DemographicsPanelProps = {
    demographics: DashboardDemographics;
};

export function DemographicsPanel({ demographics }: DemographicsPanelProps) {
    const requests = sumCounts(demographics.sex);
    const female = countForLabel(demographics.sex, 'Female');
    const seniors = countForLabel(demographics.age, '60+');
    const ageTotal = sumCounts(demographics.age);
    const topStatus = topCount(demographics.civil_status);
    const statusTotal = sumCounts(demographics.civil_status);

    return (
        <div className="space-y-4" data-tour="dashboard-demographics">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <HeadlineTile
                    label="Individual requests"
                    value={requests.toLocaleString()}
                />
                <HeadlineTile
                    label="Female"
                    value={percentLabel(female, requests)}
                    detail={`${female.toLocaleString()} requests`}
                />
                <HeadlineTile
                    label="Seniors (60+)"
                    value={percentLabel(seniors, ageTotal)}
                    detail={`${seniors.toLocaleString()} requests`}
                />
                <HeadlineTile
                    label="Top civil status"
                    value={topStatus?.label ?? '—'}
                    detail={
                        topStatus
                            ? `${topStatus.count.toLocaleString()} (${percentLabel(topStatus.count, statusTotal)})`
                            : 'No civil status recorded'
                    }
                />
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-3">
                <DashboardChartCard
                    title="Age by sex"
                    description="Individual requests, male on the left and female on the right"
                    className="lg:col-span-2"
                >
                    <PopulationPyramidChart data={demographics.age_by_sex} />
                </DashboardChartCard>
                <DashboardChartCard
                    title="Sex"
                    description="Individual requests by sex"
                >
                    <DonutBreakdownChart
                        data={demographics.sex}
                        valueLabel="Requests"
                        showShare
                    />
                </DashboardChartCard>
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-2">
                <DashboardChartCard
                    title="Civil status"
                    description="Individual requests by civil status"
                >
                    <RankedBarChart
                        data={demographics.civil_status}
                        valueLabel="Requests"
                        showShare
                    />
                </DashboardChartCard>
                <DashboardChartCard
                    title="Sector inclusion"
                    description="Share of individual requests in each sector"
                >
                    <ShareBarList items={sectorShares(demographics)} />
                </DashboardChartCard>
            </div>
        </div>
    );
}

function HeadlineTile({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="rounded-lg border border-border bg-card px-4 py-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            {detail ? (
                <p className="text-xs text-muted-foreground">{detail}</p>
            ) : null}
        </div>
    );
}

function sectorShares(demographics: DashboardDemographics): ShareBarItem[] {
    return [
        { label: 'PWD', rows: demographics.pwd },
        { label: '4Ps', rows: demographics.four_ps },
        { label: 'Solo parent', rows: demographics.solo_parent },
        { label: 'Indigenous', rows: demographics.indigenous },
    ].map(({ label, rows }) => {
        const yes = countForLabel(rows, 'Yes');
        const no = countForLabel(rows, 'No');

        return { label, count: yes, total: yes + no };
    });
}

function sumCounts(rows: DemographicCount[]): number {
    return rows.reduce((sum, row) => sum + row.count, 0);
}

function countForLabel(rows: DemographicCount[], label: string): number {
    return rows.find((row) => row.label === label)?.count ?? 0;
}

function topCount(rows: DemographicCount[]): DemographicCount | undefined {
    return [...rows].sort((left, right) => right.count - left.count)[0];
}

function percentLabel(part: number, total: number): string {
    if (total <= 0) {
        return '0%';
    }

    return `${Math.round((part / total) * 100)}%`;
}

const PYRAMID_SKELETON_WIDTHS = ['40%', '62%', '48%', '78%', '34%', '22%'];

export function DemographicsPanelSkeleton() {
    return (
        <div
            className="space-y-4"
            aria-busy="true"
            aria-label="Loading demographics"
        >
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div
                        key={index}
                        className="rounded-lg border border-border bg-card px-4 py-3"
                    >
                        <Skeleton className="h-3 w-24" />
                        <Skeleton className="mt-2 h-6 w-16" />
                        {index > 0 ? (
                            <Skeleton className="mt-1 h-3 w-20" />
                        ) : null}
                    </div>
                ))}
            </div>
            <div className="grid items-start gap-4 lg:grid-cols-3">
                <DashboardChartCardSkeleton
                    label="Loading age by sex"
                    className="lg:col-span-2"
                >
                    <PyramidSkeleton />
                </DashboardChartCardSkeleton>
                <DashboardChartCardSkeleton label="Loading sex">
                    <DonutSkeleton />
                </DashboardChartCardSkeleton>
            </div>
            <div className="grid items-start gap-4 lg:grid-cols-2">
                <DashboardChartCardSkeleton label="Loading civil status">
                    <RankedBarsSkeleton />
                </DashboardChartCardSkeleton>
                <DashboardChartCardSkeleton label="Loading sector inclusion">
                    <ShareBarsSkeleton />
                </DashboardChartCardSkeleton>
            </div>
        </div>
    );
}

function PyramidSkeleton() {
    return (
        <div className="flex h-[260px] flex-col justify-between py-2">
            {PYRAMID_SKELETON_WIDTHS.map((width) => (
                <div key={width} className="grid grid-cols-[88px_1fr] gap-3">
                    <Skeleton className="h-3 w-16 justify-self-end" />
                    <div className="grid grid-cols-2 gap-1">
                        <div className="flex justify-end">
                            <Skeleton className="h-3" style={{ width }} />
                        </div>
                        <Skeleton className="h-3" style={{ width }} />
                    </div>
                </div>
            ))}
        </div>
    );
}

function DonutSkeleton() {
    return (
        <div>
            <div className="flex h-[220px] items-center justify-center">
                <Skeleton className="size-44 rounded-full" />
            </div>
            <div className="mt-2 flex flex-wrap gap-2">
                <Skeleton className="h-3 w-16" />
                <Skeleton className="h-3 w-20" />
                <Skeleton className="h-3 w-24" />
            </div>
        </div>
    );
}

function RankedBarsSkeleton() {
    return (
        <div className="flex flex-col gap-3 py-1">
            {Array.from({ length: 5 }).map((_, index) => (
                <div key={index} className="flex items-center gap-3">
                    <Skeleton className="h-3 w-24 shrink-0" />
                    <div className="min-w-0 flex-1">
                        <Skeleton
                            className="h-3"
                            style={{ width: `${88 - index * 12}%` }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

function ShareBarsSkeleton() {
    return (
        <div className="flex flex-col gap-3">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="space-y-1.5">
                    <div className="flex items-center justify-between gap-3">
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-4 w-16" />
                    </div>
                    <Skeleton className="h-1.5 w-full rounded-full" />
                </div>
            ))}
        </div>
    );
}
