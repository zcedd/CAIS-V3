import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import { DistributionOfferSummary } from '@/components/distribution/composition';
import type { DistributionOffer } from '@/components/distribution/composition';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, show } from '@/routes/executive/distributions';

type TaskRow = {
    task_id: number;
    stage: string | null;
    batch_id: number | null;
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
}: {
    tasks: TaskRow[];
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Distributions', href: index.url() }],
        });
    }, []);

    return (
        <>
            <Head title="Approval inbox" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Approval inbox</h1>
                    <p className="text-sm text-muted-foreground">
                        Pending distribution decisions assigned to you.
                    </p>
                </div>

                <Card>
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
                                            {task.batch_id ? (
                                                <Link
                                                    href={show.url(
                                                        task.batch_id,
                                                    )}
                                                    className="font-medium underline-offset-4 hover:underline"
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
