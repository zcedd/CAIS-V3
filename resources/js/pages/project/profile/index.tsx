import { DataTable } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/project';
import { type BreadcrumbItem } from '@/types';
import { Assistance, Project } from '@/types/project';
import { Head } from '@inertiajs/react';
import { columns } from './personal-assistance-table';

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

export default function Index({ Project, PendingAssistance }: { Project: Project; PendingAssistance: Assistance[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project Profile" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {Project.name}
                            <Badge variant="secondary">{Project.is_organization ? 'Organization' : 'Personal'}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-2">
                            <span className="font-semibold">Description:</span>
                            <span className="ml-2 text-muted-foreground">{Project.descriptions || 'No description provided.'}</span>
                        </div>
                        <div className="mb-2">
                            <span className="font-semibold">Date:</span>
                            <span className="ml-2 text-muted-foreground">
                                <span>{Project.dateStarted ?? 'N/A'}</span>
                                <span>{Project.dateEnded ? ' - ' + Project.dateEnded : ''}</span>
                            </span>
                        </div>
                        <div className="mb-2">
                            <span className="font-semibold">Source of fund:</span>
                            <span className="ml-2 text-muted-foreground">
                                {Project.source_of_fund && Project.source_of_fund.length > 0
                                    ? Project.source_of_fund.map((source, idx: number) => (
                                          <div key={idx} className="">
                                              {source.name}
                                          </div>
                                      ))
                                    : 'N/A'}
                            </span>
                        </div>
                    </CardContent>
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
                            <DataTable columns={columns} data={PendingAssistance} />
                        </TabsContent>
                        <TabsContent value="verified">Verified.</TabsContent>
                        <TabsContent value="delivered">Delivered.</TabsContent>
                        <TabsContent value="denied">Denied.</TabsContent>
                    </Tabs>
                </div>
            </div>
        </AppLayout>
    );
}
