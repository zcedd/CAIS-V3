import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { show as departmentProgramShow } from '@/routes/user/programs';
import type { DashboardProgramRow, DepartmentSummary } from '@/types/dashboard';
import { Link } from '@inertiajs/react';

type ProgramsTableProps = {
    department: DepartmentSummary;
    data: DashboardProgramRow[];
};

export function ProgramsTable({ department, data }: ProgramsTableProps) {
    return (
        <Card data-tour="dashboard-programs-summary">
            <CardHeader>
                <CardTitle>Programs summary</CardTitle>
                <CardDescription>
                    The 10 latest programs with request counts for the selected
                    filters
                </CardDescription>
            </CardHeader>
            <CardContent>
                {data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No programs found for this department.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Program</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Total requests
                                </TableHead>
                                <TableHead className="text-right">
                                    Delivered
                                </TableHead>
                                <TableHead className="text-right">
                                    In progress
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell>
                                        <Link
                                            href={departmentProgramShow.url({
                                                department: department.slug,
                                                program: row.id,
                                            })}
                                            prefetch
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {row.name}
                                        </Link>
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
                                        {row.in_progress.toLocaleString()}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
