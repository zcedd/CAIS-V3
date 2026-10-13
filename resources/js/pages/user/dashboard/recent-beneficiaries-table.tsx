import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DashboardChartCard } from '@/pages/user/dashboard/dashboard-charts';
import { show as beneficiaryShow } from '@/routes/user/beneficiaries';
import type {
    DashboardRecentBeneficiaryRow,
    DepartmentSummary,
} from '@/types/dashboard';

type RecentBeneficiariesTableProps = {
    department: DepartmentSummary;
    data: DashboardRecentBeneficiaryRow[];
};

function formatRequestedDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    const parsed = new Date(
        Number(match[1]),
        Number(match[2]) - 1,
        Number(match[3]),
    );

    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function locationLabel(row: DashboardRecentBeneficiaryRow): string {
    const parts = [row.barangay, row.city].filter(
        (part): part is string => part != null && part.trim() !== '',
    );

    return parts.length > 0 ? parts.join(', ') : '-';
}

export function RecentBeneficiariesTable({
    department,
    data,
}: RecentBeneficiariesTableProps) {
    return (
        <DashboardChartCard
            title="Recent beneficiaries"
            description="Latest people in these filters, from their most recent request"
            className="h-auto"
            data-tour="dashboard-recent-beneficiaries"
        >
            {data.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No beneficiaries found for these filters.
                </p>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Beneficiary</TableHead>
                            <TableHead>CAIS number</TableHead>
                            <TableHead>Location</TableHead>
                            <TableHead>Program</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Date requested</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {data.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell>
                                    <Link
                                        href={beneficiaryShow.url({
                                            department: department.slug,
                                            beneficiary: row.id,
                                        })}
                                        prefetch
                                        className="font-medium text-primary hover:underline"
                                    >
                                        {row.name}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {row.type === 'organization'
                                            ? 'Organization'
                                            : 'Individual'}
                                    </p>
                                </TableCell>
                                <TableCell className="font-mono text-xs">
                                    {row.cais_number}
                                </TableCell>
                                <TableCell>{locationLabel(row)}</TableCell>
                                <TableCell>{row.program}</TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {row.status}
                                    </Badge>
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {formatRequestedDate(row.date_requested)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </DashboardChartCard>
    );
}
