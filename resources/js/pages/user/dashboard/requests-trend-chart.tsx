import { ChartLine } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent
    
} from '@/components/ui/chart';
import type {ChartConfig} from '@/components/ui/chart';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { DashboardChartFrame } from '@/pages/user/dashboard/dashboard-chart-frame';
import {
    aggregateRequestsTrend
    
    
} from '@/types/dashboard';
import type {RequestsTrendPoint, TrendPeriod} from '@/types/dashboard';

const chartConfig = {
    count: {
        label: 'Requests',
        color: 'var(--chart-1)',
    },
} satisfies ChartConfig;

type RequestsTrendChartProps = {
    data: RequestsTrendPoint[];
};

export function RequestsTrendChart({ data }: RequestsTrendChartProps) {
    const [period, setPeriod] = useState<TrendPeriod>('week');

    const chartData = useMemo(
        () => aggregateRequestsTrend(data, period),
        [data, period],
    );

    return (
        <Card className="gap-4" data-tour="dashboard-requests-trend">
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <CardTitle>Requests over time</CardTitle>
                    <CardDescription>
                        Assistance request volume by{' '}
                        {period === 'day'
                            ? 'day'
                            : period === 'week'
                              ? 'week'
                              : 'month'}
                    </CardDescription>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <CardAction className="static col-auto row-auto self-auto justify-self-auto">
                        <ToggleGroup
                            type="single"
                            value={period}
                            onValueChange={(value) => {
                                if (
                                    value === 'day' ||
                                    value === 'week' ||
                                    value === 'month'
                                ) {
                                    setPeriod(value);
                                }
                            }}
                            variant="outline"
                            size="sm"
                            spacing={0}
                        >
                            <ToggleGroupItem value="day">Day</ToggleGroupItem>
                            <ToggleGroupItem value="week">Week</ToggleGroupItem>
                            <ToggleGroupItem value="month">
                                Month
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </CardAction>
                    <ChartLine className="hidden size-5 text-muted-foreground sm:block" />
                </div>
            </CardHeader>
            <CardContent>
                {chartData.length === 0 ? (
                    <p className="flex h-[240px] items-center justify-center text-sm text-muted-foreground">
                        No request timeline with these filters.
                    </p>
                ) : (
                    <DashboardChartFrame height={240}>
                        <ChartContainer
                            key={period}
                            config={chartConfig}
                            className="!aspect-auto h-full w-full"
                            initialDimension={{ width: 640, height: 240 }}
                        >
                            <LineChart
                                data={chartData}
                                margin={{
                                    left: 4,
                                    right: 12,
                                    top: 8,
                                    bottom: 4,
                                }}
                            >
                                <CartesianGrid vertical={false} />
                                <XAxis
                                    dataKey="label"
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={8}
                                    minTickGap={28}
                                    tickFormatter={(value: string) =>
                                        value.length > 10
                                            ? value.slice(5)
                                            : value
                                    }
                                />
                                <YAxis
                                    tickLine={false}
                                    axisLine={false}
                                    allowDecimals={false}
                                    width={40}
                                />
                                <ChartTooltip
                                    content={<ChartTooltipContent />}
                                />
                                <Line
                                    type="monotone"
                                    dataKey="count"
                                    stroke="var(--color-count)"
                                    strokeWidth={2}
                                    dot={false}
                                    activeDot={{ r: 4 }}
                                />
                            </LineChart>
                        </ChartContainer>
                    </DashboardChartFrame>
                )}
            </CardContent>
        </Card>
    );
}
