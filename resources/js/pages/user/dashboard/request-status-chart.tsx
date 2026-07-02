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
import type { RequestStatusChartPoint } from '@/types/dashboard';
import { Cell, Pie, PieChart } from 'recharts';

const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'hsl(var(--muted-foreground))',
];

type RequestStatusChartProps = {
    data: RequestStatusChartPoint[];
};

export function RequestStatusChart({ data }: RequestStatusChartProps) {
    const chartConfig = data.reduce<ChartConfig>((config, point, index) => {
        config[point.status] = {
            label: point.status,
            color: CHART_COLORS[index % CHART_COLORS.length],
        };

        return config;
    }, {});

    chartConfig.count = { label: 'Requests' };

    const chartData = data.map((point, index) => ({
        ...point,
        fill: CHART_COLORS[index % CHART_COLORS.length],
    }));

    return (
        <Card className="h-full" data-tour="dashboard-requests-status-chart">
            <CardHeader>
                <CardTitle>Request status</CardTitle>
                <CardDescription>
                    Distribution of assistance requests by current status
                </CardDescription>
            </CardHeader>
            <CardContent>
                {chartData.length === 0 ? (
                    <p className="flex h-[280px] items-center justify-center text-sm text-muted-foreground">
                        No request data for the selected filters.
                    </p>
                ) : (
                    <ChartContainer
                        config={chartConfig}
                        className="mx-auto aspect-auto h-[280px] w-full"
                    >
                        <PieChart>
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        hideLabel
                                        nameKey="status"
                                    />
                                }
                            />
                            <Pie
                                data={chartData}
                                dataKey="count"
                                nameKey="status"
                                innerRadius={60}
                                outerRadius={100}
                                paddingAngle={2}
                            >
                                {chartData.map((entry) => (
                                    <Cell
                                        key={entry.status}
                                        fill={entry.fill}
                                    />
                                ))}
                            </Pie>
                        </PieChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
