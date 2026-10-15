import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    calendar,
    index as distributionsIndex,
    show,
} from '@/routes/user/distributions';
import type { BreadcrumbItem } from '@/types';

type DepartmentSummary = { slug: string; name: string };

type CalendarBatch = {
    id: number;
    batch_code: string;
    title: string;
    program: string | null;
    venue: string | null;
    scheduled_at: string | null;
    status: string;
    status_label: string;
};

const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const;

function shiftMonth(month: string, delta: number): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + delta, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function dayKey(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function monthLabel(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);

    return new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, monthNumber - 1, 1));
}

function monthCells(month: string): { key: string; label: number; inMonth: boolean }[] {
    const [year, monthNumber] = month.split('-').map(Number);
    const first = new Date(year, monthNumber - 1, 1);
    const start = new Date(first);
    start.setDate(1 - first.getDay());
    const cells: { key: string; label: number; inMonth: boolean }[] = [];

    for (let index = 0; index < 42; index += 1) {
        const day = new Date(start);
        day.setDate(start.getDate() + index);
        cells.push({
            key: dayKey(day),
            label: day.getDate(),
            inMonth: day.getMonth() === monthNumber - 1,
        });
    }

    return cells;
}

export default function DistributionCalendar({
    department,
    month,
    batches,
}: {
    department: DepartmentSummary;
    month: string;
    batches: CalendarBatch[];
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Distributions',
                    href: distributionsIndex.url(department.slug),
                },
                {
                    title: 'Calendar',
                    href: calendar.url(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    const grouped = useMemo(() => {
        const days = new Map<string, CalendarBatch[]>();

        for (const batch of batches) {
            if (!batch.scheduled_at) {
                continue;
            }

            const key = dayKey(new Date(batch.scheduled_at));
            days.set(key, [...(days.get(key) ?? []), batch]);
        }

        return days;
    }, [batches]);

    const cells = useMemo(() => monthCells(month), [month]);
    const today = dayKey(new Date());

    function visit(nextMonth: string): void {
        router.get(
            calendar.url(department.slug, { query: { month: nextMonth } }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Distribution calendar" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Distribution calendar
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Approved and scheduled events for {department.name}.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Previous month"
                            onClick={() => visit(shiftMonth(month, -1))}
                        >
                            <ChevronLeft />
                        </Button>
                        <span className="min-w-36 text-center text-sm font-medium">
                            {monthLabel(month)}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Next month"
                            onClick={() => visit(shiftMonth(month, 1))}
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                </div>

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <div className="grid grid-cols-7 border-b border-border bg-muted/40">
                        {weekdays.map((day) => (
                            <div
                                key={day}
                                className="px-2 py-2 text-center text-xs font-medium text-muted-foreground"
                            >
                                {day}
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-7">
                        {cells.map((cell) => {
                            const events = grouped.get(cell.key) ?? [];

                            return (
                                <div
                                    key={cell.key}
                                    className={cn(
                                        'min-h-28 border-r border-b border-border p-2 last:border-r-0',
                                        !cell.inMonth && 'bg-muted/30',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'mb-1 flex size-6 items-center justify-center rounded-full text-xs tabular-nums',
                                            cell.key === today &&
                                                'bg-primary text-primary-foreground',
                                            !cell.inMonth &&
                                                cell.key !== today &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {cell.label}
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        {events.map((batch) => (
                                            <Link
                                                key={batch.id}
                                                href={show.url({
                                                    department: department.slug,
                                                    distributionBatch: batch.id,
                                                })}
                                                className="rounded-md bg-muted px-1.5 py-1 text-[11px] leading-tight hover:bg-muted/80"
                                            >
                                                <span className="block truncate font-medium">
                                                    {batch.batch_code}
                                                </span>
                                                <span className="block truncate text-muted-foreground">
                                                    {batch.title}
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    {batches.length === 0 ? (
                        <p className="border-t border-border px-4 py-3 text-sm text-muted-foreground">
                            No approved or scheduled distributions in this
                            month.
                        </p>
                    ) : null}
                </section>
            </div>
        </>
    );
}
