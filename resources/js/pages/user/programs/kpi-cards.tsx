import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { ProgramSummary } from '@/types/program';
import {
    ClipboardList,
    Loader,
    Package,
    PackageCheck,
    type LucideIcon,
} from 'lucide-react';

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
                const Icon = kpi.icon;
                const isDelivered = kpi.key === 'delivered_requests';

                return (
                    <div
                        key={kpi.key}
                        className={cn(
                            'rounded-xl border border-border bg-card p-4',
                            'transition-colors duration-150',
                        )}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-xs text-muted-foreground">
                                {kpi.label}
                            </p>
                            <Icon className="size-4 shrink-0 text-muted-foreground" />
                        </div>
                        <p className="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums">
                            {summary[kpi.key].toLocaleString()}
                        </p>
                        {isDelivered && deliveryRate !== null ? (
                            <div className="mt-2 space-y-1.5">
                                <Progress
                                    value={deliveryRate}
                                    aria-label={`Delivery rate ${deliveryRate}%`}
                                />
                                <p className="text-xs text-muted-foreground">
                                    <span className="font-medium tabular-nums text-foreground">
                                        {deliveryRate}%
                                    </span>{' '}
                                    of all requests delivered
                                </p>
                            </div>
                        ) : (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {kpi.description}
                            </p>
                        )}
                    </div>
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
                <div
                    key={kpi.key}
                    className="rounded-xl border border-border bg-card p-4"
                >
                    <div className="flex items-center justify-between gap-2">
                        <Skeleton className="h-3 w-24 rounded-sm" />
                        <Skeleton className="size-4 rounded-sm" />
                    </div>
                    <Skeleton className="mt-2 h-8 w-16 rounded-sm" />
                    <Skeleton className="mt-2 h-3 w-32 rounded-sm" />
                </div>
            ))}
        </div>
    );
}
