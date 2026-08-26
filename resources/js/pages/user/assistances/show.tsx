'use client';

import { DataTable } from '@/components/data-table';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { AssistanceDocumentsSection } from '@/pages/user/assistances/assistance-documents';
import {
    AssistanceStatusTimeline,
    type AssistanceStatusTimelineEntry,
} from '@/pages/user/assistances/assistance-status-timeline';
import { assistanceStatuses } from '@/pages/user/programs/assistance-data';
import { receipt as assistanceReceipt, show as assistanceShow } from '@/routes/user/assistances';
import { show as beneficiaryShow } from '@/routes/user/beneficiaries';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import type { AssistanceDocumentsPayload, DocumentTypeOption } from '@/types/document';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
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
import { useEffect, useMemo } from 'react';

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
    cais_number: string;
    beneficiary_id: number | null;
    beneficiary_name: string;
    beneficiary_type: string | null;
    status: string;
    current_sub_status: string | null;
    mode_of_request: string;
    date_requested: string | null;
    date_verified: string | null;
    date_delivered: string | null;
    date_denied: string | null;
    remark: string | null;
    items_count: number;
    items_received_count: number;
    items: AssistanceItem[];
    status_history: AssistanceStatusTimelineEntry[];
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
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium tabular-nums">
                {value && value !== '' ? value : '—'}
            </dd>
        </div>
    );
}

function createAssistanceItemColumns(): ColumnDef<AssistanceItem>[] {
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
            cell: ({ row }) => formatItemAmount(row.original) ?? '—',
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
        {
            accessorKey: 'is_received',
            meta: { title: 'Received' },
            header: 'Received',
            cell: ({ row }) =>
                row.original.is_received ? (
                    <Badge
                        variant="outline"
                        className={STATUS_BADGE_CLASSES.Delivered}
                    >
                        <CheckCircle2 aria-hidden />
                        Yes
                    </Badge>
                ) : (
                    <Badge variant="outline">No</Badge>
                ),
        },
    ];
}

function ItemsTableToolbar({
    table,
    columnVisibility,
}: {
    table: Table<AssistanceItem>;
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

export default function UserAssistanceShow({
    department,
    program,
    assistance,
    documents,
    document_types,
}: {
    department: DepartmentSummary;
    program: ProgramSummary;
    assistance: AssistanceProfile;
    documents: AssistanceDocumentsPayload;
    document_types: DocumentTypeOption[];
}) {
    const statusOption = assistanceStatuses.find(
        (entry) => entry.value === assistance.status,
    );
    const StatusIcon = statusOption?.icon;

    const itemColumns = useMemo(() => createAssistanceItemColumns(), []);

    const receivedPercent =
        assistance.items_count > 0
            ? Math.round(
                  (assistance.items_received_count / assistance.items_count) *
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
                                {StatusIcon ? (
                                    <StatusIcon aria-hidden />
                                ) : null}
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
                                    <p className="mb-3 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
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
                                    </dl>
                                </div>

                                <div>
                                    <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Remark
                                    </p>
                                    <p
                                        className={cn(
                                            'whitespace-pre-wrap text-sm leading-relaxed',
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
                                    <p className="mb-3 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
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
                                        <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
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
                                                    pendingLabel: 'Not requested',
                                                },
                                                {
                                                    label: 'Verified',
                                                    value: assistance.date_verified,
                                                    icon: PackageCheck,
                                                    tone: 'sky',
                                                    doneLabel: 'Verified',
                                                    pendingLabel: 'Awaiting verification',
                                                },
                                                {
                                                    label: 'Delivered',
                                                    value: assistance.date_delivered,
                                                    icon: CheckCircle2,
                                                    tone: 'emerald',
                                                    doneLabel: 'Delivered',
                                                    pendingLabel: 'Not delivered',
                                                },
                                                ...(assistance.date_denied
                                                    ? [
                                                          {
                                                              label: 'Denied',
                                                              value: assistance.date_denied,
                                                              icon: CircleX,
                                                              tone: 'red' as const,
                                                              doneLabel: 'Denied',
                                                              pendingLabel: 'Denied',
                                                          },
                                                      ]
                                                    : []),
                                            ] as const satisfies ReadonlyArray<{
                                                label: string;
                                                value: string | null;
                                                icon: LucideIcon;
                                                tone: 'amber' | 'sky' | 'emerald' | 'red';
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
                                                            <p className="text-sm font-medium leading-tight">
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
                                    <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        <Package className="size-3.5" />
                                        Items
                                    </p>
                                    {assistance.items_count === 0 ? (
                                        <p className="text-sm text-muted-foreground/60 italic">
                                            No items listed
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            <p className="text-sm">
                                                <span className="font-semibold tabular-nums">
                                                    {assistance.items_received_count.toLocaleString()}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    of{' '}
                                                    {assistance.items_count.toLocaleString()}{' '}
                                                    received
                                                </span>
                                            </p>
                                            {receivedPercent !== null ? (
                                                <Progress
                                                    value={receivedPercent}
                                                    aria-label={`Items received ${receivedPercent}%`}
                                                />
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
                            description="Timeline of sub-status updates for this request, oldest to newest"
                        />
                        <AssistanceStatusTimeline
                            entries={assistance.status_history}
                        />
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
                            description="Goods or services included in this assistance"
                        />
                        <DataTable
                            columns={itemColumns}
                            data={assistance.items}
                            emptyMessage="No items listed for this assistance."
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
