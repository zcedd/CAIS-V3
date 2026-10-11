import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { index, show } from '@/routes/executive/dispersals';

type TaskRow = {
    task_id: number;
    stage: string | null;
    batch_id: number | null;
    batch_code: string | null;
    title: string | null;
    program: string | null;
    department: string | null;
    approval_mode: string | null;
    scheduled_at: string | null;
    beneficiary_count: number | null;
    proposed_cash_total: string | null;
};

export default function ExecutiveDispersalsIndex({
    tasks,
}: {
    tasks: TaskRow[];
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Dispersals', href: index.url() }],
        });
    }, []);

    return (
        <>
            <Head title="Approval inbox" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Approval inbox</h1>
                    <p className="text-sm text-muted-foreground">
                        Pending dispersal decisions assigned to you.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Batch</th>
                                <th className="px-3 py-2">Department</th>
                                <th className="px-3 py-2">Stage</th>
                                <th className="px-3 py-2">Beneficiaries</th>
                                <th className="px-3 py-2">Proposed cash</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tasks.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-6 text-muted-foreground"
                                        colSpan={5}
                                    >
                                        No pending approval tasks.
                                    </td>
                                </tr>
                            )}
                            {tasks.map((task) => (
                                <tr key={task.task_id} className="border-t">
                                    <td className="px-3 py-2">
                                        {task.batch_id ? (
                                            <Link
                                                href={show.url(task.batch_id)}
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
                                    </td>
                                    <td className="px-3 py-2">
                                        {task.department}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="outline">
                                            {task.stage}
                                        </Badge>
                                        <div className="text-muted-foreground">
                                            {task.approval_mode ===
                                            'beneficiary'
                                                ? 'Individual review'
                                                : 'Whole batch'}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {task.beneficiary_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        {task.proposed_cash_total}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
