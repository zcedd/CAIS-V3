import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DeliveredItemsChartPoint } from '@/types/dashboard';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

const chartConfig = {
    quantity: {
        label: 'Quantity',
        color: 'var(--chart-1)',
    },
} satisfies ChartConfig;

type DeliveredItemsChartProps = {
    data: DeliveredItemsChartPoint[];
};

export function DeliveredItemsChart({ data }: DeliveredItemsChartProps) {
    const chartData = data.map((point) => ({
        label: `${point.item} (${point.unit})`,
        quantity: point.quantity,
    }));

    return (
        <Card className="h-full" data-tour="dashboard-items-delivered-charts">
            <CardHeader>
                <CardTitle>Delivered items</CardTitle>
                <CardDescription>
                    Top items delivered by quantity received
                </CardDescription>
            </CardHeader>
            <CardContent>
                {chartData.length === 0 ? (
                    <p className="flex h-[280px] items-center justify-center text-sm text-muted-foreground">
                        No delivered items for the selected filters.
                    </p>
                ) : (
                    <ChartContainer
                        config={chartConfig}
                        className="aspect-auto h-[280px] w-full"
                    >
                        <BarChart
                            data={chartData}
                            layout="vertical"
                            margin={{ left: 8, right: 8 }}
                        >
                            <CartesianGrid horizontal={false} />
                            <XAxis
                                type="number"
                                tickLine={false}
                                axisLine={false}
                            />
                            <YAxis
                                type="category"
                                dataKey="label"
                                width={140}
                                tickLine={false}
                                axisLine={false}
                                tickFormatter={(value: string) =>
                                    value.length > 24
                                        ? `${value.slice(0, 24)}…`
                                        : value
                                }
                            />
                            <ChartTooltip content={<ChartTooltipContent />} />
                            <Bar
                                dataKey="quantity"
                                fill="var(--color-quantity)"
                                radius={4}
                            />
                        </BarChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
