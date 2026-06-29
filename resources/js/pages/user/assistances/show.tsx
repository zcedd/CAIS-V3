import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    AssistanceStatusTimeline,
    type AssistanceStatusTimelineEntry,
} from '@/pages/user/assistances/assistance-status-timeline';
import { assistanceStatuses } from '@/pages/user/programs/assistance-data';
import { show as assistanceShow } from '@/routes/user/assistances';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProgramSummary = {
    id: number;
    name: string;
};

type AssistanceItem = {
    name: string;
    quantity: number | null;
    unit: string | null;
    specification: string | null;
    is_received: boolean;
};

type AssistanceProfile = {
    id: number;
    status: string;
    cais_number: string;
    beneficiary_name: string;
    beneficiary_type: 'Organization' | 'Personal' | null;
    mode_of_request: string;
    date_requested: string | null;
    date_verified: string | null;
    date_delivered: string | null;
    date_denied: string | null;
    remark: string | null;
    items: AssistanceItem[];
    status_history: AssistanceStatusTimelineEntry[];
};

function formatItemAmount(item: AssistanceItem): string | null {
    if (item.quantity !== null && item.unit) {
        return `${item.quantity} ${item.unit}`;
    }

    if (item.quantity !== null) {
        return String(item.quantity);
    }

    if (item.unit) {
        return item.unit;
    }

    return null;
}

export default function UserAssistanceShow({
    department,
    program,
    assistance,
}: {
    department: DepartmentSummary;
    program: ProgramSummary;
    assistance: AssistanceProfile;
}) {
    const statusOption = assistanceStatuses.find(
        (entry) => entry.value === assistance.status,
    );
    const StatusIcon = statusOption?.icon;

    useEffect(() => {
        const programsHref = departmentProgramsIndex.url(department.slug);
        const programHref = departmentProgramShow.url({
            department: department.slug,
            program: program.id,
        });
        const selfHref = assistanceShow.url({
            department: department.slug,
            program: program.id,
            assistance: assistance.id,
        });

        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Programs',
                    href: programsHref,
                },
                {
                    title: program.name,
                    href: programHref,
                },
                {
                    title: assistance.cais_number,
                    href: selfHref,
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [
        assistance.cais_number,
        assistance.id,
        department.slug,
        program.id,
        program.name,
    ]);

    const heading =
        assistance.cais_number !== '—'
            ? assistance.cais_number
            : `Assistance #${assistance.id}`;

    return (
        <>
            <Head title={heading} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Assistance profile for {program.name}.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link
                            href={departmentProgramShow.url({
                                department: department.slug,
                                program: program.id,
                            })}
                        >
                            Back to program
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Overview</CardTitle>
                        <CardDescription>
                            Request status and key dates.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            {StatusIcon ? (
                                <StatusIcon className="h-4 w-4 text-muted-foreground" />
                            ) : null}
                            <span className="font-medium">
                                {statusOption?.label ?? assistance.status}
                            </span>
                            {assistance.beneficiary_type ? (
                                <Badge variant="secondary">
                                    {assistance.beneficiary_type}
                                </Badge>
                            ) : null}
                            <Badge variant="outline">
                                {assistance.mode_of_request}
                            </Badge>
                        </div>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt className="font-medium text-foreground">
                                    Requested
                                </dt>
                                <dd className="text-muted-foreground tabular-nums">
                                    {assistance.date_requested ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-medium text-foreground">
                                    Verified
                                </dt>
                                <dd className="text-muted-foreground tabular-nums">
                                    {assistance.date_verified ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-medium text-foreground">
                                    Delivered
                                </dt>
                                <dd className="text-muted-foreground tabular-nums">
                                    {assistance.date_delivered ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-medium text-foreground">
                                    Denied
                                </dt>
                                <dd className="text-muted-foreground tabular-nums">
                                    {assistance.date_denied ?? '—'}
                                </dd>
                            </div>
                        </dl>
                        <div>
                            <p className="font-medium text-foreground">
                                Remark
                            </p>
                            <p className="whitespace-pre-wrap text-muted-foreground">
                                {assistance.remark?.trim()
                                    ? assistance.remark
                                    : '—'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">
                            Assistance tracking
                        </CardTitle>
                        <CardDescription>
                            Timeline of sub-status updates for this request,
                            oldest to newest.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <AssistanceStatusTimeline
                            entries={assistance.status_history}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Beneficiary</CardTitle>
                        <CardDescription>
                            Recipient linked to this assistance record.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2 text-sm">
                        <p>
                            <span className="font-medium text-foreground">
                                CAIS number:{' '}
                            </span>
                            <span className="font-mono text-xs">
                                {assistance.cais_number}
                            </span>
                        </p>
                        <p>
                            <span className="font-medium text-foreground">
                                Name:{' '}
                            </span>
                            <span className="text-muted-foreground">
                                {assistance.beneficiary_name}
                            </span>
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">
                            Items requested
                        </CardTitle>
                        <CardDescription>
                            Goods or services included in this assistance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {assistance.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No items listed for this assistance.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Specification</TableHead>
                                        <TableHead>Received</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assistance.items.map((item, index) => {
                                        const amount = formatItemAmount(item);

                                        return (
                                            <TableRow
                                                key={`${item.name}-${index}`}
                                            >
                                                <TableCell className="font-medium">
                                                    {item.name}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground tabular-nums">
                                                    {amount ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {item.specification?.trim()
                                                        ? item.specification
                                                        : '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {item.is_received
                                                        ? 'Yes'
                                                        : 'No'}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
