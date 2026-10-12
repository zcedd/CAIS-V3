import { Package } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import {
    DashboardSectionCard,
    SectionCardSkeleton,
} from '@/pages/user/dashboard/dashboard-stat-card';
import type { DeliveredItemsChartPoint } from '@/types/dashboard';

type TopDeliveredItemsProps = {
    data: DeliveredItemsChartPoint[];
};

export function TopDeliveredItems({ data }: TopDeliveredItemsProps) {
    const ranked = [...data]
        .sort(
            (left, right) =>
                (right.quantity ?? right.count) -
                (left.quantity ?? left.count),
        )
        .slice(0, 5);
    const totalQuantity = data.reduce(
        (sum, point) => sum + (point.quantity ?? point.count),
        0,
    );

    return (
        <DashboardSectionCard
            title="Top delivered items"
            description="Sorted by quantity received, using the filters above"
            icon={Package}
            className="h-full"
            data-tour="dashboard-top-delivered-items"
        >
            {ranked.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">
                    No delivered items with these filters.
                </p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {ranked.map((point) => {
                        const quantity = point.quantity ?? point.count;
                        const percent =
                            totalQuantity > 0
                                ? (quantity / totalQuantity) * 100
                                : 0;

                        return (
                            <li
                                key={`${point.item}-${point.unit}`}
                                className="flex flex-col gap-1"
                            >
                                <div className="flex items-baseline justify-between gap-3">
                                    <p className="min-w-0 truncate text-sm font-medium">
                                        {point.item}
                                    </p>
                                    <p className="shrink-0 text-sm tabular-nums">
                                        {quantity.toLocaleString()}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {percent.toFixed(1)}%
                                        </span>
                                    </p>
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
        <SectionCardSkeleton
            label="Loading top delivered items"
            titleClassName="w-40"
            descriptionClassName="w-56"
            className="h-full"
        >
            <div className="space-y-4">
                {Array.from({ length: 5 }).map((_, index) => (
                    <div key={index} className="space-y-2">
                        <div className="flex justify-between gap-3">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-4 w-12" />
                        </div>
                        <Skeleton className="h-2 w-full rounded-full" />
                    </div>
                ))}
            </div>
        </SectionCardSkeleton>
    );
}
