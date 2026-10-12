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
import type { DashboardTone } from '@/pages/user/dashboard/dashboard-stat-card';
import type { DashboardSummary } from '@/types/dashboard';

type KpiCardsProps = {
    summary: DashboardSummary;
    variant?: 'grid' | 'strip';
    className?: string;
};

const primaryKpis: {
    key:
        | 'total_requests'
        | 'delivered_requests'
        | 'in_progress_requests'
        | 'denied_requests';
    label: string;
    description: string;
    icon: typeof ClipboardList;
    tone: DashboardTone;
}[] = [
    {
        key: 'total_requests',
        label: 'Total requests',
        description: 'Assistance requests matching the filters',
        icon: ClipboardList,
        tone: 'info',
    },
    {
        key: 'delivered_requests',
        label: 'Delivered',
        description: 'Requests marked as delivered',
        icon: CheckCircle2,
        tone: 'success',
    },
    {
        key: 'in_progress_requests',
        label: 'In progress',
        description: 'Not delivered or denied yet',
        icon: Loader,
        tone: 'warning',
    },
    {
        key: 'denied_requests',
        label: 'Denied',
        description: 'Requests marked as denied',
        icon: Ban,
        tone: 'danger',
    },
];

const secondaryKpis: {
    key: 'total_delivered_items' | 'unique_beneficiaries' | 'active_programs';
    label: string;
    description: string;
    icon: typeof Package;
    tone: DashboardTone;
}[] = [
    {
        key: 'total_delivered_items',
        label: 'Delivered items',
        description: 'Total quantity received',
        icon: Package,
        tone: 'success',
    },
    {
        key: 'unique_beneficiaries',
        label: 'Beneficiaries',
        description: 'People and organizations assisted',
        icon: Users,
        tone: 'info',
    },
    {
        key: 'active_programs',
        label: 'Active programs',
        description: 'Open programs',
        icon: FolderKanban,
        tone: 'success',
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
                    tone={kpi.tone}
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
