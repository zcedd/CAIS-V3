import { Package } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { DashboardSectionCard } from '@/pages/user/dashboard/dashboard-stat-card';
import type { DeliveredItemsChartPoint } from '@/types/dashboard';

type TopDeliveredItemsProps = {
    data: DeliveredItemsChartPoint[];
};

export function TopDeliveredItems({ data }: TopDeliveredItemsProps) {
    const totalQuantity = data.reduce(
        (sum, point) => sum + (point.quantity ?? point.count),
        0,
    );

    return (
        <DashboardSectionCard
            title="Top delivered items"
            description="Sorted by quantity received, using the filters above"
            icon={Package}
            data-tour="dashboard-top-delivered-items"
        >
            {data.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">
                    No delivered items with these filters.
                </p>
            ) : (
                <ul className="space-y-4">
                    {data.map((point) => {
                        const quantity = point.quantity ?? point.count;
                        const percent =
                            totalQuantity > 0
                                ? (quantity / totalQuantity) * 100
                                : 0;

                        return (
                            <li
                                key={`${point.item}-${point.unit}`}
                                className="space-y-2"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {point.item}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {point.unit} ·{' '}
                                            {point.count.toLocaleString()}{' '}
                                            deliveries
                                        </p>
                                    </div>
                                    <div className="shrink-0 text-right">
                                        <p className="text-sm font-medium tabular-nums">
                                            {quantity.toLocaleString()}
                                        </p>
                                        <p className="text-xs text-muted-foreground tabular-nums">
                                            {percent.toFixed(1)}%
                                        </p>
                                    </div>
                                </div>
                                <Progress value={percent} />
                            </li>
                        );
                    })}
                </ul>
            )}
        </DashboardSectionCard>
    );
}

export function TopDeliveredItemsSkeleton() {
    return (
        <div
            className="overflow-hidden rounded-2xl bg-card ring-1 ring-foreground/10"
            aria-busy="true"
            aria-label="Loading top delivered items"
        >
            <div className="flex items-start justify-between gap-2 px-6 pt-6">
                <div className="space-y-2">
                    <div className="h-5 w-40 animate-pulse rounded bg-muted" />
                    <div className="h-4 w-56 animate-pulse rounded bg-muted" />
                </div>
                <div className="size-5 animate-pulse rounded bg-muted" />
            </div>
            <div className="space-y-4 px-6 py-6">
                {Array.from({ length: 5 }).map((_, index) => (
                    <div key={index} className="space-y-2">
                        <div className="flex justify-between gap-3">
                            <div className="h-4 w-32 animate-pulse rounded bg-muted" />
                            <div className="h-4 w-12 animate-pulse rounded bg-muted" />
                        </div>
                        <div className="h-2 w-full animate-pulse rounded bg-muted" />
                    </div>
                ))}
            </div>
        </div>
    );
}
