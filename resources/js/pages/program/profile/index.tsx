import { DataTable } from '@/components/data-table';
import { Table } from '@/components/skeleton/table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/program';
import { type BreadcrumbItem } from '@/types';
import { type Assistance, type Program } from '@/types/program';
import { Head, WhenVisible } from '@inertiajs/react';
import { organizationalDeliveredColumns } from './delivered-assistance/organization-delivered-column';
import { personalDeliveredColumns } from './delivered-assistance/personal-delivered-column';
import { organizationalPendingColumns } from './pending-assistance/organization-pending-column';
import { personalPendingColumns } from './pending-assistance/personal-pending-column';

export default function Index({
    program,
    personalPendingAssistance,
    organizationalPendingAssistance,
    personalDeliveredAssistance,
    organizationalDeliveredAssistance,
    personalDeniedAssistance,
    organizationalDeniedAssistance,
}: {
    program: Program;
    personalPendingAssistance: Assistance[];
    organizationalPendingAssistance: Assistance[];
    personalDeliveredAssistance: Assistance[];
    organizationalDeliveredAssistance: Assistance[];
    personalDeniedAssistance: Assistance[];
    organizationalDeniedAssistance: Assistance[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Program List', href: index().url },
        { title: 'Profile', href: show(program.id).url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Program Profile" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <WhenVisible data="program" fallback={() => <Table />}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {program.name}
                                <Badge variant="secondary">{program.is_organization ? 'Organization' : 'Personal'}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-2">
                                <span className="font-semibold">Description:</span>
                                <span className="ml-2 text-muted-foreground">{program.descriptions || 'No description provided.'}</span>
                            </div>
                            <div className="mb-2">
                                <span className="font-semibold">Date:</span>
                                <span className="ml-2 text-muted-foreground">
                                    <span>{program.date_started ?? 'N/A'}</span>
                                    <span>{program.date_ended ? ' - ' + program.date_ended : ''}</span>
                                </span>
                            </div>
                            <div className="mb-2">
                                <span className="font-semibold">Source of fund:</span>
                                <span className="ml-2 text-muted-foreground">
                                    {program.sourceOfFund && program.sourceOfFund.length > 0
                                        ? program.sourceOfFund.map((fund: { name: string }, idx: number) => (
                                              <div key={idx} className="">
                                                  {fund.name}
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
                            {program.is_organization ? (
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
                            {program.is_organization ? (
                                <WhenVisible data="organizationalDeliveredAssistance" fallback={() => <Table />}>
                                    <DataTable columns={organizationalDeliveredColumns} data={organizationalDeliveredAssistance} />
                                </WhenVisible>
                            ) : (
                                <WhenVisible data="personalDeliveredAssistance" fallback={() => <Table />}>
                                    <DataTable columns={personalDeliveredColumns} data={personalDeliveredAssistance} />
                                </WhenVisible>
                            )}
                        </TabsContent>
                        <TabsContent value="denied">
                            {program.is_organization ? (
                                <WhenVisible data="organizationalDeniedAssistance" fallback={() => <Table />}>
                                    <DataTable columns={organizationalPendingColumns} data={organizationalDeniedAssistance} />
                                </WhenVisible>
                            ) : (
                                <WhenVisible data="personalDeniedAssistance" fallback={() => <Table />}>
                                    <DataTable columns={personalPendingColumns} data={personalDeniedAssistance} />
                                </WhenVisible>
                            )}
                        </TabsContent>
                    </Tabs>
                </div>
            </div>
        </AppLayout>
    );
}
