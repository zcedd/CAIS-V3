import { Head, Link, router, setLayoutProps, WhenVisible } from '@inertiajs/react';
import { Check, Undo2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { ProgramAttachmentList } from '@/components/program-attachments-field';
import type { ProgramAttachment } from '@/components/program-attachments-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import type { ProgramCardMetric } from '@/components/user/program-card';
import { ProgramDecisionDialog } from '@/components/user/programs/program-decision-dialog';
import type { ProgramDecisionAction } from '@/components/user/programs/program-decision-dialog';
import {
    formatProgramDate,
    formatProgramPeriod,
} from '@/lib/format-program-period';
import {
    ProgramReachCards,
    ProgramReachSkeleton,
    ProgramShowMetrics,
    ProgramShowMetricsSkeleton,
} from '@/pages/user/programs/program-show-metrics';
import {
    ProgramStatusBreakdown,
    ProgramStatusBreakdownSkeleton,
} from '@/pages/user/programs/status-breakdown';
import type { BreadcrumbItem } from '@/types';
import type {
    ProgramStatusBreakdownPoint,
    ProgramStockRow,
    ProgramSummary,
} from '@/types/program';

type ProgramReview = {
    mode: 'endorse' | 'approve';
    audience: string;
    index_url: string;
    show_url: string;
    positive_url: string;
    return_url: string;
    awaiting_status: string;
    positive_action: Extract<ProgramDecisionAction, 'endorse' | 'approve'>;
    positive_label: string;
};

type GovernorProgram = {
    id: number;
    name: string;
    descriptions: string | null;
    kind: string | null;
    kind_label: string | null;
    approval_status: string;
    approval_label: string;
    department: { id: number; name: string } | null;
    start_at: string | null;
    end_at: string | null;
    start_at_input: string | null;
    end_at_input: string | null;
    distribution_mode: 'individual' | 'batch' | 'both';
    distribution_mode_label: string;
    workflow_name: string;
    is_closed: boolean;
    is_organization: boolean;
    public_intake: boolean;
    batch_name: string | null;
    parent: { id: number; name: string } | null;
    funds: Array<{
        id: number;
        name: string;
        year: string | null;
        amount: number | null;
    }>;
    items: Array<{
        id: number;
        name: string;
        unit: string | null;
    }>;
    fields: Array<{
        id: number;
        label: string;
        type_label: string;
        is_required: boolean;
        options: string[] | null;
    }>;
    document_requirements: Array<{
        id: number;
        name: string;
        is_required: boolean;
        required_before_label: string;
    }>;
    attachments: ProgramAttachment[];
    stock: ProgramStockRow[];
    batches: Array<{
        id: number;
        name: string;
        batch_name: string | null;
        approval_label: string;
        total_requests: number;
        start_at: string | null;
        end_at: string | null;
        is_closed: boolean;
    }>;
    eligibility: {
        cooldown_days: number | null;
        requirements: string[];
        item_caps: Array<{
            item_id: number;
            item_name: string;
            max_released_per_year: number;
        }>;
        source_name: string | null;
    };
};

function formatFundAmount(amount: number | null): string | null {
    if (amount === null) {
        return null;
    }

    return amount.toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 2,
    });
}

function programPeriod(startAt: string | null, endAt: string | null): string {
    if (startAt && endAt) {
        return `${startAt} to ${endAt}`;
    }

    if (startAt) {
        return startAt;
    }

    if (endAt) {
        return endAt;
    }

    return 'No dates set';
}

const MS_PER_DAY = 86_400_000;

function programTimeline(
    startInput: string | null,
    endInput: string | null,
): { percent: number; caption: string } | null {
    if (!startInput || !endInput) {
        return null;
    }

    const start = new Date(startInput).getTime();
    const end = new Date(endInput).getTime();

    if (Number.isNaN(start) || Number.isNaN(end) || end <= start) {
        return null;
    }

    const now = Date.now();
    const percent = Math.min(
        100,
        Math.max(0, Math.round(((now - start) / (end - start)) * 100)),
    );

    if (now < start) {
        const days = Math.ceil((start - now) / MS_PER_DAY);

        return {
            percent: 0,
            caption: `Starts in ${days} ${days === 1 ? 'day' : 'days'}`,
        };
    }

    if (now > end) {
        const days = Math.floor((now - end) / MS_PER_DAY);

        return {
            percent: 100,
            caption:
                days === 0
                    ? 'Ended today'
                    : `Ended ${days} ${days === 1 ? 'day' : 'days'} ago`,
        };
    }

    const daysLeft = Math.ceil((end - now) / MS_PER_DAY);

    return {
        percent,
        caption: `${percent}% elapsed · ${daysLeft} ${daysLeft === 1 ? 'day' : 'days'} remaining`,
    };
}

type ReviewAssistance = {
    id: number;
    cais_number: string | null;
    beneficiary_name: string;
    status: string;
    mode_of_request: string | null;
    date_requested: string | null;
    date_delivered: string | null;
    batch_name: string | null;
};

type PaginatedAssistances = {
    data: ReviewAssistance[];
    current_page: number;
    last_page: number;
    total: number;
};

function DetailBlock({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-2">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </p>
            {children}
        </div>
    );
}

function EmptyDetail({ children }: { children: ReactNode }) {
    return <p className="text-sm text-muted-foreground">{children}</p>;
}

export default function GovernorProgramShow({
    program,
    review,
    summary,
    status_breakdown,
    program_metrics,
    assistances,
}: {
    program: GovernorProgram;
    review: ProgramReview;
    summary?: ProgramSummary;
    status_breakdown?: ProgramStatusBreakdownPoint[];
    program_metrics?: ProgramCardMetric | null;
    assistances?: PaginatedAssistances;
}) {
    const canDecide = program.approval_status === review.awaiting_status;
    const description = program.descriptions?.trim();
    const timeline = programTimeline(
        program.start_at_input,
        program.end_at_input,
    );
    const showBatchColumn = program.batches.length > 0;
    const hasEligibility =
        program.eligibility.cooldown_days !== null ||
        program.eligibility.requirements.length > 0 ||
        program.eligibility.item_caps.length > 0;
    const [decision, setDecision] = useState<Extract<
        ProgramDecisionAction,
        'endorse' | 'approve' | 'return'
    > | null>(null);

    useEffect(() => {
        const breadcrumbs: BreadcrumbItem[] = [
            {
                title: review.audience,
                href: review.index_url,
            },
            {
                title: program.name,
                href: review.show_url,
            },
        ];

        setLayoutProps({ breadcrumbs });
    }, [program.name, review.audience, review.index_url, review.show_url]);

    return (
        <>
            <Head title={program.name} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {program.name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {program.approval_label}
                            </Badge>
                            <Badge variant="outline">
                                {program.is_closed ? 'Closed' : 'Open'}
                            </Badge>
                            <Badge variant="outline">
                                {program.is_organization
                                    ? 'Organization'
                                    : 'Individual'}
                            </Badge>
                            <Badge variant="outline">
                                {program.distribution_mode_label}
                            </Badge>
                            {program.kind_label ? (
                                <Badge variant="outline">
                                    {program.kind_label}
                                </Badge>
                            ) : null}
                            {program.batch_name ? (
                                <Badge variant="outline">
                                    {program.batch_name}
                                </Badge>
                            ) : null}
                            {program.public_intake ? (
                                <Badge variant="outline">Public intake</Badge>
                            ) : null}
                            {program.department ? (
                                <Badge variant="outline">
                                    {program.department.name}
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={review.index_url}>Back to programs</Link>
                    </Button>
                </div>

                {canDecide ? (
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            onClick={() => setDecision(review.positive_action)}
                        >
                            <Check data-icon="inline-start" />
                            {review.positive_label}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDecision('return')}
                        >
                            <Undo2 data-icon="inline-start" />
                            Return to department
                        </Button>
                    </div>
                ) : null}

                <ProgramDecisionDialog
                    open={decision !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDecision(null);
                        }
                    }}
                    programName={program.name}
                    action={decision ?? review.positive_action}
                    url={
                        decision === 'return'
                            ? review.return_url
                            : review.positive_url
                    }
                />

                <WhenVisible
                    data={['summary', 'program_metrics']}
                    buffer={200}
                    fallback={<ProgramShowMetricsSkeleton />}
                >
                    {program_metrics ? (
                        <ProgramShowMetrics
                            metrics={program_metrics}
                            summary={summary}
                        />
                    ) : (
                        <ProgramShowMetricsSkeleton />
                    )}
                </WhenVisible>

                <WhenVisible
                    data={['program_metrics', 'status_breakdown']}
                    buffer={200}
                    fallback={
                        <div className="grid gap-4 xl:grid-cols-3">
                            <ProgramReachSkeleton />
                            <ProgramStatusBreakdownSkeleton />
                        </div>
                    }
                >
                    <div className="grid gap-4 xl:grid-cols-3">
                        {program_metrics ? (
                            <ProgramReachCards metrics={program_metrics} />
                        ) : (
                            <ProgramReachSkeleton />
                        )}
                        {status_breakdown ? (
                            <ProgramStatusBreakdown
                                breakdown={status_breakdown}
                            />
                        ) : (
                            <ProgramStatusBreakdownSkeleton />
                        )}
                    </div>
                </WhenVisible>

                <Card>
                    <CardHeader>
                        <CardTitle>Program details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-2">
                        <DetailBlock title="Description">
                            {description ? (
                                <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                    {description}
                                </p>
                            ) : (
                                <EmptyDetail>No description</EmptyDetail>
                            )}
                        </DetailBlock>

                        <DetailBlock title="Program period">
                            <p className="text-sm">
                                {programPeriod(
                                    program.start_at,
                                    program.end_at,
                                )}
                            </p>
                            {timeline ? (
                                <div className="space-y-1.5">
                                    <Progress
                                        value={timeline.percent}
                                        aria-label={`Program timeline ${timeline.percent}% elapsed`}
                                    />
                                    <p className="text-xs text-muted-foreground tabular-nums">
                                        {timeline.caption}
                                    </p>
                                </div>
                            ) : program.start_at_input &&
                              !program.end_at_input ? (
                                <p className="text-xs text-muted-foreground">
                                    Ongoing since{' '}
                                    {formatProgramDate(program.start_at_input)}
                                </p>
                            ) : null}
                        </DetailBlock>

                        <DetailBlock title="Distribution">
                            <p className="text-sm">
                                {program.distribution_mode_label}
                            </p>
                        </DetailBlock>

                        <DetailBlock title="Workflow">
                            <p className="text-sm">{program.workflow_name}</p>
                        </DetailBlock>

                        {program.parent ? (
                            <DetailBlock title="Scheme">
                                <p className="text-sm">{program.parent.name}</p>
                            </DetailBlock>
                        ) : null}

                        <DetailBlock title="Funding sources">
                            {program.funds.length === 0 ? (
                                <EmptyDetail>
                                    No funding sources linked
                                </EmptyDetail>
                            ) : (
                                <ul className="flex flex-col gap-1.5">
                                    {program.funds.map((fund) => (
                                        <li
                                            key={fund.id}
                                            className="flex items-baseline justify-between gap-2 text-sm"
                                        >
                                            <span className="min-w-0 truncate">
                                                {fund.name}
                                                {fund.year ? (
                                                    <span className="ml-1.5 text-xs text-muted-foreground">
                                                        {fund.year}
                                                    </span>
                                                ) : null}
                                            </span>
                                            {formatFundAmount(fund.amount) ? (
                                                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                    {formatFundAmount(
                                                        fund.amount,
                                                    )}
                                                </span>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </DetailBlock>

                        <DetailBlock title="Covered items">
                            {program.items.length === 0 ? (
                                <EmptyDetail>No items linked</EmptyDetail>
                            ) : (
                                <div className="flex flex-wrap gap-1.5">
                                    {program.items.map((item) => (
                                        <Badge
                                            key={item.id}
                                            variant="secondary"
                                        >
                                            {item.name}
                                            {item.unit ? (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {item.unit}
                                                </span>
                                            ) : null}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                        </DetailBlock>

                        <DetailBlock title="Custom fields">
                            {program.fields.length === 0 ? (
                                <EmptyDetail>No custom fields</EmptyDetail>
                            ) : (
                                <ul className="flex flex-col gap-1.5">
                                    {program.fields.map((field) => (
                                        <li key={field.id} className="text-sm">
                                            <span>{field.label}</span>
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {field.type_label}
                                                {field.is_required
                                                    ? ' · Required'
                                                    : ''}
                                            </span>
                                            {field.options &&
                                            field.options.length > 0 ? (
                                                <span className="block text-xs text-muted-foreground">
                                                    {field.options.join(', ')}
                                                </span>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </DetailBlock>

                        <DetailBlock title="Document requirements">
                            {program.document_requirements.length === 0 ? (
                                <EmptyDetail>
                                    No document requirements
                                </EmptyDetail>
                            ) : (
                                <ul className="flex flex-col gap-1.5">
                                    {program.document_requirements.map(
                                        (requirement) => (
                                            <li
                                                key={requirement.id}
                                                className="text-sm"
                                            >
                                                {requirement.name}
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    ·{' '}
                                                    {requirement.is_required
                                                        ? 'Required'
                                                        : 'Optional'}{' '}
                                                    ·{' '}
                                                    {
                                                        requirement.required_before_label
                                                    }
                                                </span>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                        </DetailBlock>

                        <DetailBlock title="Attachments">
                            {program.attachments.length === 0 ? (
                                <EmptyDetail>No attachments</EmptyDetail>
                            ) : (
                                <ProgramAttachmentList
                                    attachments={program.attachments}
                                    showHeading={false}
                                />
                            )}
                        </DetailBlock>

                        <DetailBlock title="Stock allocated">
                            {program.stock.length === 0 ? (
                                <EmptyDetail>
                                    No stock allocated to this program
                                </EmptyDetail>
                            ) : (
                                <ul className="flex flex-col gap-3">
                                    {program.stock.map((row) => {
                                        const cap =
                                            row.on_hand > 0
                                                ? row.on_hand
                                                : row.remaining;
                                        const stockPercent =
                                            cap > 0
                                                ? Math.min(
                                                      100,
                                                      (row.remaining / cap) *
                                                          100,
                                                  )
                                                : 0;

                                        return (
                                            <li
                                                key={row.item_id}
                                                className="space-y-1"
                                            >
                                                <div className="flex items-center justify-between gap-2 text-sm">
                                                    <span>
                                                        {row.item_name}
                                                        {row.unit ? (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                · {row.unit}
                                                            </span>
                                                        ) : null}
                                                        {row.is_low ? (
                                                            <Badge
                                                                variant="destructive"
                                                                className="ml-2"
                                                            >
                                                                Low
                                                            </Badge>
                                                        ) : null}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {row.remaining}{' '}
                                                        remaining
                                                    </span>
                                                </div>
                                                <Progress value={stockPercent} />
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </DetailBlock>

                        {program.batches.length > 0 ? (
                            <DetailBlock title="Batches">
                                <ul className="flex flex-col gap-1.5">
                                    {program.batches.map((batch) => (
                                        <li
                                            key={batch.id}
                                            className="text-sm"
                                        >
                                            {batch.batch_name ?? batch.name}
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {batch.approval_label} ·{' '}
                                                {batch.total_requests}{' '}
                                                {batch.total_requests === 1
                                                    ? 'request'
                                                    : 'requests'}
                                                {batch.is_closed
                                                    ? ' · Closed'
                                                    : ''}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {formatProgramPeriod(
                                                    batch.start_at,
                                                    batch.end_at,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </DetailBlock>
                        ) : null}

                        <DetailBlock title="Eligibility">
                            {program.eligibility.source_name ? (
                                <p className="text-xs text-muted-foreground">
                                    From {program.eligibility.source_name}
                                </p>
                            ) : null}
                            {hasEligibility ? (
                                <ul className="flex flex-col gap-1.5 text-sm">
                                    {program.eligibility.cooldown_days !==
                                    null ? (
                                        <li>
                                            {program.eligibility.cooldown_days}{' '}
                                            day cooldown after delivery
                                        </li>
                                    ) : null}
                                    {program.eligibility.requirements.map(
                                        (requirement) => (
                                            <li key={requirement}>
                                                {requirement}
                                            </li>
                                        ),
                                    )}
                                    {program.eligibility.item_caps.map(
                                        (cap) => (
                                            <li key={cap.item_id}>
                                                {cap.item_name}:{' '}
                                                {cap.max_released_per_year} per
                                                year
                                            </li>
                                        ),
                                    )}
                                </ul>
                            ) : (
                                <EmptyDetail>No eligibility rules</EmptyDetail>
                            )}
                        </DetailBlock>
                    </CardContent>
                </Card>

                <section className="rounded-xl border border-border bg-card">
                    <div className="flex flex-col gap-4 p-4">
                        <div className="space-y-1">
                            <h2 className="text-[15px] font-semibold tracking-tight">
                                Assistance
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Assistance records for this program.
                            </p>
                        </div>
                        {assistances ? (
                            assistances.data.length === 0 ? (
                                <EmptyDetail>No assistance records</EmptyDetail>
                            ) : (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-left text-xs text-muted-foreground">
                                                    <th className="px-2 py-2 font-medium">
                                                        CAIS number
                                                    </th>
                                                    <th className="px-2 py-2 font-medium">
                                                        Beneficiary
                                                    </th>
                                                    {showBatchColumn ? (
                                                        <th className="px-2 py-2 font-medium">
                                                            Batch
                                                        </th>
                                                    ) : null}
                                                    <th className="px-2 py-2 font-medium">
                                                        Status
                                                    </th>
                                                    <th className="px-2 py-2 font-medium">
                                                        Mode
                                                    </th>
                                                    <th className="px-2 py-2 font-medium">
                                                        Requested
                                                    </th>
                                                    <th className="px-2 py-2 font-medium">
                                                        Delivered
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {assistances.data.map(
                                                    (row) => (
                                                        <tr
                                                            key={row.id}
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="px-2 py-2 tabular-nums">
                                                                {row.cais_number ??
                                                                    '—'}
                                                            </td>
                                                            <td className="px-2 py-2">
                                                                {
                                                                    row.beneficiary_name
                                                                }
                                                            </td>
                                                            {showBatchColumn ? (
                                                                <td className="px-2 py-2">
                                                                    {row.batch_name ??
                                                                        '—'}
                                                                </td>
                                                            ) : null}
                                                            <td className="px-2 py-2">
                                                                {row.status}
                                                            </td>
                                                            <td className="px-2 py-2">
                                                                {row.mode_of_request ??
                                                                    '—'}
                                                            </td>
                                                            <td className="px-2 py-2">
                                                                {row.date_requested ??
                                                                    '—'}
                                                            </td>
                                                            <td className="px-2 py-2">
                                                                {row.date_delivered ??
                                                                    '—'}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    {assistances.last_page > 1 ? (
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                {assistances.total.toLocaleString()}{' '}
                                                records
                                            </p>
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={
                                                        assistances.current_page <=
                                                        1
                                                    }
                                                    onClick={() =>
                                                        router.get(
                                                            review.show_url,
                                                            {
                                                                page: assistances.current_page -
                                                                    1,
                                                            },
                                                            {
                                                                preserveState: true,
                                                                preserveScroll: true,
                                                                only: [
                                                                    'assistances',
                                                                ],
                                                            },
                                                        )
                                                    }
                                                >
                                                    Previous
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={
                                                        assistances.current_page >=
                                                        assistances.last_page
                                                    }
                                                    onClick={() =>
                                                        router.get(
                                                            review.show_url,
                                                            {
                                                                page: assistances.current_page +
                                                                    1,
                                                            },
                                                            {
                                                                preserveState: true,
                                                                preserveScroll: true,
                                                                only: [
                                                                    'assistances',
                                                                ],
                                                            },
                                                        )
                                                    }
                                                >
                                                    Next
                                                </Button>
                                            </div>
                                        </div>
                                    ) : null}
                                </>
                            )
                        ) : (
                            <WhenVisible
                                data="assistances"
                                buffer={200}
                                fallback={
                                    <Skeleton className="h-24 w-full rounded-md" />
                                }
                            >
                                <Skeleton className="h-24 w-full rounded-md" />
                            </WhenVisible>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}
