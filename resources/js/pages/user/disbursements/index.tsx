import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    calendar,
    create,
    index as disbursementsIndex,
    show as disbursementShow,
} from '@/routes/user/disbursements';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type BatchRow = {
    id: number;
    batch_code: string;
    title: string;
    program: string | null;
    scheduled_at: string | null;
    status: string;
    status_label: string;
    approval_mode: string;
    beneficiary_count: number;
    approved_beneficiary_count: number;
    approved_cash_total: string;
    creator: string | null;
};

type Paginator = {
    data: BatchRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
    current_page: number;
    last_page: number;
};

type Option = { value: string; label: string };
type ProgramOption = { id: number; name: string };

function formatWhen(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function DisbursementsIndex({
    department,
    batches,
    filters,
    statuses,
    programs,
}: {
    department: DepartmentSummary;
    batches: Paginator;
    filters: Record<string, string | null>;
    statuses: Option[];
    programs: ProgramOption[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Disbursements',
                    href: disbursementsIndex.url(department.slug),
                },
            ],
        });
    }, [department.slug]);

    function applyFilters(next: Record<string, string>): void {
        router.get(
            disbursementsIndex.url(department.slug),
            { ...filters, ...next, search },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Disbursements" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Disbursement batches
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Group eligible requests, send them for approval,
                            then record the actual release.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={calendar.url(department.slug)}>
                                Calendar
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={create.url(department.slug)}>
                                New batch
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-4">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters({});
                            }
                        }}
                        placeholder="Search code or title"
                        aria-label="Search batches"
                    />
                    <Select
                        value={filters.status || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                status: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger aria-label="Status" className="w-full">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.program_id || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                program_id: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger aria-label="Program" className="w-full">
                            <SelectValue placeholder="All programs" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All programs</SelectItem>
                            {programs.map((program) => (
                                <SelectItem
                                    key={program.id}
                                    value={String(program.id)}
                                >
                                    {program.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.approval_mode || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                approval_mode: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label="Approval mode"
                            className="w-full"
                        >
                            <SelectValue placeholder="All approval modes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                All approval modes
                            </SelectItem>
                            <SelectItem value="batch">Whole batch</SelectItem>
                            <SelectItem value="beneficiary">
                                Individual beneficiary
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Batch</TableHead>
                                    <TableHead>Program</TableHead>
                                    <TableHead>Scheduled</TableHead>
                                    <TableHead>Beneficiaries</TableHead>
                                    <TableHead>Approved cash</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {batches.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            className="text-muted-foreground"
                                            colSpan={6}
                                        >
                                            No disbursement batches match these
                                            filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {batches.data.map((batch) => (
                                    <TableRow key={batch.id}>
                                        <TableCell>
                                            <Link
                                                href={disbursementShow.url({
                                                    department: department.slug,
                                                    disbursementBatch:
                                                        batch.id,
                                                })}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {batch.batch_code}
                                            </Link>
                                            <div className="text-muted-foreground">
                                                {batch.title}
                                            </div>
                                        </TableCell>
                                        <TableCell>{batch.program}</TableCell>
                                        <TableCell>
                                            {formatWhen(batch.scheduled_at)}
                                        </TableCell>
                                        <TableCell>
                                            {batch.approved_beneficiary_count}/
                                            {batch.beneficiary_count}
                                        </TableCell>
                                        <TableCell>
                                            {batch.approved_cash_total}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {batch.status_label}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">
                        {batches.total} batches
                    </span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={batches.prev_page_url === null}
                            onClick={() =>
                                batches.prev_page_url &&
                                router.visit(batches.prev_page_url)
                            }
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={batches.next_page_url === null}
                            onClick={() =>
                                batches.next_page_url &&
                                router.visit(batches.next_page_url)
                            }
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
