import { PieChart as PieChartIcon } from 'lucide-react';
import { Cell, Pie, PieChart } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent
    
} from '@/components/ui/chart';
import type {ChartConfig} from '@/components/ui/chart';
import { Progress } from '@/components/ui/progress';
import { DashboardChartFrame } from '@/pages/user/dashboard/dashboard-chart-frame';
import { DashboardSectionCard } from '@/pages/user/dashboard/dashboard-stat-card';
import type { RequestStatusChartPoint } from '@/types/dashboard';

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
    const total = data.reduce((sum, point) => sum + point.count, 0);

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
        percent: total > 0 ? (point.count / total) * 100 : 0,
    }));

    return (
        <DashboardSectionCard
            title="Requests by status"
            description="Distribution of assistance requests by current status"
            icon={PieChartIcon}
            data-tour="dashboard-requests-status-chart"
        >
            {chartData.length === 0 ? (
                <p className="flex h-[220px] items-center justify-center text-sm text-muted-foreground">
                    No request data for the selected filters.
                </p>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2 lg:items-start">
                    <DashboardChartFrame height={220}>
                        <ChartContainer
                            config={chartConfig}
                            className="!aspect-auto mx-auto h-full w-full"
                            initialDimension={{ width: 320, height: 220 }}
                        >
                            <PieChart
                                margin={{
                                    top: 8,
                                    right: 8,
                                    bottom: 8,
                                    left: 8,
                                }}
                            >
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
                                    innerRadius={50}
                                    outerRadius={80}
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
                    </DashboardChartFrame>

                    <ul className="space-y-3">
                        {chartData.map((entry) => (
                            <li key={entry.status} className="space-y-1.5">
                                <div className="flex items-center justify-between gap-2 text-sm">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <span
                                            className="size-2.5 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: entry.fill,
                                            }}
                                        />
                                        <span className="truncate font-medium">
                                            {entry.status}
                                        </span>
                                    </div>
                                    <div className="shrink-0 text-right tabular-nums text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            {entry.count.toLocaleString()}
                                        </span>
                                        <span className="ml-2 text-xs">
                                            {entry.percent.toFixed(1)}%
                                        </span>
                                    </div>
                                </div>
                                <Progress
                                    value={entry.percent}
                                    className="h-1.5"
                                />
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </DashboardSectionCard>
    );
}
