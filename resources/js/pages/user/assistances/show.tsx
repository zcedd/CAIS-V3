'use client';

import { DataTable } from '@/components/data-table';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import { Badge } from '@/components/ui/badge';
import { slaLabel } from '@/components/user/sla-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { AssistanceDocumentsSection } from '@/pages/user/assistances/assistance-documents';
import {
    AssistanceStatusTimeline,
    type AssistanceStatusTimelineEntry,
} from '@/pages/user/assistances/assistance-status-timeline';
import { assistanceStatuses } from '@/pages/user/programs/assistance-data';
import {
    receipt as assistanceReceipt,
    show as assistanceShow,
} from '@/routes/user/assistances';
import { assign as assignAssistance } from '@/routes/user/programs/assistances';
import type { DepartmentStaffOption } from '@/pages/user/programs/assistance-toolbar';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import {
    ASSISTANCE_ITEM_ORIGIN_LABELS,
    formatItemQuantity,
} from '@/types/assistance-item';
import type {
    AssistanceItemVariance,
    AssistanceReleasedItem,
    AssistanceRequestedItem,
} from '@/types/assistance-item';
import type {
    AssistanceDocumentsPayload,
    DocumentTypeOption,
} from '@/types/document';
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import type { ColumnDef, Table, VisibilityState } from '@tanstack/react-table';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    CheckCircle2,
    Circle,
    CircleX,
    ClipboardList,
    Package,
    PackageCheck,
    Printer,
    UserRound,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProgramSummary = {
    id: number;
    name: string;
};

type AssistanceProfile = {
    id: number;
    cais_number: string;
    beneficiary_id: number | null;
    beneficiary_name: string;
    beneficiary_type: string | null;
    status: string;
    current_sub_status: string | null;
    mode_of_request: string;
    encoder_name?: string | null;
    assigned_to_id?: number | null;
    assignee_name?: string | null;
    sla_due_at?: string | null;
    sla_state?: string | null;
    can_advance?: boolean;
    can_assign?: boolean;
    step_has_owner?: boolean;
    date_requested: string | null;
    date_verified: string | null;
    date_delivered: string | null;
    date_denied: string | null;
    remark: string | null;
    requested_items: AssistanceRequestedItem[];
    released_items: AssistanceReleasedItem[];
    item_variance: AssistanceItemVariance;
    status_history: AssistanceStatusTimelineEntry[];
    assignments?: Array<{
        id: number;
        assigned_to_name: string;
        assigned_by_name: string;
        remark: string | null;
        recorded_at: string | null;
    }>;
};

const STATUS_BADGE_CLASSES: Record<string, string> = {
    Delivered:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    Verified:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
    Pending:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    Denied: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
};

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

const ORIGIN_BADGE_CLASSES: Record<string, string> = {
    additional:
        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-300',
    substitute:
        'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300',
};

function SectionHeading({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="space-y-1">
            <h2 className="text-[15px] font-semibold tracking-tight">
                {title}
            </h2>
            <p className="text-xs text-muted-foreground">{description}</p>
        </div>
    );
}

function DetailItem({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div>
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium tabular-nums">
                {value && value !== '' ? value : '—'}
            </dd>
        </div>
    );
}

function createRequestedItemColumns(): ColumnDef<AssistanceRequestedItem>[] {
    return [
        {
            accessorKey: 'name',
            meta: { title: 'Item' },
            header: 'Item',
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            id: 'requested',
            meta: {
                title: 'Requested',
                cellClassName: 'tabular-nums text-muted-foreground',
            },
            header: 'Requested',
            cell: ({ row }) =>
                formatItemQuantity(
                    row.original.requested_quantity,
                    row.original.unit,
                    row.original.kind,
                ),
        },
        {
            id: 'released',
            meta: {
                title: 'Released',
                cellClassName: 'tabular-nums text-muted-foreground',
            },
            header: 'Released',
            cell: ({ row }) =>
                formatItemQuantity(
                    row.original.released_quantity,
                    row.original.unit,
                    row.original.kind,
                ),
        },
        {
            id: 'outstanding',
            meta: { title: 'Outstanding' },
            header: 'Outstanding',
            cell: ({ row }) => {
                const { pending_quantity, substituted_quantity, unit, kind } =
                    row.original;

                if (pending_quantity > 0) {
                    return (
                        <Badge
                            variant="outline"
                            className={STATUS_BADGE_CLASSES.Pending}
                        >
                            {formatItemQuantity(pending_quantity, unit, kind)}{' '}
                            owed
                        </Badge>
                    );
                }

                if (substituted_quantity > 0) {
                    return (
                        <Badge
                            variant="outline"
                            className={ORIGIN_BADGE_CLASSES.substitute}
                        >
                            Substituted
                        </Badge>
                    );
                }

                return (
                    <Badge
                        variant="outline"
                        className={STATUS_BADGE_CLASSES.Delivered}
                    >
                        <CheckCircle2 aria-hidden />
                        Fulfilled
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'specification',
            meta: { title: 'Specification' },
            header: 'Specification',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.specification?.trim()
                        ? row.original.specification
                        : '—'}
                </span>
            ),
        },
    ];
}

function createReleasedItemColumns(): ColumnDef<AssistanceReleasedItem>[] {
    return [
        {
            accessorKey: 'name',
            meta: { title: 'Item' },
            header: 'Item',
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            id: 'amount',
            meta: {
                title: 'Amount',
                cellClassName: 'tabular-nums text-muted-foreground',
            },
            header: 'Amount',
            cell: ({ row }) =>
                formatItemQuantity(
                    row.original.quantity,
                    row.original.unit,
                    row.original.kind,
                ),
        },
        {
            accessorKey: 'origin',
            meta: { title: 'Type' },
            header: 'Type',
            cell: ({ row }) => {
                const { origin, substituted_for_name } = row.original;

                return (
                    <div className="flex flex-col gap-1">
                        <Badge
                            variant="outline"
                            className={ORIGIN_BADGE_CLASSES[origin] ?? ''}
                        >
                            {ASSISTANCE_ITEM_ORIGIN_LABELS[origin]}
                        </Badge>
                        {substituted_for_name ? (
                            <span className="text-xs text-muted-foreground">
                                in place of {substituted_for_name}
                            </span>
                        ) : null}
                    </div>
                );
            },
        },
        {
            accessorKey: 'fulfillment_reason',
            meta: { title: 'Reason' },
            header: 'Reason',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.fulfillment_reason?.trim()
                        ? row.original.fulfillment_reason
                        : '—'}
                </span>
            ),
        },
        {
            accessorKey: 'specification',
            meta: { title: 'Specification' },
            header: 'Specification',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.specification?.trim()
                        ? row.original.specification
                        : '—'}
                </span>
            ),
        },
    ];
}

function ItemsTableToolbar<TItem>({
    table,
    columnVisibility,
}: {
    table: Table<TItem>;
    columnVisibility: VisibilityState;
}) {
    return (
        <div className="flex items-center justify-end">
            <DataTableViewOptions
                table={table}
                columnVisibility={columnVisibility}
            />
        </div>
    );
}

function VarianceSummary({ variance }: { variance: AssistanceItemVariance }) {
    const entries = [
        variance.additional_quantity > 0
            ? `${variance.additional_quantity} additional`
            : null,
        variance.substitute_quantity > 0
            ? `${variance.substitute_quantity} substitute`
            : null,
        variance.shortfall_quantity > 0
            ? `${variance.shortfall_quantity} not yet released`
            : null,
    ].filter((entry): entry is string => entry !== null);

    if (entries.length === 0) {
        return null;
    }

    return (
        <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            Variance against the request: {entries.join(', ')}.
        </p>
    );
}

export default function UserAssistanceShow({
    department,
    program,
    assistance,
    documents,
    document_types,
    staff_options = [],
}: {
    department: DepartmentSummary;
    program: ProgramSummary;
    assistance: AssistanceProfile;
    documents: AssistanceDocumentsPayload;
    document_types: DocumentTypeOption[];
    staff_options?: DepartmentStaffOption[];
}) {
    const statusOption = assistanceStatuses.find(
        (entry) => entry.value === assistance.status,
    );
    const StatusIcon = statusOption?.icon;

    const requestedItemColumns = useMemo(
        () => createRequestedItemColumns(),
        [],
    );
    const releasedItemColumns = useMemo(() => createReleasedItemColumns(), []);

    const variance = assistance.item_variance;
    const fulfilledPercent =
        variance.requested_quantity > 0
            ? Math.min(
                  Math.round(
                      ((variance.fulfilled_quantity +
                          variance.substituted_quantity) /
                          variance.requested_quantity) *
                          100,
                  ),
                  100,
              )
            : null;

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

    const isOrganization =
        assistance.beneficiary_type?.toLowerCase() === 'organization';
    const [assigneeId, setAssigneeId] = useState(
        assistance.assigned_to_id
            ? String(assistance.assigned_to_id)
            : 'unassigned',
    );

    const timelineEntries = useMemo<AssistanceStatusTimelineEntry[]>(() => {
        const statusEntries = assistance.status_history.map((entry) => ({
            ...entry,
            event_type: 'status' as const,
        }));
        const assignmentEntries = (assistance.assignments ?? [])
            .filter((entry) => Boolean(entry.recorded_at))
            .map((entry) => ({
                id: entry.id + 1_000_000,
                name: `Assigned to ${entry.assigned_to_name}`,
                parent_status: null,
                remark: [
                    `By ${entry.assigned_by_name}`,
                    entry.remark?.trim() ? entry.remark : null,
                ]
                    .filter(Boolean)
                    .join(' — '),
                recorded_at: entry.recorded_at as string,
                event_type: 'assignment' as const,
            }));

        return [...statusEntries, ...assignmentEntries];
    }, [assistance.status_history, assistance.assignments]);

    return (
        <>
            <Head title={heading} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge
                                variant="outline"
                                className={cn(
                                    STATUS_BADGE_CLASSES[assistance.status] ??
                                        '',
                                )}
                            >
                                {StatusIcon ? <StatusIcon aria-hidden /> : null}
                                {assistance.status}
                            </Badge>
                            {assistance.current_sub_status &&
                            assistance.current_sub_status !==
                                assistance.status ? (
                                <Badge variant="secondary">
                                    {assistance.current_sub_status}
                                </Badge>
                            ) : null}
                            <Badge variant="outline">
                                {assistance.mode_of_request}
                            </Badge>
                            {assistance.encoder_name ? (
                                <Badge variant="outline">
                                    {assistance.encoder_name}
                                </Badge>
                            ) : null}
                            <Badge variant="outline">
                                {assistance.assignee_name ?? 'Unassigned'}
                            </Badge>
                            {assistance.sla_state &&
                            assistance.sla_state !== 'none' ? (
                                <Badge variant="outline">
                                    SLA {slaLabel(assistance.sla_state)}
                                </Badge>
                            ) : null}
                            {assistance.beneficiary_type ? (
                                <Badge variant="outline">
                                    {isOrganization ? (
                                        <Building2 aria-hidden />
                                    ) : (
                                        <UserRound aria-hidden />
                                    )}
                                    {assistance.beneficiary_type}
                                </Badge>
                            ) : null}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Assistance under{' '}
                            <span className="font-medium text-foreground">
                                {program.name}
                            </span>
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={assistanceReceipt.url({
                                    department: department.slug,
                                    program: program.id,
                                    assistance: assistance.id,
                                })}
                            >
                                <Printer className="size-4" />
                                Print acknowledgment
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={departmentProgramShow.url({
                                    department: department.slug,
                                    program: program.id,
                                })}
                            >
                                <ArrowLeft className="size-4" />
                                Back to program
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Overview */}
                <section
                    data-tour="assistance-overview"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-col gap-4 p-4">
                        <SectionHeading
                            title="Overview"
                            description="Request details, beneficiary, key dates, and items summary"
                        />

                        <div className="grid gap-6 lg:grid-cols-3">
                            <div className="min-w-0 space-y-5 lg:col-span-2">
                                <div>
                                    <p className="mb-3 flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <ClipboardList className="size-3.5" />
                                        Request details
                                    </p>
                                    <dl className="grid gap-4 sm:grid-cols-2">
                                        <DetailItem
                                            label="Requested"
                                            value={formatDate(
                                                assistance.date_requested,
                                            )}
                                        />
                                        <DetailItem
                                            label="Verified"
                                            value={formatDate(
                                                assistance.date_verified,
                                            )}
                                        />
                                        <DetailItem
                                            label="Delivered"
                                            value={formatDate(
                                                assistance.date_delivered,
                                            )}
                                        />
                                        {assistance.date_denied ? (
                                            <DetailItem
                                                label="Denied"
                                                value={formatDate(
                                                    assistance.date_denied,
                                                )}
                                            />
                                        ) : null}
                                        <DetailItem
                                            label="Mode of request"
                                            value={assistance.mode_of_request}
                                        />
                                        <DetailItem
                                            label="Status"
                                            value={
                                                assistance.current_sub_status
                                                    ? `${assistance.status} · ${assistance.current_sub_status}`
                                                    : assistance.status
                                            }
                                        />
                                        <DetailItem
                                            label="Assignee"
                                            value={
                                                assistance.assignee_name ??
                                                'Unassigned'
                                            }
                                        />
                                        <DetailItem
                                            label="SLA"
                                            value={slaLabel(assistance.sla_state)}
                                        />
                                    </dl>
                                </div>

                                <div>
                                    <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Remark
                                    </p>
                                    <p
                                        className={cn(
                                            'text-sm leading-relaxed whitespace-pre-wrap',
                                            assistance.remark?.trim()
                                                ? 'text-muted-foreground'
                                                : 'text-muted-foreground/60 italic',
                                        )}
                                    >
                                        {assistance.remark?.trim()
                                            ? assistance.remark
                                            : 'No remark recorded'}
                                    </p>
                                </div>

                                <div>
                                    <p className="mb-3 flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {isOrganization ? (
                                            <Building2 className="size-3.5" />
                                        ) : (
                                            <UserRound className="size-3.5" />
                                        )}
                                        Beneficiary
                                    </p>
                                    <div className="rounded-lg border border-border p-3">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0 space-y-1">
                                                {assistance.beneficiary_id ? (
                                                    <Link
                                                        href={beneficiaryShow.url(
                                                            {
                                                                department:
                                                                    department.slug,
                                                                beneficiary:
                                                                    assistance.beneficiary_id,
                                                            },
                                                        )}
                                                        className="text-sm font-medium text-primary hover:underline"
                                                    >
                                                        {
                                                            assistance.beneficiary_name
                                                        }
                                                    </Link>
                                                ) : (
                                                    <p className="text-sm font-medium">
                                                        {
                                                            assistance.beneficiary_name
                                                        }
                                                    </p>
                                                )}
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {assistance.cais_number}
                                                </p>
                                            </div>
                                            {assistance.beneficiary_type ? (
                                                <Badge variant="outline">
                                                    {
                                                        assistance.beneficiary_type
                                                    }
                                                </Badge>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="min-w-0 space-y-5 lg:border-l lg:border-border lg:pl-6">
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            <CalendarDays className="size-3.5" />
                                            Key dates
                                        </p>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                STATUS_BADGE_CLASSES[
                                                    assistance.status
                                                ] ?? '',
                                            )}
                                        >
                                            {StatusIcon ? (
                                                <StatusIcon aria-hidden />
                                            ) : null}
                                            {assistance.current_sub_status ??
                                                assistance.status}
                                        </Badge>
                                    </div>
                                    <ul className="space-y-2">
                                        {(
                                            [
                                                {
                                                    label: 'Requested',
                                                    value: assistance.date_requested,
                                                    icon: Circle,
                                                    tone: 'amber',
                                                    doneLabel: 'Requested',
                                                    pendingLabel:
                                                        'Not requested',
                                                },
                                                {
                                                    label: 'Verified',
                                                    value: assistance.date_verified,
                                                    icon: PackageCheck,
                                                    tone: 'sky',
                                                    doneLabel: 'Verified',
                                                    pendingLabel:
                                                        'Awaiting verification',
                                                },
                                                {
                                                    label: 'Delivered',
                                                    value: assistance.date_delivered,
                                                    icon: CheckCircle2,
                                                    tone: 'emerald',
                                                    doneLabel: 'Delivered',
                                                    pendingLabel:
                                                        'Not delivered',
                                                },
                                                ...(assistance.date_denied
                                                    ? [
                                                          {
                                                              label: 'Denied',
                                                              value: assistance.date_denied,
                                                              icon: CircleX,
                                                              tone: 'red' as const,
                                                              doneLabel:
                                                                  'Denied',
                                                              pendingLabel:
                                                                  'Denied',
                                                          },
                                                      ]
                                                    : []),
                                            ] as const satisfies ReadonlyArray<{
                                                label: string;
                                                value: string | null;
                                                icon: LucideIcon;
                                                tone:
                                                    | 'amber'
                                                    | 'sky'
                                                    | 'emerald'
                                                    | 'red';
                                                doneLabel: string;
                                                pendingLabel: string;
                                            }>
                                        ).map((entry) => {
                                            const Icon = entry.icon;
                                            const isSet = Boolean(entry.value);
                                            const toneClasses = {
                                                amber: isSet
                                                    ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300'
                                                    : '',
                                                sky: isSet
                                                    ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300'
                                                    : '',
                                                emerald: isSet
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300'
                                                    : '',
                                                red: isSet
                                                    ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300'
                                                    : '',
                                            }[entry.tone];

                                            return (
                                                <li
                                                    key={entry.label}
                                                    className="flex items-center justify-between gap-3"
                                                >
                                                    <div className="flex min-w-0 items-center gap-2">
                                                        <span
                                                            className={cn(
                                                                'flex size-7 shrink-0 items-center justify-center rounded-md border',
                                                                isSet
                                                                    ? toneClasses
                                                                    : 'border-border bg-muted/40 text-muted-foreground/60',
                                                            )}
                                                        >
                                                            <Icon
                                                                className="size-3.5"
                                                                aria-hidden
                                                            />
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="text-sm leading-tight font-medium">
                                                                {entry.label}
                                                            </p>
                                                            <p
                                                                className={cn(
                                                                    'text-xs tabular-nums',
                                                                    isSet
                                                                        ? 'text-muted-foreground'
                                                                        : 'text-muted-foreground/60',
                                                                )}
                                                            >
                                                                {formatDate(
                                                                    entry.value,
                                                                )}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            'shrink-0',
                                                            isSet
                                                                ? toneClasses
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {isSet
                                                            ? entry.doneLabel
                                                            : entry.pendingLabel}
                                                    </Badge>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>

                                <div className="space-y-2">
                                    <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <Package className="size-3.5" />
                                        Items
                                    </p>
                                    {variance.requested_quantity === 0 &&
                                    variance.released_quantity === 0 ? (
                                        <p className="text-sm text-muted-foreground/60 italic">
                                            No items listed
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            <p className="text-sm">
                                                <span className="font-semibold tabular-nums">
                                                    {variance.released_quantity.toLocaleString()}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    released against{' '}
                                                    {variance.requested_quantity.toLocaleString()}{' '}
                                                    requested
                                                </span>
                                            </p>
                                            {fulfilledPercent !== null ? (
                                                <Progress
                                                    value={fulfilledPercent}
                                                    aria-label={`Request settled ${fulfilledPercent}%`}
                                                />
                                            ) : null}
                                            {variance.additional_quantity >
                                            0 ? (
                                                <p className="text-xs text-muted-foreground">
                                                    Includes{' '}
                                                    {variance.additional_quantity.toLocaleString()}{' '}
                                                    additional, never requested
                                                </p>
                                            ) : null}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Tracking */}
                <section
                    data-tour="assistance-tracking"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-col gap-4 p-4">
                        <SectionHeading
                            title="Assistance tracking"
                            description="Status changes and assignment history, oldest to newest"
                        />
                        {assistance.can_advance === false ? (
                            <p className="text-sm text-muted-foreground">
                                Only the assignee for this stage can update the
                                status.
                            </p>
                        ) : null}
                        {staff_options.length > 0 &&
                        assistance.can_assign !== false ? (
                            <Form
                                {...assignAssistance.form.patch({
                                    department: department.slug,
                                    program: program.id,
                                    assistance: assistance.id,
                                })}
                                disableWhileProcessing
                                options={{ preserveScroll: true }}
                                transform={(data) => ({
                                    ...data,
                                    assigned_to_id:
                                        assigneeId === 'unassigned'
                                            ? null
                                            : Number(assigneeId),
                                })}
                                onSuccess={() =>
                                    toast.success(
                                        'Assistance assignment updated.',
                                    )
                                }
                                className="flex flex-wrap items-end gap-2 rounded-lg border p-3"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="space-y-1">
                                            <Label htmlFor="assistance-show-assignee">
                                                Assignee
                                            </Label>
                                            <Select
                                                value={assigneeId}
                                                onValueChange={setAssigneeId}
                                            >
                                                <SelectTrigger
                                                    id="assistance-show-assignee"
                                                    className="h-9 w-56"
                                                >
                                                    <SelectValue placeholder="Unassigned" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="unassigned">
                                                        Unassigned
                                                    </SelectItem>
                                                    {staff_options.map(
                                                        (staff) => (
                                                            <SelectItem
                                                                key={staff.id}
                                                                value={String(
                                                                    staff.id,
                                                                )}
                                                            >
                                                                {staff.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving...'
                                                : 'Update assignment'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : null}
                        <AssistanceStatusTimeline entries={timelineEntries} />
                    </div>
                </section>

                <AssistanceDocumentsSection
                    departmentSlug={department.slug}
                    programId={program.id}
                    assistanceId={assistance.id}
                    documents={documents}
                    documentTypes={document_types}
                />

                {/* Items */}
                <section
                    data-tour="assistance-items"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-col gap-4 p-4">
                        <SectionHeading
                            title="Items requested"
                            description="What the beneficiary applied for, and how much of it is still owed"
                        />
                        <VarianceSummary variance={variance} />
                        <DataTable
                            columns={requestedItemColumns}
                            data={assistance.requested_items}
                            emptyMessage="No items were requested for this assistance."
                            toolbar={(table, columnVisibility) => (
                                <ItemsTableToolbar
                                    table={table}
                                    columnVisibility={columnVisibility}
                                />
                            )}
                        />
                    </div>
                </section>

                <section className="rounded-xl border border-border bg-card">
                    <div className="flex flex-col gap-4 p-4">
                        <SectionHeading
                            title="Items released"
                            description="What was actually handed over, including additional and substitute items"
                        />
                        <DataTable
                            columns={releasedItemColumns}
                            data={assistance.released_items}
                            emptyMessage="Nothing has been released yet."
                            toolbar={(table, columnVisibility) => (
                                <ItemsTableToolbar
                                    table={table}
                                    columnVisibility={columnVisibility}
                                />
                            )}
                        />
                    </div>
                </section>
            </div>
        </>
    );
}
