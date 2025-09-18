import { ChartConfig, ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Pie, PieChart } from 'recharts';

const chartConfig = {
    visitors: {
        label: 'Visitors',
    },
    pending: {
        label: 'Pending',
        color: 'var(--chart-1)',
    },
    verified: {
        label: 'Verified',
        color: 'var(--chart-2)',
    },
    delivered: {
        label: 'Delivered',
        color: 'var(--chart-3)',
    },
    denied: {
        label: 'Denied',
        color: 'var(--chart-4)',
    },
} satisfies ChartConfig;

export interface ChartData {
    status: string;
    count: number;
    fill: string;
}

export default function ChartStatus({ chartData }: { chartData: ChartData[] }) {
    return (
        <ChartContainer config={chartConfig} className="mx-auto aspect-square max-h-[250px]">
            <PieChart>
                <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel />} />
                <Pie data={chartData} dataKey="count" nameKey="status" />
                {/* <ChartLegend
                    content={<ChartLegendContent nameKey="status" />}
                    className="-translate-y-2 flex-wrap gap-2 *:basis-1/4 *:justify-center"
                /> */}
            </PieChart>
        </ChartContainer>
    );
}
