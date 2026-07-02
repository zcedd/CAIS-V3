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
import type { DashboardProgramRow } from '@/types/dashboard';

type ProgramsTableProps = {
    data: DashboardProgramRow[];
};

export function ProgramsTable({ data }: ProgramsTableProps) {
    return (
        <Card data-tour="dashboard-programs-summary">
            <CardHeader>
                <CardTitle>Programs summary</CardTitle>
                <CardDescription>
                    Request counts per program for the selected demographic
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
                                    <TableCell className="font-medium">
                                        {row.name}
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
