import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartConfig, ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import AppLayout from '@/layouts/app-layout';
import { create, index, show } from '@/routes/project';
import { type BreadcrumbItem } from '@/types';
import { Project } from '@/types/model';
import { Head, Link } from '@inertiajs/react';
import { Pie, PieChart } from 'recharts';

const chartData = [
    { browser: 'chrome', visitors: 0, fill: 'var(--color-chrome)' },
    { browser: 'safari', visitors: 0, fill: 'var(--color-safari)' },
    { browser: 'firefox', visitors: 3, fill: 'var(--color-firefox)' },
    { browser: 'edge', visitors: 0, fill: 'var(--color-edge)' },
    { browser: 'other', visitors: 1, fill: 'var(--color-other)' },
];

const chartConfig = {
    visitors: {
        label: 'Visitors',
    },
    chrome: {
        label: 'Chrome',
        color: 'var(--chart-1)',
    },
    safari: {
        label: 'Safari',
        color: 'var(--chart-2)',
    },
    firefox: {
        label: 'Firefox',
        color: 'var(--chart-3)',
    },
    edge: {
        label: 'Edge',
        color: 'var(--chart-4)',
    },
    other: {
        label: 'Other',
        color: 'var(--chart-5)',
    },
} satisfies ChartConfig;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Project List',
        href: index().url,
    },
];

export default function ProjectList({ Projects }: { Projects: Project[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Button asChild>
                    <Link href={create().url}>Create Project</Link>
                </Button>
                <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                    {Projects &&
                        Projects.map((project, idx) => (
                            <div key={project.id ?? idx}>
                                <Link href={show(project.id).url}>
                                    <Card>
                                        <CardHeader className="gap-0">
                                            <CardTitle className="text-lg">{project.name}</CardTitle>
                                            <CardDescription>{project.is_organization ? 'Organization' : 'Personal'}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="text-sm">
                                            <div className="grid grid-cols-2 gap-2">
                                                <div className="">
                                                    <p>
                                                        <span className="font-bold">Details: </span>
                                                        {project.descriptions?.slice(0, 50)}
                                                        {project.descriptions && project.descriptions.length > 50 ? '...' : ''}
                                                    </p>
                                                    <p>
                                                        <span className="font-bold">Date: </span>
                                                        {project.dateStarted} {project.dateEnded ? '- ' + project.dateEnded : ''}
                                                    </p>
                                                </div>
                                                <div className="">
                                                    <ChartContainer config={chartConfig} className="mx-auto aspect-square max-h-[250px]">
                                                        <PieChart>
                                                            <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel />} />
                                                            <Pie data={chartData} dataKey="visitors" nameKey="browser" />
                                                        </PieChart>
                                                    </ChartContainer>
                                                </div>
                                            </div>
                                        </CardContent>
                                        {/* <CardFooter>
                                            <p>Card Footer</p>
                                        </CardFooter> */}
                                    </Card>
                                </Link>
                            </div>
                        ))}
                </div>
                {/* <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div> */}
            </div>
        </AppLayout>
    );
}
