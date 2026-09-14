import {
    Ban,
    CheckCircle2,
    ClipboardList,
    FolderKanban,
    Loader,
    Package,
    Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { DashboardStatCard } from '@/pages/user/dashboard/dashboard-stat-card';
import type { DashboardSummary } from '@/types/dashboard';

type KpiCardsProps = {
    summary: DashboardSummary;
    variant?: 'grid' | 'strip';
    className?: string;
};

const primaryKpis = [
    {
        key: 'total_requests' as const,
        label: 'Total requests',
        description: 'Assistance requests matching the filters',
        icon: ClipboardList,
    },
    {
        key: 'delivered_requests' as const,
        label: 'Delivered',
        description: 'Requests marked as delivered',
        icon: CheckCircle2,
    },
    {
        key: 'in_progress_requests' as const,
        label: 'In progress',
        description: 'Not delivered or denied yet',
        icon: Loader,
    },
    {
        key: 'denied_requests' as const,
        label: 'Denied',
        description: 'Requests marked as denied',
        icon: Ban,
    },
];

const secondaryKpis = [
    {
        key: 'total_delivered_items' as const,
        label: 'Delivered items',
        description: 'Total quantity received',
        icon: Package,
    },
    {
        key: 'unique_beneficiaries' as const,
        label: 'Beneficiaries',
        description: 'People and organizations assisted',
        icon: Users,
    },
    {
        key: 'active_programs' as const,
        label: 'Active programs',
        description: 'Open programs',
        icon: FolderKanban,
    },
];

const stripKpis = [...primaryKpis, ...secondaryKpis.slice(0, 1)];

export function KpiCards({
    summary,
    variant = 'grid',
    className,
}: KpiCardsProps) {
    const items =
        variant === 'strip' ? stripKpis : [...primaryKpis, ...secondaryKpis];

    return (
        <div
            className={cn(
                'grid gap-4 sm:grid-cols-2 xl:grid-cols-4',
                className,
            )}
            data-tour="dashboard-kpis"
        >
            {items.map((kpi) => (
                <DashboardStatCard
                    key={kpi.key}
                    label={kpi.label}
                    value={summary[kpi.key].toLocaleString()}
                    description={kpi.description}
                    icon={kpi.icon}
                    valueClassName={
                        variant === 'strip' ? 'text-2xl' : 'text-3xl'
                    }
                />
            ))}
        </div>
    );
}

export function KpiCardsSkeleton({
    variant = 'grid',
}: {
    variant?: 'grid' | 'strip';
}) {
    const items =
        variant === 'strip' ? stripKpis : [...primaryKpis, ...secondaryKpis];

    return (
        <div
            className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            data-tour="dashboard-kpis"
            aria-busy="true"
            aria-label="Loading dashboard statistics"
        >
            {items.map((kpi) => (
                <DashboardStatCard
                    key={kpi.key}
                    label={kpi.label}
                    value={
                        <span className="inline-block h-8 w-16 animate-pulse rounded bg-muted" />
                    }
                    description={
                        <span className="inline-block h-3 w-28 animate-pulse rounded bg-muted" />
                    }
                    icon={kpi.icon}
                />
            ))}
        </div>
    );
}
