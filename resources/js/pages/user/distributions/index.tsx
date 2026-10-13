import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Can } from '@/components/auth/can';
import { DistributionOfferSummary } from '@/components/distribution/composition';
import type { DistributionOffer } from '@/components/distribution/composition';
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
import { Permission } from '@/lib/permissions';
import {
    calendar,
    create,
    index as distributionsIndex,
    show as distributionShow,
} from '@/routes/user/distributions';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type BatchRow = {
    id: number;
    batch_code: string;
    distribution_type_label: string;
    title: string;
    program: string | null;
    scheduled_at: string | null;
    status: string;
    status_label: string;
    beneficiary_count: number;
    approved_beneficiary_count: number;
    approved_cash_total: string;
    offer: DistributionOffer;
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
type DistributionSummary = {
    total: number;
    individual: number;
    batch: number;
    scheduled: number;
    pending_approval: number;
    in_progress: number;
    completed: number;
    cancelled: number;
    beneficiaries_scheduled: number;
    beneficiaries_released: number;
};

function SummaryCard({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-1 p-4">
                <span className="text-sm text-muted-foreground">{label}</span>
                <span className="text-2xl font-semibold tabular-nums">
                    {value}
                </span>
            </CardContent>
        </Card>
    );
}

function formatWhen(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function DistributionsIndex({
    department,
    batches,
    summary,
    filters,
    statuses,
    distribution_types,
    programs,
}: {
    department: DepartmentSummary;
    batches: Paginator;
    summary: DistributionSummary;
    filters: Record<string, string | null>;
    statuses: Option[];
    distribution_types: Option[];
    programs: ProgramOption[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Distributions',
                    href: distributionsIndex.url(department.slug),
                },
            ],
        });
    }, [department.slug]);

    function applyFilters(next: Record<string, string>): void {
        router.get(
            distributionsIndex.url(department.slug),
            { ...filters, ...next, search },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Distributions" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Distributions
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Individual distribution releases one approved
                            request directly. Batch distribution groups requests
                            and waits for executive approval.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            asChild
                            data-tour="distributions-calendar"
                        >
                            <Link href={calendar.url(department.slug)}>
                                Calendar
                            </Link>
                        </Button>
                        <Can permission={Permission.DistributionCreate}>
                            <Button
                                variant="outline"
                                asChild
                                data-tour="distributions-create-individual"
                            >
                                <Link
                                    href={create.url(department.slug, {
                                        query: { mode: 'individual' },
                                    })}
                                >
                                    Individual distribution
                                </Link>
                            </Button>
                            <Button asChild data-tour="distributions-create">
                                <Link
                                    href={create.url(department.slug, {
                                        query: { mode: 'batch' },
                                    })}
                                >
                                    Batch distribution
                                </Link>
                            </Button>
                        </Can>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SummaryCard label="Activities" value={summary.total} />
                    <SummaryCard
                        label="Individual"
                        value={summary.individual}
                    />
                    <SummaryCard label="Batch" value={summary.batch} />
                    <SummaryCard label="Scheduled" value={summary.scheduled} />
                    <SummaryCard
                        label="Pending approval"
                        value={summary.pending_approval}
                    />
                    <SummaryCard
                        label="In progress"
                        value={summary.in_progress}
                    />
                    <SummaryCard label="Completed" value={summary.completed} />
                    <SummaryCard label="Cancelled" value={summary.cancelled} />
                    <SummaryCard
                        label="Beneficiaries scheduled"
                        value={summary.beneficiaries_scheduled}
                    />
                    <SummaryCard
                        label="Beneficiaries released"
                        value={summary.beneficiaries_released}
                    />
                </div>

                <div
                    className="grid gap-3 md:grid-cols-4"
                    data-tour="distributions-filters"
                >
                    <Input
                        defaultValue={filters.venue ?? ''}
                        placeholder="Venue"
                        aria-label="Venue"
                        onBlur={(event) =>
                            applyFilters({ venue: event.target.value })
                        }
                    />
                    <Input
                        defaultValue={filters.beneficiary ?? ''}
                        placeholder="Beneficiary name or control number"
                        aria-label="Beneficiary"
                        onBlur={(event) =>
                            applyFilters({ beneficiary: event.target.value })
                        }
                    />
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
                        value={filters.distribution_type || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                distribution_type: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label="Distribution type"
                            className="w-full"
                        >
                            <SelectValue placeholder="All types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>
                            {distribution_types.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
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
                </div>

                <Card data-tour="distributions-table">
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Program</TableHead>
                                    <TableHead>Scheduled</TableHead>
                                    <TableHead>Beneficiaries</TableHead>
                                    <TableHead>Assistance</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {batches.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            className="text-muted-foreground"
                                            colSpan={7}
                                        >
                                            No distributions match these
                                            filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {batches.data.map((batch) => (
                                    <TableRow key={batch.id}>
                                        <TableCell>
                                            <Link
                                                href={distributionShow.url({
                                                    department: department.slug,
                                                    distributionBatch: batch.id,
                                                })}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {batch.batch_code}
                                            </Link>
                                            <div className="text-muted-foreground">
                                                {batch.title}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {batch.distribution_type_label}
                                        </TableCell>
                                        <TableCell>{batch.program}</TableCell>
                                        <TableCell>
                                            {formatWhen(batch.scheduled_at)}
                                        </TableCell>
                                        <TableCell>
                                            {batch.approved_beneficiary_count}/
                                            {batch.beneficiary_count}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            <DistributionOfferSummary
                                                offer={batch.offer}
                                            />
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
                        {batches.total} distributions
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
