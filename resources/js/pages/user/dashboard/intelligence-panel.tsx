import { Banknote, Package, Truck, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent
    
} from '@/components/ui/chart';
import type {ChartConfig} from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { StatCard, StatCardSkeleton } from '@/components/user/stat-card';
import { formatPeso } from '@/lib/format-peso';
import type {
    CityChartPoint,
    DashboardDemographics,
    DashboardSummary,
} from '@/types/dashboard';

const cityConfig = {
    count: { label: 'Beneficiaries', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const ageColors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

export function IntelligencePanel({
    summary,
    cities,
    demographics,
    map,
}: {
    summary: DashboardSummary;
    cities: CityChartPoint[];
    demographics: DashboardDemographics | null;
    map?: ReactNode;
}) {
    const ageData = (demographics?.age ?? []).filter((row) => row.count > 0);
    const sectors = sectorTiles(demographics);

    return (
        <div className="space-y-4" data-tour="dashboard-assistance-overview">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Total cash disbursed"
                    value={formatPeso(summary.cash_disbursed)}
                    description="Released cash assistance"
                    icon={Banknote}
                />
                <StatCard
                    label="In-kind packages"
                    value={summary.goods_released.toLocaleString()}
                    description="Goods quantity released"
                    icon={Package}
                />
                <StatCard
                    label="Registered heads"
                    value={summary.unique_beneficiaries.toLocaleString()}
                    description="Distinct beneficiaries in these filters"
                    icon={Users}
                />
                <StatCard
                    label="Delivered requests"
                    value={summary.delivered_requests.toLocaleString()}
                    description={`${summary.total_requests.toLocaleString()} requests in view`}
                    icon={Truck}
                />
            </div>

            <section className="rounded-xl border border-border bg-card p-4">
                <h2 className="text-sm font-medium">
                    Social vulnerability categories
                </h2>
                <p className="mb-3 text-xs text-muted-foreground">
                    Counts of requests whose beneficiary matches each flag.
                </p>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {sectors.map((sector) => (
                        <div
                            key={sector.label}
                            className="rounded-lg border border-border bg-muted/30 p-3"
                        >
                            <p className="text-sm font-medium">{sector.label}</p>
                            <p className="mt-2 text-2xl font-semibold tabular-nums">
                                {sector.count.toLocaleString()}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                requests
                            </p>
                        </div>
                    ))}
                </div>
            </section>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="h-full rounded-xl border border-border bg-card p-4">
                    <h2 className="text-sm font-medium">
                        Beneficiaries by municipality
                    </h2>
                    <p className="mb-3 text-xs text-muted-foreground">
                        Distinct requests grouped by city.
                    </p>
                    {cities.length === 0 ? (
                        <p className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                            No city is recorded for these filters.
                        </p>
                    ) : (
                        <>
                            <ChartContainer
                                config={cityConfig}
                                className="aspect-auto h-64 w-full"
                            >
                                <BarChart data={cities}>
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="city"
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
                                    <ChartTooltip
                                        content={<ChartTooltipContent />}
                                    />
                                    <Bar dataKey="count" radius={4}>
                                        {cities.map((row, index) => (
                                            <Cell
                                                key={row.city}
                                                fill={
                                                    ageColors[
                                                        index %
                                                            ageColors.length
                                                    ]
                                                }
                                            />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ChartContainer>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {cities.map((row, index) => (
                                    <span
                                        key={row.city}
                                        className="inline-flex items-center gap-1.5 text-[10px] text-muted-foreground"
                                    >
                                        <span
                                            className="size-2 rounded-full"
                                            style={{
                                                background:
                                                    ageColors[
                                                        index %
                                                            ageColors.length
                                                    ],
                                            }}
                                        />
                                        {row.city} ({row.count})
                                    </span>
                                ))}
                            </div>
                        </>
                    )}
                </section>

                <section className="h-full rounded-xl border border-border bg-card p-4">
                    <h2 className="text-sm font-medium">Age distribution</h2>
                    <p className="mb-3 text-xs text-muted-foreground">
                        Ages of individual beneficiaries on these requests.
                    </p>
                    {ageData.length === 0 ? (
                        <p className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                            No age is recorded for these filters.
                        </p>
                    ) : (
                        <ChartContainer
                            config={{
                                count: { label: 'People', color: 'var(--chart-2)' },
                            }}
                            className="aspect-auto h-64 w-full"
                        >
                            <PieChart>
                                <ChartTooltip
                                    content={<ChartTooltipContent />}
                                />
                                <Pie
                                    data={ageData}
                                    dataKey="count"
                                    nameKey="label"
                                    innerRadius={58}
                                    outerRadius={88}
                                    paddingAngle={2}
                                >
                                    {ageData.map((row, index) => (
                                        <Cell
                                            key={row.label}
                                            fill={
                                                ageColors[
                                                    index % ageColors.length
                                                ]
                                            }
                                        />
                                    ))}
                                </Pie>
                            </PieChart>
                        </ChartContainer>
                    )}
                    <div className="mt-2 flex flex-wrap gap-2">
                        {ageData.map((row, index) => (
                            <span
                                key={row.label}
                                className="inline-flex items-center gap-1.5 text-[10px] text-muted-foreground"
                            >
                                <span
                                    className="size-2 rounded-full"
                                    style={{
                                        background:
                                            ageColors[index % ageColors.length],
                                    }}
                                />
                                {row.label} ({row.count})
                            </span>
                        ))}
                    </div>
                </section>
                {map}
            </div>
        </div>
    );
}

export function IntelligencePanelSkeleton() {
    return (
        <div
            className="space-y-4"
            aria-busy="true"
            aria-label="Loading assistance overview"
        >
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }, (_, index) => (
                    <StatCardSkeleton key={index} />
                ))}
            </div>

            <section className="rounded-xl border border-border bg-card p-4">
                <Skeleton className="h-4 w-56" />
                <Skeleton className="mt-2 h-3 w-80 max-w-full" />
                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {Array.from({ length: 5 }, (_, index) => (
                        <div
                            key={index}
                            className="rounded-lg border border-border bg-muted/30 p-3"
                        >
                            <Skeleton className="h-4 w-24" />
                            <Skeleton className="mt-2 h-8 w-12" />
                            <Skeleton className="mt-1 h-3 w-16" />
                        </div>
                    ))}
                </div>
            </section>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="h-full rounded-xl border border-border bg-card p-4">
                    <Skeleton className="h-4 w-48" />
                    <Skeleton className="mt-2 h-3 w-64" />
                    <Skeleton className="mt-3 h-64 w-full" />
                </section>
                <section className="h-full rounded-xl border border-border bg-card p-4">
                    <Skeleton className="h-4 w-36" />
                    <Skeleton className="mt-2 h-3 w-72" />
                    <Skeleton className="mx-auto mt-3 size-64 rounded-full" />
                </section>
                <section className="h-full rounded-xl border border-border bg-card p-4">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="mt-2 h-3 w-48" />
                    <Skeleton className="mt-3 h-64 w-full" />
                </section>
            </div>
        </div>
    );
}

function sectorTiles(demographics: DashboardDemographics | null): {
    label: string;
    count: number;
}[] {
    const yesCount = (
        rows: { label: string; count: number }[] | undefined,
    ): number => rows?.find((row) => row.label === 'Yes')?.count ?? 0;

    const senior =
        demographics?.age.find((row) => row.label === '60+')?.count ?? 0;

    return [
        { label: 'Person with Disability', count: yesCount(demographics?.pwd) },
        { label: 'Solo Parent', count: yesCount(demographics?.solo_parent) },
        { label: '4Ps Beneficiary', count: yesCount(demographics?.four_ps) },
        { label: 'Indigenous', count: yesCount(demographics?.indigenous) },
        { label: 'Senior Citizen (60+)', count: senior },
    ];
}
