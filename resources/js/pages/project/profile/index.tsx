import { DataTable } from '@/components/data-table';
import { Table } from '@/components/skeleton/table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/project';
import { type BreadcrumbItem } from '@/types';
import { Assistance, Project } from '@/types/project';
import { Head, WhenVisible } from '@inertiajs/react';
import { organizationalDeliveredColumns } from './delivered-assistance/organization-delivered-column';
import { personalDeliveredColumns } from './delivered-assistance/personal-delivered-column';
import { organizationalPendingColumns } from './pending-assistance/organization-pending-column';
import { personalPendingColumns } from './pending-assistance/personal-pending-column';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Project List',
        href: index().url,
    },
    {
        title: 'Profile',
        href: show(1).url,
    },
];

export default function Index({
    project,
    personalPendingAssistance,
    organizationalPendingAssistance,
    personalDeliveredAssistance,
    organizationalDeliveredAssistance,
}: {
    project: Project;
    personalPendingAssistance: Assistance[];
    organizationalPendingAssistance: Assistance[];
    personalDeliveredAssistance: Assistance[];
    organizationalDeliveredAssistance: Assistance[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project Profile" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <WhenVisible data="project" fallback={() => <Table />}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {project.name}
                                <Badge variant="secondary">{project.is_organization ? 'Organization' : 'Personal'}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-2">
                                <span className="font-semibold">Description:</span>
                                <span className="ml-2 text-muted-foreground">{project.descriptions || 'No description provided.'}</span>
                            </div>
                            <div className="mb-2">
                                <span className="font-semibold">Date:</span>
                                <span className="ml-2 text-muted-foreground">
                                    <span>{project.dateStarted ?? 'N/A'}</span>
                                    <span>{project.dateEnded ? ' - ' + project.dateEnded : ''}</span>
                                </span>
                            </div>
                            <div className="mb-2">
                                <span className="font-semibold">Source of fund:</span>
                                <span className="ml-2 text-muted-foreground">
                                    {project.source_of_fund && project.source_of_fund.length > 0
                                        ? project.source_of_fund.map((project, idx: number) => (
                                              <div key={idx} className="">
                                                  {project.name}
                                              </div>
                                          ))
                                        : 'N/A'}
                                </span>
                            </div>
                        </CardContent>
                    </WhenVisible>
                </Card>

                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 p-3 md:min-h-min dark:border-sidebar-border">
                    <Tabs defaultValue="pending" className="w-full">
                        <TabsList>
                            <TabsTrigger value="pending">Pending</TabsTrigger>
                            <TabsTrigger value="verified">Verified</TabsTrigger>
                            <TabsTrigger value="delivered">Delivered</TabsTrigger>
                            <TabsTrigger value="denied">Denied</TabsTrigger>
                        </TabsList>
                        <TabsContent value="pending">
                            {project.is_organization ? (
                                <WhenVisible data="organizationalPendingAssistance" fallback={() => <Table />}>
                                    <DataTable columns={organizationalPendingColumns} data={organizationalPendingAssistance} />
                                </WhenVisible>
                            ) : (
                                <WhenVisible data="personalPendingAssistance" fallback={() => <Table />}>
                                    <DataTable columns={personalPendingColumns} data={personalPendingAssistance} />
                                </WhenVisible>
                            )}
                        </TabsContent>
                        <TabsContent value="verified">Verified.</TabsContent>
                        <TabsContent value="delivered">
                            {project.is_organization ? (
                                <WhenVisible data="organizationalDeliveredAssistance" fallback={() => <Table />}>
                                    <DataTable columns={organizationalDeliveredColumns} data={organizationalDeliveredAssistance} />
                                </WhenVisible>
                            ) : (
                                <WhenVisible data="personalDeliveredAssistance" fallback={() => <Table />}>
                                    <DataTable columns={personalDeliveredColumns} data={personalDeliveredAssistance} />
                                </WhenVisible>
                            )}
                        </TabsContent>
                        <TabsContent value="denied">Denied.</TabsContent>
                    </Tabs>
                </div>
            </div>
        </AppLayout>
    );
}
