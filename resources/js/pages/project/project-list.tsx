import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { create, index, show } from '@/routes/project';
import { type BreadcrumbItem } from '@/types';
import { Project } from '@/types/model';
import { Head, Link } from '@inertiajs/react';
import ChartStatus, { ChartData } from './chart-status';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Project List',
        href: index().url,
    },
];

export default function ProjectList({ Projects }: { Projects: Project[] }) {
    const getChartData = (Project: Project) => {
        let chartData: Array<ChartData> = [
            {
                status: 'Pending',
                count: Project.pending_assistance ? Project.pending_assistance.length : 0,
                fill: 'var(--color-pending)',
            },
            {
                status: 'Verified',
                count: Project.verified_assistance ? Project.verified_assistance.length : 0,
                fill: 'var(--color-verified)',
            },
            {
                status: 'Delivered',
                count: Project.delivered_assistance ? Project.delivered_assistance.length : 0,
                fill: 'var(--color-delivered)',
            },
            {
                status: 'Denied',
                count: Project.denied_assistance ? Project.denied_assistance.length : 0,
                fill: 'var(--color-denied)',
            },
        ];

        return chartData;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Button asChild>
                    <Link href={create().url}>Create Project</Link>
                </Button>
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {Projects &&
                        Projects.map((project, idx) => (
                            <div key={project.id ?? idx}>
                                <Link href={show(project.id).url}>
                                    <Card className="h-100">
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
                                                    <p>
                                                        <span className="font-bold">No. of pending request: </span>
                                                        {project.pending_assistance ? project.pending_assistance.length : 0}
                                                    </p>
                                                    <p>
                                                        <span className="font-bold">No. of delivered request: </span>
                                                        {project.delivered_assistance ? project.delivered_assistance.length : 0}
                                                    </p>
                                                    <p>
                                                        <span className="font-bold">No. of denied request: </span>
                                                        {project.denied_assistance ? project.denied_assistance.length : 0}
                                                    </p>
                                                </div>
                                                <div className="">
                                                    <ChartStatus chartData={getChartData(project)} />
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
