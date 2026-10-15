import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import { DistributionOfferSummary } from '@/components/distribution/composition';
import type { DistributionOffer } from '@/components/distribution/composition';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
type TaskRow = {
    task_id: number;
    stage: string | null;
    batch_id: number | null;
    show_url: string | null;
    batch_code: string | null;
    title: string | null;
    program: string | null;
    department: string | null;
    scheduled_at: string | null;
    beneficiary_count: number | null;
    proposed_cash_total: string | null;
    offer: DistributionOffer | null;
};

export default function ExecutiveDistributionsIndex({
    tasks,
    inbox_url: inboxUrl,
}: {
    tasks: TaskRow[];
    inbox_url: string;
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Distributions', href: inboxUrl }],
        });
    }, [inboxUrl]);

    return (
        <>
            <Head title="Approval inbox" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Approval inbox
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pending distribution decisions assigned to you.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">
                            Pending decisions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Batch</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Stage</TableHead>
                                    <TableHead>Beneficiaries</TableHead>
                                    <TableHead>Assistance</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tasks.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            className="text-muted-foreground"
                                            colSpan={5}
                                        >
                                            No pending approval tasks.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {tasks.map((task) => (
                                    <TableRow key={task.task_id}>
                                        <TableCell>
                                            {task.show_url ? (
                                                <Link
                                                    href={task.show_url}
                                                    className="font-medium hover:underline"
                                                >
                                                    {task.batch_code}
                                                </Link>
                                            ) : (
                                                task.batch_code
                                            )}
                                            <div className="text-muted-foreground">
                                                {task.title} · {task.program}
                                            </div>
                                        </TableCell>
                                        <TableCell>{task.department}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {task.stage}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {task.beneficiary_count}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {task.offer ? (
                                                <DistributionOfferSummary
                                                    offer={task.offer}
                                                />
                                            ) : (
                                                'None'
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
