import { Link } from '@inertiajs/react';
import { FolderKanban } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DashboardSectionCard } from '@/pages/user/dashboard/dashboard-stat-card';
import { show as executiveProgramShow } from '@/routes/executive/programs';
import type { DashboardProgramRow } from '@/types/dashboard';

type ExecutiveProgramRow = DashboardProgramRow & {
    department_name?: string | null;
};

type ProgramsTableProps = {
    data: ExecutiveProgramRow[];
};

export function ProgramsTable({ data }: ProgramsTableProps) {
    return (
        <DashboardSectionCard
            title="Programs"
            description="Recent programs, with delivered, denied, and in-progress counts"
            icon={FolderKanban}
            data-tour="dashboard-programs-summary"
        >
            {data.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No programs found.
                </p>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Program</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="text-right">
                                Requests
                            </TableHead>
                            <TableHead className="text-right">
                                Delivered
                            </TableHead>
                            <TableHead className="text-right">Denied</TableHead>
                            <TableHead className="text-right">
                                In progress
                            </TableHead>
                            <TableHead className="text-right">Rate</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {data.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell>
                                    <Link
                                        href={executiveProgramShow.url(row.id)}
                                        prefetch
                                        className="font-medium text-primary hover:underline"
                                    >
                                        {row.name}
                                    </Link>
                                    {row.batches && row.batches.length > 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            {row.batches.length}{' '}
                                            {row.batches.length === 1
                                                ? 'batch'
                                                : 'batches'}
                                        </p>
                                    ) : null}
                                </TableCell>
                                <TableCell>
                                    {row.department_name ?? '—'}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {row.type === 'organization'
                                            ? 'Organization'
                                            : 'Individual'}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant={
                                            row.status === 'open'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {row.status === 'open'
                                            ? 'Open'
                                            : 'Closed'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {row.total_requests.toLocaleString()}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {row.delivered.toLocaleString()}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {(row.denied ?? 0).toLocaleString()}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {row.in_progress.toLocaleString()}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {(row.delivery_rate ?? 0).toFixed(1)}%
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </DashboardSectionCard>
    );
}
