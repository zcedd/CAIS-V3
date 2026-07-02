import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DashboardSummary } from '@/types/dashboard';
import {
    CheckCircle2,
    ClipboardList,
    Package,
    FolderKanban,
} from 'lucide-react';

type KpiCardsProps = {
    summary: DashboardSummary;
};

const kpis = [
    {
        key: 'total_requests' as const,
        label: 'Total requests',
        description: 'Assistance requests in scope',
        icon: ClipboardList,
    },
    {
        key: 'delivered_requests' as const,
        label: 'Delivered requests',
        description: 'Requests marked as delivered',
        icon: CheckCircle2,
    },
    {
        key: 'total_delivered_items' as const,
        label: 'Delivered items',
        description: 'Total quantity received',
        icon: Package,
    },
    {
        key: 'active_programs' as const,
        label: 'Active programs',
        description: 'Open programs in department',
        icon: FolderKanban,
    },
];

export function KpiCards({ summary }: KpiCardsProps) {
    return (
        <div
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            data-tour="dashboard-kpis"
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
