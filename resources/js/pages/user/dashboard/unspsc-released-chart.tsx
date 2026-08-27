import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { DashboardChartFrame } from '@/pages/user/dashboard/dashboard-chart-frame';
import type { UnspscReleasedChartPoint } from '@/types/dashboard';

const chartConfig = {
    quantity: {
        label: 'Quantity',
        color: 'var(--chart-2)',
    },
} satisfies ChartConfig;

type UnspscReleasedChartProps = {
    data: UnspscReleasedChartPoint[];
    className?: string;
};

export function UnspscReleasedChart({
    data,
    className,
}: UnspscReleasedChartProps) {
    const chartData = data.map((point) => ({
        label: point.segment,
        quantity: point.quantity,
        code: point.code,
    }));

    const chartHeight = Math.min(
        360,
        Math.max(180, chartData.length * 36 + 24),
    );

    const chart =
        chartData.length === 0 ? (
            <p className="flex h-[180px] items-center justify-center text-sm text-muted-foreground">
                No classified released items for the selected filters.
            </p>
        ) : (
            <DashboardChartFrame height={chartHeight} className={className}>
                <ChartContainer
                    config={chartConfig}
                    className="!aspect-auto h-full w-full"
                    initialDimension={{ width: 640, height: chartHeight }}
                >
                    <BarChart
                        data={chartData}
                        layout="vertical"
                        margin={{ left: 4, right: 12, top: 4, bottom: 4 }}
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
                            width={128}
                            tickLine={false}
                            axisLine={false}
                            tickFormatter={(value: string) =>
                                value.length > 22
                                    ? `${value.slice(0, 22)}…`
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
            </DashboardChartFrame>
        );

    return <div data-tour="dashboard-unspsc-released-chart">{chart}</div>;
}
