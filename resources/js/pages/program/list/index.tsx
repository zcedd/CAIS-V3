import { Chart } from '@/components/skeleton/chart';
import { ProjectCard } from '@/components/skeleton/project-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { create, index, show } from '@/routes/program';
import { type BreadcrumbItem } from '@/types';
import { Program } from '@/types/program';
import { Head, Link, router, WhenVisible } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InfiniteScroll from 'react-infinite-scroll-component';
import ChartStatus, { ChartData } from './chart-status';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Program List',
        href: index().url,
    },
];

export default function Index({ programs, search, perPage }: { programs: Program[]; search?: string; perPage?: string }) {
    const getChartData = (program: Program) => {
        const chartData: Array<ChartData> = [
            {
                status: 'Pending',
                count: program.pendingAssistance?.length ?? 0,
                fill: 'var(--color-pending)',
            },
            {
                status: 'Verified',
                count: program.verifiedAssistance?.length ?? 0,
                fill: 'var(--color-verified)',
            },
            {
                status: 'Delivered',
                count: program.deliveredAssistance?.length ?? 0,
                fill: 'var(--color-delivered)',
            },
            {
                status: 'Denied',
                count: program.deniedAssistance?.length ?? 0,
                fill: 'var(--color-denied)',
            },
        ];

        return chartData;
    };

    const fetchData = () => {
        setPerPageQuery('15');
        const params: { search?: string; per_page?: string } = {};

        if (searchQuery.trim()) {
            params.search = searchQuery;
        }

        if (perPage) {
            params.per_page = perPageQuery;
        }

        router.get(index().url, params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            only: ['programs'],
            showProgress: false,
        });
    };

    const [searchQuery, setSearchQuery] = useState(search ?? '');

    const [perPageQuery, setPerPageQuery] = useState(perPage ?? '');

    useEffect(() => {
        const handler = setTimeout(() => {
            const params: { search?: string; per_page?: string } = {};

            if (searchQuery.trim()) {
                params.search = searchQuery;
            }

            if (perPage) {
                params.per_page = perPage;
            }

            router.get(index().url, params, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['programs'],
                showProgress: false,
            });
        }, 500);

        return () => clearTimeout(handler);
    }, [searchQuery, perPage]);

    useEffect(() => {
        setSearchQuery(search ?? '');
    }, [search]);

    useEffect(() => {
        setPerPageQuery(perPage ?? '');
    }, [perPage]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Programs" />
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
                            <Link href={create().url}>Create Program</Link>
                        </Button>
                    </div>
                </div>
                <InfiniteScroll
                    dataLength={programs.length}
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
                        {programs &&
                            programs.map((program, idx) => (
                                <div key={program.id ?? idx}>
                                    <Link href={show(program.id).url}>
                                        <Card className="h-100">
                                            <CardHeader className="gap-0">
                                                <CardTitle className="text-lg">{program.name}</CardTitle>
                                                <CardDescription>{program.is_organization ? 'Organization' : 'Personal'}</CardDescription>
                                            </CardHeader>
                                            <CardContent className="text-sm">
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="">
                                                        <p>
                                                            <span className="font-bold">Details: </span>
                                                            {program.descriptions?.slice(0, 50)}
                                                            {program.descriptions && program.descriptions.length > 50 ? '...' : ''}
                                                        </p>
                                                        <p>
                                                            <span className="font-bold">Date: </span>
                                                            {program.date_started} {program.date_ended ? '- ' + program.date_ended : ''}
                                                        </p>
                                                        <p>
                                                            <span className="font-bold">No. of pending request: </span>
                                                            {program.pendingAssistance?.length ?? 0}
                                                        </p>
                                                        <p>
                                                            <span className="font-bold">No. of delivered request: </span>
                                                            {program.deliveredAssistance?.length ?? 0}
                                                        </p>
                                                        <p>
                                                            <span className="font-bold">No. of denied request: </span>
                                                            {program.deniedAssistance?.length ?? 0}
                                                        </p>
                                                    </div>
                                                    <div className="">
                                                        <WhenVisible data="program" fallback={() => <Chart />} key={program.id}>
                                                            <ChartStatus chartData={getChartData(program)} />
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
