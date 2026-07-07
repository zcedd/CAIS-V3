import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ProgramSummary } from '@/types/program';
import {
    CheckCircle2,
    ClipboardList,
    Clock,
    Package,
} from 'lucide-react';

type ProgramKpiCardsProps = {
    summary: ProgramSummary;
};

const kpis = [
    {
        key: 'total_requests' as const,
        label: 'Total requests',
        description: 'Assistance requests for this program',
        icon: ClipboardList,
    },
    {
        key: 'delivered_requests' as const,
        label: 'Delivered requests',
        description: 'Requests marked as delivered',
        icon: CheckCircle2,
    },
    {
        key: 'in_progress_requests' as const,
        label: 'In progress',
        description: 'Requests not yet terminal',
        icon: Clock,
    },
    {
        key: 'total_delivered_items' as const,
        label: 'Delivered items',
        description: 'Total quantity received',
        icon: Package,
    },
];

export function ProgramKpiCards({ summary }: ProgramKpiCardsProps) {
    return (
        <div
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            data-tour="program-kpis"
        >
            {kpis.map((kpi) => (
                <Card key={kpi.key} size="sm">
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div className="space-y-1">
                            <CardDescription>{kpi.label}</CardDescription>
                            <CardTitle className="text-3xl font-semibold tabular-nums">
                                {summary[kpi.key].toLocaleString()}
                            </CardTitle>
                            <p className="text-xs text-muted-foreground">
                                {kpi.description}
                            </p>
                        </div>
                        <kpi.icon className="size-5 shrink-0 text-muted-foreground" />
                    </CardHeader>
                </Card>
            ))}
        </div>
    );
}

export function ProgramKpiCardsSkeleton() {
    return (
        <div
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            data-tour="program-kpis"
            aria-busy="true"
            aria-label="Loading program statistics"
        >
            {kpis.map((kpi) => (
                <Card key={kpi.key} size="sm">
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div className="space-y-2">
                            <div className="h-4 w-24 animate-pulse rounded bg-muted" />
                            <div className="h-9 w-16 animate-pulse rounded bg-muted" />
                            <div className="h-3 w-32 animate-pulse rounded bg-muted" />
                        </div>
                        <kpi.icon className="size-5 shrink-0 text-muted-foreground/40" />
                    </CardHeader>
                </Card>
            ))}
        </div>
    );
}
