import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    PolarAngleAxis,
    RadialBar,
    RadialBarChart,
    XAxis,
    YAxis,
} from 'recharts';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { DashboardChartFrame } from '@/pages/user/dashboard/dashboard-chart-frame';
import type {
    AgeBySexPoint,
    DashboardProgramRow,
    DemographicCount,
} from '@/types/dashboard';

const CHART_PALETTE = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
] as const;

const UNSPECIFIED_COLOR = 'var(--muted-foreground)';

const ENTER_ANIMATION = {
    animationDuration: 800,
    animationEasing: 'ease-out',
} as const;

type ColoredCount = DemographicCount & {
    fill: string;
};

type DashboardChartCardProps = {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
    'data-tour'?: string;
};

/**
 * Chart card with a title and description, and no corner icon.
 */
export function DashboardChartCard({
    title,
    description,
    children,
    className,
    contentClassName,
    'data-tour': dataTour,
}: DashboardChartCardProps) {
    return (
        <Card
            size="sm"
            className={cn('h-full', className)}
            data-tour={dataTour}
        >
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description ? (
                    <CardDescription className="text-xs">
                        {description}
                    </CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className={contentClassName}>{children}</CardContent>
        </Card>
    );
}

export function DashboardChartCardSkeleton({
    label,
    className,
    children,
}: {
    label: string;
    className?: string;
    children?: ReactNode;
}) {
    return (
        <Card
            aria-busy="true"
            aria-label={label}
            size="sm"
            className={cn('h-full', className)}
        >
            <CardHeader>
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-3 w-48" />
            </CardHeader>
            <CardContent>
                {children ?? <Skeleton className="h-48 w-full" />}
            </CardContent>
        </Card>
    );
}

export function seriesColor(index: number): string {
    return CHART_PALETTE[index % CHART_PALETTE.length];
}

export function ChartLegendChips({
    items,
    showShare = false,
}: {
    items: ColoredCount[];
    showShare?: boolean;
}) {
    if (items.length === 0) {
        return null;
    }

    const total = items.reduce((sum, item) => sum + item.count, 0);

    return (
        <div className="mt-2 flex flex-wrap gap-2">
            {items.map((item) => (
                <span
                    key={item.label}
                    className="inline-flex items-center gap-1.5 text-[10px] text-muted-foreground"
                >
                    <span
                        className="size-2 rounded-full"
                        style={{ background: item.fill }}
                    />
                    {item.label} ({legendValue(item.count, showShare, total)})
                </span>
            ))}
        </div>
    );
}

function legendValue(count: number, showShare: boolean, total: number): string {
    const countLabel = count.toLocaleString();

    if (!showShare || total <= 0) {
        return countLabel;
    }

    return `${countLabel}, ${Math.round((count / total) * 100)}%`;
}

export function sortByLabelOrder(
    data: DemographicCount[],
    order: readonly string[],
): DemographicCount[] {
    const rank = new Map(order.map((label, index) => [label, index]));

    return [...data].sort((left, right) => {
        const leftRank = rank.get(left.label) ?? order.length;
        const rightRank = rank.get(right.label) ?? order.length;

        return leftRank - rightRank;
    });
}

function colorBreakdown(data: DemographicCount[]): ColoredCount[] {
    let paletteIndex = 0;

    return data.map((row) => {
        const unspecified = row.label.trim().toLowerCase() === 'unspecified';

        return {
            ...row,
            fill: unspecified
                ? UNSPECIFIED_COLOR
                : CHART_PALETTE[paletteIndex++ % CHART_PALETTE.length],
        };
    });
}

function shortenLabel(label: string, max = 18): string {
    if (label.length <= max) {
        return label;
    }

    return `${label.slice(0, max - 1)}…`;
}

function EmptyChart({ message }: { message: string }) {
    return (
        <p className="flex h-48 items-center justify-center text-center text-sm text-muted-foreground">
            {message}
        </p>
    );
}

type BreakdownChartProps = {
    data: DemographicCount[];
    emptyMessage?: string;
    valueLabel?: string;
    showShare?: boolean;
};

function breakdownConfig(valueLabel: string): ChartConfig {
    return {
        count: { label: valueLabel },
    };
}

export function DonutBreakdownChart({
    data,
    emptyMessage = 'No data with these filters.',
    valueLabel = 'Count',
    showShare = false,
}: BreakdownChartProps) {
    const colored = colorBreakdown(data);
    const total = colored.reduce((sum, row) => sum + row.count, 0);

    if (colored.length === 0) {
        return <EmptyChart message={emptyMessage} />;
    }

    return (
        <div>
            <div className="relative">
                <DashboardChartFrame height={220}>
                    <ChartContainer
                        config={breakdownConfig(valueLabel)}
                        className="mx-auto aspect-auto! h-full w-full"
                        initialDimension={{ width: 320, height: 220 }}
                    >
                        <PieChart
                            margin={{ top: 8, right: 8, bottom: 8, left: 8 }}
                        >
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        hideLabel
                                        nameKey="label"
                                    />
                                }
                            />
                            <Pie
                                data={colored}
                                dataKey="count"
                                nameKey="label"
                                innerRadius={58}
                                outerRadius={88}
                                paddingAngle={2}
                                {...ENTER_ANIMATION}
                            >
                                {colored.map((row) => (
                                    <Cell key={row.label} fill={row.fill} />
                                ))}
                            </Pie>
                        </PieChart>
                    </ChartContainer>
                </DashboardChartFrame>
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <span className="text-lg font-semibold tabular-nums">
                        {total.toLocaleString()}
                    </span>
                </div>
            </div>
            <ChartLegendChips items={colored} showShare={showShare} />
        </div>
    );
}

export function ColumnBreakdownChart({
    data,
    emptyMessage = 'No data with these filters.',
    valueLabel = 'Count',
}: BreakdownChartProps) {
    const colored = colorBreakdown(data);

    if (colored.length === 0) {
        return <EmptyChart message={emptyMessage} />;
    }

    return (
        <div>
            <DashboardChartFrame height={220}>
                <ChartContainer
                    config={breakdownConfig(valueLabel)}
                    className="aspect-auto! h-full w-full"
                    initialDimension={{ width: 320, height: 220 }}
                >
                    <BarChart
                        data={colored}
                        margin={{ top: 8, right: 8, bottom: 0, left: 0 }}
                    >
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="label"
                            tick={false}
                            tickLine={false}
                            axisLine={false}
                        />
                        <YAxis
                            allowDecimals={false}
                            tickLine={false}
                            axisLine={false}
                            width={32}
                        />
                        <ChartTooltip content={<ChartTooltipContent />} />
                        <Bar dataKey="count" radius={4} {...ENTER_ANIMATION}>
                            {colored.map((row) => (
                                <Cell key={row.label} fill={row.fill} />
                            ))}
                        </Bar>
                    </BarChart>
                </ChartContainer>
            </DashboardChartFrame>
            <ChartLegendChips items={colored} />
        </div>
    );
}

export function RankedBarChart({
    data,
    emptyMessage = 'No data with these filters.',
    valueLabel = 'Count',
    showShare = false,
}: BreakdownChartProps) {
    const colored = colorBreakdown(data);

    if (colored.length === 0) {
        return <EmptyChart message={emptyMessage} />;
    }

    const height = Math.max(180, colored.length * 32 + 16);

    return (
        <div>
            <DashboardChartFrame height={height}>
                <ChartContainer
                    config={breakdownConfig(valueLabel)}
                    className="aspect-auto! h-full w-full"
                    initialDimension={{ width: 320, height }}
                >
                    <BarChart
                        data={colored}
                        layout="vertical"
                        margin={{ top: 4, right: 12, bottom: 0, left: 4 }}
                    >
                        <CartesianGrid horizontal={false} />
                        <XAxis
                            type="number"
                            allowDecimals={false}
                            tickLine={false}
                            axisLine={false}
                        />
                        <YAxis
                            type="category"
                            dataKey="label"
                            width={108}
                            tickLine={false}
                            axisLine={false}
                            tickFormatter={(value: string) =>
                                shortenLabel(value)
                            }
                        />
                        <ChartTooltip content={<ChartTooltipContent />} />
                        <Bar dataKey="count" radius={4} {...ENTER_ANIMATION}>
                            {colored.map((row) => (
                                <Cell key={row.label} fill={row.fill} />
                            ))}
                        </Bar>
                    </BarChart>
                </ChartContainer>
            </DashboardChartFrame>
            <ChartLegendChips items={colored} showShare={showShare} />
        </div>
    );
}

const PYRAMID_AGE_ORDER = [
    'Under 18',
    '18-29',
    '30-44',
    '45-59',
    '60+',
    'Unspecified',
] as const;

const pyramidConfig = {
    male: { label: 'Male', color: 'var(--chart-1)' },
    female: { label: 'Female', color: 'var(--chart-2)' },
} satisfies ChartConfig;

export function PopulationPyramidChart({
    data,
    emptyMessage = 'No data with these filters.',
}: {
    data: AgeBySexPoint[];
    emptyMessage?: string;
}) {
    const byLabel = new Map(data.map((row) => [row.label, row]));
    const extraLabels = data
        .map((row) => row.label)
        .filter(
            (label) =>
                !PYRAMID_AGE_ORDER.includes(
                    label as (typeof PYRAMID_AGE_ORDER)[number],
                ),
        );
    const rows = [...PYRAMID_AGE_ORDER, ...extraLabels].map((label) => {
        const row = byLabel.get(label);

        return {
            label,
            male: -(row?.male ?? 0),
            female: row?.female ?? 0,
            other: row?.other ?? 0,
        };
    });
    const otherTotal = rows.reduce((sum, row) => sum + row.other, 0);
    const drawn = rows.reduce(
        (sum, row) => sum + Math.abs(row.male) + row.female,
        0,
    );

    if (drawn === 0 && otherTotal === 0) {
        return <EmptyChart message={emptyMessage} />;
    }

    const peak = Math.max(
        1,
        ...rows.map((row) => Math.max(Math.abs(row.male), row.female)),
    );

    return (
        <div>
            <DashboardChartFrame height={260}>
                <ChartContainer
                    config={pyramidConfig}
                    className="aspect-auto! h-full w-full"
                    initialDimension={{ width: 480, height: 260 }}
                >
                    <BarChart
                        data={rows}
                        layout="vertical"
                        stackOffset="sign"
                        margin={{ top: 4, right: 12, bottom: 0, left: 4 }}
                    >
                        <CartesianGrid horizontal={false} />
                        <XAxis
                            type="number"
                            domain={[-peak, peak]}
                            allowDecimals={false}
                            tickLine={false}
                            axisLine={false}
                            tickFormatter={(value: number) =>
                                Math.abs(value).toLocaleString()
                            }
                        />
                        <YAxis
                            type="category"
                            dataKey="label"
                            width={88}
                            reversed
                            tickLine={false}
                            axisLine={false}
                        />
                        <ChartTooltip
                            content={
                                <ChartTooltipContent
                                    formatter={(value, name) => (
                                        <div className="flex w-full items-center justify-between gap-4">
                                            <span className="text-muted-foreground">
                                                {name === 'male'
                                                    ? 'Male'
                                                    : 'Female'}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {Math.abs(
                                                    Number(value),
                                                ).toLocaleString()}
                                            </span>
                                        </div>
                                    )}
                                />
                            }
                        />
                        <ChartLegend content={<ChartLegendContent />} />
                        <Bar
                            dataKey="male"
                            stackId="pyramid"
                            fill="var(--color-male)"
                            radius={[4, 0, 0, 4]}
                            {...ENTER_ANIMATION}
                        />
                        <Bar
                            dataKey="female"
                            stackId="pyramid"
                            fill="var(--color-female)"
                            radius={[0, 4, 4, 0]}
                            {...ENTER_ANIMATION}
                        />
                    </BarChart>
                </ChartContainer>
            </DashboardChartFrame>
            {otherTotal > 0 ? (
                <p className="mt-2 text-center text-[10px] text-muted-foreground">
                    {otherTotal.toLocaleString()}{' '}
                    {otherTotal === 1 ? 'request' : 'requests'} with other or
                    unspecified sex {otherTotal === 1 ? 'is' : 'are'} not drawn
                    on the pyramid.
                </p>
            ) : null}
        </div>
    );
}

export type ShareBarItem = {
    label: string;
    count: number;
    total: number;
};

export function ShareBarList({
    items,
    emptyMessage = 'No data with these filters.',
}: {
    items: ShareBarItem[];
    emptyMessage?: string;
}) {
    if (items.length === 0) {
        return <EmptyChart message={emptyMessage} />;
    }

    return (
        <ul className="flex flex-col gap-3">
            {items.map((item) => {
                const percent =
                    item.total > 0
                        ? Math.round((item.count / item.total) * 100)
                        : 0;

                return (
                    <li key={item.label} className="space-y-1.5">
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span>{item.label}</span>
                            <span className="shrink-0 text-muted-foreground tabular-nums">
                                {item.count.toLocaleString()} ({percent}%)
                            </span>
                        </div>
                        <Progress value={percent} />
                    </li>
                );
            })}
        </ul>
    );
}

const outcomesConfig = {
    delivered: { label: 'Delivered', color: 'var(--chart-2)' },
    in_progress: { label: 'In progress', color: 'var(--chart-1)' },
    denied: { label: 'Denied', color: 'var(--chart-5)' },
} satisfies ChartConfig;

export function ProgramOutcomesChart({
    data,
}: {
    data: DashboardProgramRow[];
}) {
    const rows = [...data]
        .sort((left, right) => right.total_requests - left.total_requests)
        .slice(0, 8)
        .map((row) => ({
            name: row.name,
            delivered: row.delivered,
            in_progress: row.in_progress,
            denied: row.denied,
        }));

    if (rows.length === 0) {
        return null;
    }

    const height = Math.max(220, rows.length * 36);

    return (
        <DashboardChartCard
            title="Program outcomes"
            description="Delivered, in progress, and denied requests for the busiest programs"
        >
            <DashboardChartFrame height={height}>
                <ChartContainer
                    config={outcomesConfig}
                    className="aspect-auto! h-full w-full"
                    initialDimension={{ width: 480, height }}
                >
                    <BarChart
                        data={rows}
                        layout="vertical"
                        margin={{ top: 4, right: 12, bottom: 0, left: 4 }}
                    >
                        <CartesianGrid horizontal={false} />
                        <XAxis
                            type="number"
                            allowDecimals={false}
                            tickLine={false}
                            axisLine={false}
                        />
                        <YAxis
                            type="category"
                            dataKey="name"
                            width={120}
                            tickLine={false}
                            axisLine={false}
                            tickFormatter={(value: string) =>
                                shortenLabel(value, 16)
                            }
                        />
                        <ChartTooltip content={<ChartTooltipContent />} />
                        <ChartLegend content={<ChartLegendContent />} />
                        <Bar
                            dataKey="delivered"
                            stackId="outcomes"
                            fill="var(--color-delivered)"
                            {...ENTER_ANIMATION}
                        />
                        <Bar
                            dataKey="in_progress"
                            stackId="outcomes"
                            fill="var(--color-in_progress)"
                            {...ENTER_ANIMATION}
                        />
                        <Bar
                            dataKey="denied"
                            stackId="outcomes"
                            fill="var(--color-denied)"
                            radius={[0, 4, 4, 0]}
                            {...ENTER_ANIMATION}
                        />
                    </BarChart>
                </ChartContainer>
            </DashboardChartFrame>
        </DashboardChartCard>
    );
}

export function RadialMetric({
    label,
    display,
    progress,
    description,
}: {
    label: string;
    display: string;
    progress: number;
    description: string;
}) {
    const clamped = Math.min(100, Math.max(0, progress));
    const config = {
        value: { label, color: 'var(--chart-1)' },
    } satisfies ChartConfig;

    return (
        <div className="flex items-center gap-3">
            <div className="relative size-24 shrink-0">
                <ChartContainer
                    config={config}
                    className="aspect-square size-full"
                    initialDimension={{ width: 96, height: 96 }}
                >
                    <RadialBarChart
                        data={[{ value: clamped, fill: 'var(--color-value)' }]}
                        startAngle={90}
                        endAngle={-270}
                        innerRadius={28}
                        outerRadius={42}
                    >
                        <PolarAngleAxis
                            type="number"
                            domain={[0, 100]}
                            allowDataOverflow
                            tick={false}
                        />
                        <RadialBar
                            dataKey="value"
                            background
                            cornerRadius={8}
                            {...ENTER_ANIMATION}
                        />
                    </RadialBarChart>
                </ChartContainer>
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <span className="text-[11px] font-semibold tabular-nums">
                        {display}
                    </span>
                </div>
            </div>
            <div className="flex min-w-0 flex-col gap-0.5">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}
