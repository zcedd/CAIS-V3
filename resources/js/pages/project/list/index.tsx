import { Chart } from '@/components/skeleton/chart';
import { ProjectCard } from '@/components/skeleton/project-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { create, index, show } from '@/routes/project';
import { type BreadcrumbItem } from '@/types';
import { Project } from '@/types/project';
import { Head, Link, router, WhenVisible } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InfiniteScroll from 'react-infinite-scroll-component';
import ChartStatus, { ChartData } from './chart-status';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Project List',
        href: index().url,
    },
];

export default function Index({ Projects, search, perPage }: { Projects: Project[]; search: string; perPage: string }) {
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

    const fetchData = () => {
        setPerPageQuery('15');
        const params: { search?: string; perPage?: string } = {};

        if (searchQuery.trim()) {
            params.search = searchQuery;
        }

        if (perPage) {
            params.perPage = perPageQuery;
        }

        console.log(params);
        router.get(index().url, params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            only: ['Projects'],
            showProgress: false,
        });
    };

    const [searchQuery, setSearchQuery] = useState(search || '');

    const [perPageQuery, setPerPageQuery] = useState(perPage || '');

    useEffect(() => {
        const handler = setTimeout(() => {
            const params: { search?: string; perPage?: string } = {};

            if (searchQuery.trim()) {
                params.search = searchQuery;
            }

            if (perPage) {
                params.perPage = perPage;
            }

            console.log(perPage);

            router.get(index().url, params, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['Projects'],
                showProgress: false,
            });
        }, 500);

        return () => clearTimeout(handler);
    }, [searchQuery, perPage]);

    useEffect(() => {
        setSearchQuery(search || '');
    }, [search]);

    useEffect(() => {
        setPerPageQuery(perPage || '0');
    }, [perPage]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid grid-cols-2">
                    <div className="flex justify-start">
                        <input
                            type="text"
                            name="search"
                            placeholder="Search"
                            className="w-full rounded-md border border-gray-500 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none focus:border-[#033284] focus:ring-1 focus:ring-[#0242b3d2]"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                        />{' '}
                    </div>
                    <div className="flex justify-end">
                        <Button asChild>
                            <Link href={create().url}>Create Project</Link>
                        </Button>
                    </div>
                </div>
                <InfiniteScroll
                    dataLength={Projects.length} //This is important field to render the next data
                    next={fetchData}
                    hasMore={true}
                    loader={
                        <div className="mt-4 grid auto-rows-min gap-4 md:grid-cols-3">
                            <ProjectCard />
                            <ProjectCard />
                            <ProjectCard />
                        </div>
                    }
                    endMessage={
                        <p style={{ textAlign: 'center' }}>
                            <b>Yay! You have seen it all</b>
                        </p>
                    }
                >
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
                                                        <WhenVisible data="project" fallback={() => <Chart />} key={project.id}>
                                                            <ChartStatus chartData={getChartData(project)} />
                                                        </WhenVisible>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </Link>
                                </div>
                            ))}
                    </div>
                </InfiniteScroll>
            </div>
        </AppLayout>
    );
}
