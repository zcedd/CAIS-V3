import { ClipboardList, Loader, Package, PackageCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { StatCard, StatCardSkeleton } from '@/components/user/stat-card';
import type { ProgramSummary } from '@/types/program';

type ProgramKpiCardsProps = {
    summary: ProgramSummary;
};

const kpis: {
    key: keyof ProgramSummary;
    label: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        key: 'total_requests',
        label: 'Total requests',
        description: 'Assistance requests for this program',
        icon: ClipboardList,
    },
    {
        key: 'delivered_requests',
        label: 'Delivered requests',
        description: 'Requests marked as delivered',
        icon: PackageCheck,
    },
    {
        key: 'in_progress_requests',
        label: 'In progress',
        description: 'Not delivered or denied yet',
        icon: Loader,
    },
    {
        key: 'total_delivered_items',
        label: 'Delivered items',
        description: 'Total quantity received',
        icon: Package,
    },
];

export function ProgramKpiCards({ summary }: ProgramKpiCardsProps) {
    const deliveryRate =
        summary.total_requests > 0
            ? Math.round(
                  (summary.delivered_requests / summary.total_requests) * 100,
              )
            : null;

    return (
        <div
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            data-tour="program-kpis"
        >
            {kpis.map((kpi) => {
                const isDelivered = kpi.key === 'delivered_requests';
                const showRate = isDelivered && deliveryRate !== null;

                return (
                    <StatCard
                        key={kpi.key}
                        label={kpi.label}
                        value={summary[kpi.key].toLocaleString()}
                        description={showRate ? undefined : kpi.description}
                        icon={kpi.icon}
                        footer={
                            showRate ? (
                                <div className="flex flex-col gap-1.5">
                                    <Progress
                                        value={deliveryRate}
                                        aria-label={`Delivery rate ${deliveryRate}%`}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        <span className="font-medium text-foreground tabular-nums">
                                            {deliveryRate}%
                                        </span>{' '}
                                        of all requests delivered
                                    </p>
                                </div>
                            ) : undefined
                        }
                    />
                );
            })}
        </div>
    );
}

export function ProgramKpiCardsSkeleton() {
    return (
        <div
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            data-tour="program-kpis"
            aria-busy="true"
            aria-label="Loading program statistics"
        >
            {kpis.map((kpi) => (
                <StatCardSkeleton
                    key={kpi.key}
                    withFooter={kpi.key === 'delivered_requests'}
                />
            ))}
        </div>
    );
}
