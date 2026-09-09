import {
    Accessibility,
    HeartHandshake,
    TreePine,
    UserRound,
    Users,
    VenusAndMars,
    IdCard,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { DashboardSectionCard } from '@/pages/user/dashboard/dashboard-stat-card';
import type {
    BeneficiaryTypeChartPoint,
    DashboardDemographics,
    DemographicCount,
} from '@/types/dashboard';

function BreakdownList({
    title,
    description,
    data,
    icon,
}: {
    title: string;
    description: string;
    data: DemographicCount[];
    icon: LucideIcon;
}) {
    const total = data.reduce((sum, row) => sum + row.count, 0);

    return (
        <DashboardSectionCard
            title={title}
            description={description}
            icon={icon}
        >
            {data.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    No data with these filters.
                </p>
            ) : (
                <ul className="space-y-3">
                    {data.map((row) => {
                        const percent =
                            total > 0 ? (row.count / total) * 100 : 0;

                        return (
                            <li key={row.label} className="space-y-1.5">
                                <div className="flex items-center justify-between gap-2 text-sm">
                                    <span className="font-medium">
                                        {row.label}
                                    </span>
                                    <span className="tabular-nums text-muted-foreground">
                                        {row.count.toLocaleString()} (
                                        {percent.toFixed(1)}%)
                                    </span>
                                </div>
                                <Progress value={percent} className="h-1.5" />
                            </li>
                        );
                    })}
                </ul>
            )}
        </DashboardSectionCard>
    );
}

type BeneficiaryBreakdownProps = {
    data: BeneficiaryTypeChartPoint[];
};

export function BeneficiaryBreakdown({ data }: BeneficiaryBreakdownProps) {
    const points: DemographicCount[] = data.map((row) => ({
        label: row.label,
        count: row.count,
    }));

    return (
        <BreakdownList
            title="Requests by beneficiary type"
            description="Requests from individuals and organizations"
            data={points}
            icon={Users}
        />
    );
}

type DemographicsPanelProps = {
    demographics: DashboardDemographics;
};

export function DemographicsPanel({ demographics }: DemographicsPanelProps) {
    return (
        <div
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            data-tour="dashboard-demographics"
        >
            <BreakdownList
                title="Sex"
                description="Individual requests by sex"
                data={demographics.sex}
                icon={VenusAndMars}
            />
            <BreakdownList
                title="Age group"
                description="Individual requests by age bracket"
                data={demographics.age}
                icon={UserRound}
            />
            <BreakdownList
                title="Civil status"
                description="Individual requests by civil status"
                data={demographics.civil_status}
                icon={IdCard}
            />
            <BreakdownList
                title="PWD"
                description="Persons with disability"
                data={demographics.pwd}
                icon={Accessibility}
            />
            <BreakdownList
                title="4Ps"
                description="4Ps beneficiary status"
                data={demographics.four_ps}
                icon={HeartHandshake}
            />
            <BreakdownList
                title="Solo parent"
                description="Solo parent status"
                data={demographics.solo_parent}
                icon={Users}
            />
            <BreakdownList
                title="Indigenous"
                description="Indigenous peoples status"
                data={demographics.indigenous}
                icon={TreePine}
            />
        </div>
    );
}

export function DemographicsPanelSkeleton() {
    return (
        <div
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            aria-busy="true"
            aria-label="Loading demographics"
        >
            {Array.from({ length: 7 }).map((_, index) => (
                <div
                    key={index}
                    className="overflow-hidden rounded-2xl bg-card ring-1 ring-foreground/10"
                >
                    <div className="flex items-start justify-between gap-2 px-6 pt-6">
                        <div className="space-y-2">
                            <div className="h-5 w-28 animate-pulse rounded bg-muted" />
                            <div className="h-4 w-40 animate-pulse rounded bg-muted" />
                        </div>
                        <div className="size-5 animate-pulse rounded bg-muted" />
                    </div>
                    <div className="space-y-3 px-6 py-6">
                        {Array.from({ length: 3 }).map((__, row) => (
                            <div key={row} className="space-y-1.5">
                                <div className="h-4 w-full animate-pulse rounded bg-muted" />
                                <div className="h-1.5 w-full animate-pulse rounded bg-muted" />
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
