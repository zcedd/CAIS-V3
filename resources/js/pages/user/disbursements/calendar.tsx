import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    calendar,
    index as disbursementsIndex,
    show,
} from '@/routes/user/disbursements';

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

function shiftMonth(month: string, delta: number): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + delta, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export default function DisbursementCalendar({
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
                    title: 'Disbursements',
                    href: disbursementsIndex.url(department.slug),
                },
                { title: 'Calendar', href: calendar.url(department.slug) },
            ],
        });
    }, [department.slug]);

    const grouped = useMemo(() => {
        const days = new Map<string, CalendarBatch[]>();

        for (const batch of batches) {
            const day = batch.scheduled_at?.slice(0, 10) ?? 'Unscheduled';
            days.set(day, [...(days.get(day) ?? []), batch]);
        }

        return [...days.entries()];
    }, [batches]);

    function visit(nextMonth: string): void {
        router.get(
            calendar.url(department.slug, { query: { month: nextMonth } }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Disbursement calendar" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Disbursement calendar
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Approved and scheduled events for {department.name}.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => visit(shiftMonth(month, -1))}
                        >
                            Previous
                        </Button>
                        <span className="min-w-24 text-center text-sm font-medium">
                            {month}
                        </span>
                        <Button
                            variant="outline"
                            onClick={() => visit(shiftMonth(month, 1))}
                        >
                            Next
                        </Button>
                    </div>
                </div>

                {grouped.length === 0 && (
                    <Card>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                No approved or scheduled disbursements in this
                                month.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {grouped.map(([day, events]) => (
                    <Card key={day}>
                        <CardHeader className="border-b">
                            <CardTitle>{day}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {events.map((batch) => (
                                <div
                                    key={batch.id}
                                    className="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <div>
                                        <Link
                                            href={show.url({
                                                department: department.slug,
                                                disbursementBatch: batch.id,
                                            })}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {batch.batch_code}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {batch.title} · {batch.program} ·{' '}
                                            {batch.venue}
                                        </div>
                                    </div>
                                    <Badge variant="outline">
                                        {batch.status_label}
                                    </Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}
