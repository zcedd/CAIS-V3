import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    calendar,
    create,
    index as dispersalsIndex,
    show as dispersalShow,
} from '@/routes/user/dispersals';

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

export default function DispersalsIndex({
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
                    title: 'Dispersals',
                    href: dispersalsIndex.url(department.slug),
                },
            ],
        });
    }, [department.slug]);

    function applyFilters(next: Record<string, string>): void {
        router.get(
            dispersalsIndex.url(department.slug),
            { ...filters, ...next, search },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Dispersals" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Dispersal batches
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

                <form
                    className="grid gap-3 md:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters({});
                    }}
                >
                    <input
                        className="rounded-md border bg-background px-3 py-2 text-sm"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search code or title"
                        aria-label="Search batches"
                    />
                    <select
                        className="rounded-md border bg-background px-3 py-2 text-sm"
                        value={filters.status ?? ''}
                        aria-label="Status"
                        onChange={(event) =>
                            applyFilters({ status: event.target.value })
                        }
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className="rounded-md border bg-background px-3 py-2 text-sm"
                        value={filters.program_id ?? ''}
                        aria-label="Program"
                        onChange={(event) =>
                            applyFilters({ program_id: event.target.value })
                        }
                    >
                        <option value="">All programs</option>
                        {programs.map((program) => (
                            <option key={program.id} value={program.id}>
                                {program.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className="rounded-md border bg-background px-3 py-2 text-sm"
                        value={filters.approval_mode ?? ''}
                        aria-label="Approval mode"
                        onChange={(event) =>
                            applyFilters({
                                approval_mode: event.target.value,
                            })
                        }
                    >
                        <option value="">All approval modes</option>
                        <option value="batch">Whole batch</option>
                        <option value="beneficiary">
                            Individual beneficiary
                        </option>
                    </select>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Batch</th>
                                <th className="px-3 py-2">Program</th>
                                <th className="px-3 py-2">Scheduled</th>
                                <th className="px-3 py-2">Beneficiaries</th>
                                <th className="px-3 py-2">Approved cash</th>
                                <th className="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {batches.data.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-6 text-muted-foreground"
                                        colSpan={6}
                                    >
                                        No dispersal batches match these
                                        filters.
                                    </td>
                                </tr>
                            )}
                            {batches.data.map((batch) => (
                                <tr key={batch.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link
                                            href={dispersalShow.url({
                                                department: department.slug,
                                                dispersalBatch: batch.id,
                                            })}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {batch.batch_code}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {batch.title}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {batch.program}
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatWhen(batch.scheduled_at)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {batch.approved_beneficiary_count}/
                                        {batch.beneficiary_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        {batch.approved_cash_total}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="outline">
                                            {batch.status_label}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

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
