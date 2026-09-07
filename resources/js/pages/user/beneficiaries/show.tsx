'use client';

import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { BeneficiaryEditDrawer } from '@/components/user/beneficiaries/beneficiary-edit-drawer';
import {
    BeneficiaryShowTableToolbar,
    createBeneficiaryAssistanceColumns,
    createMembershipColumns,
} from '@/pages/user/beneficiaries/beneficiary-show-columns';
import {
    index as beneficiariesIndex,
    show as beneficiaryShow,
} from '@/routes/user/beneficiaries';
import type {
    BeneficiaryAssistanceSummary,
    BeneficiaryProfile,
    DepartmentSummary,
    FormOptions,
    IndividualOrganizationMembership,
    OrganizationMember,
    PaginatedBeneficiaryAssistances,
} from '@/types/beneficiary';
import type { BreadcrumbItem } from '@/types';
import { Head, setLayoutProps, WhenVisible } from '@inertiajs/react';
import {
    Building2,
    Clock3,
    FolderOpen,
    HeartHandshake,
    IdCard,
    MapPin,
    PackageCheck,
    Pencil,
    Phone,
    UserRound,
    Users,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

function formatDate(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function DetailItem({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium">{String(value)}</dd>
        </div>
    );
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

const summaryCards: {
    key: 'total' | 'delivered' | 'in_progress' | 'denied';
    label: string;
    icon: LucideIcon;
}[] = [
    { key: 'total', label: 'Total requests', icon: HeartHandshake },
    { key: 'delivered', label: 'Delivered', icon: PackageCheck },
    { key: 'in_progress', label: 'In progress', icon: Clock3 },
    { key: 'denied', label: 'Denied', icon: XCircle },
];

function AssistanceSummaryCards({
    summary,
}: {
    summary: BeneficiaryAssistanceSummary;
}) {
    const deliveryRate =
        summary.total > 0
            ? Math.round((summary.delivered / summary.total) * 100)
            : null;

    const descriptionFor = (key: (typeof summaryCards)[number]['key']) => {
        if (key === 'total') {
            return summary.programs > 0
                ? `Across ${summary.programs.toLocaleString()} ${summary.programs === 1 ? 'program' : 'programs'}`
                : 'No programs joined yet';
        }

        if (key === 'delivered') {
            return deliveryRate !== null
                ? `${deliveryRate}% delivery rate`
                : 'No requests yet';
        }

        if (key === 'in_progress') {
            const lastRequested = formatDate(summary.last_requested_at);

            return lastRequested
                ? `Last requested ${lastRequested}`
                : 'Nothing requested yet';
        }

        return 'Requests marked as denied';
    };

    return (
        <div
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            data-tour="beneficiary-assistance-summary"
        >
            {summaryCards.map((card) => {
                const Icon = card.icon;

                return (
                    <div
                        key={card.key}
                        className="rounded-xl border border-border bg-card p-4"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-xs text-muted-foreground">
                                {card.label}
                            </p>
                            <Icon className="size-4 shrink-0 text-muted-foreground" />
                        </div>
                        <p className="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums">
                            {summary[card.key].toLocaleString()}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {descriptionFor(card.key)}
                        </p>
                    </div>
                );
            })}
        </div>
    );
}

function AssistanceSummaryCardsSkeleton() {
    return (
        <div
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            aria-busy="true"
            aria-label="Loading assistance summary"
        >
            {summaryCards.map((card) => (
                <div
                    key={card.key}
                    className="rounded-xl border border-border bg-card p-4"
                >
                    <div className="flex items-center justify-between gap-2">
                        <Skeleton className="h-3 w-24 rounded-sm" />
                        <Skeleton className="size-4 rounded-sm" />
                    </div>
                    <Skeleton className="mt-2 h-8 w-14 rounded-sm" />
                    <Skeleton className="mt-2 h-3 w-32 rounded-sm" />
                </div>
            ))}
        </div>
    );
}

function DeliveryBreakdown({
    summary,
}: {
    summary: BeneficiaryAssistanceSummary;
}) {
    const total = summary.total;

    if (total === 0) {
        return (
            <p className="text-sm text-muted-foreground/60 italic">
                No assistance requests yet
            </p>
        );
    }

    const segments = [
        {
            label: 'Delivered',
            count: summary.delivered,
            className: 'bg-emerald-500',
        },
        {
            label: 'In progress',
            count: summary.in_progress,
            className: 'bg-amber-500',
        },
        {
            label: 'Denied',
            count: summary.denied,
            className: 'bg-red-500',
        },
    ].filter((segment) => segment.count > 0);

    const deliveryRate = Math.round((summary.delivered / total) * 100);

    return (
        <div className="space-y-3">
            <div className="flex items-baseline justify-between gap-3">
                <p className="text-sm">
                    <span className="font-semibold tabular-nums">
                        {deliveryRate}%
                    </span>{' '}
                    <span className="text-muted-foreground">delivered</span>
                </p>
                <p className="text-xs tabular-nums text-muted-foreground">
                    {summary.delivered.toLocaleString()} of{' '}
                    {total.toLocaleString()}
                </p>
            </div>
            <Progress
                value={deliveryRate}
                aria-label={`Delivery rate ${deliveryRate}%`}
            />
            <div
                role="img"
                aria-label="Assistance status distribution"
                className="flex h-2 w-full gap-px overflow-hidden rounded-full bg-muted"
            >
                {segments.map((segment) => (
                    <div
                        key={segment.label}
                        className={cn('h-full', segment.className)}
                        style={{ width: `${(segment.count / total) * 100}%` }}
                        title={`${segment.label}: ${segment.count.toLocaleString()}`}
                    />
                ))}
            </div>
            <ul className="flex flex-wrap gap-x-5 gap-y-2">
                {segments.map((segment) => (
                    <li
                        key={segment.label}
                        className="flex items-center gap-1.5 text-xs"
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'size-2 shrink-0 rounded-full',
                                segment.className,
                            )}
                        />
                        <span className="text-muted-foreground">
                            {segment.label}
                        </span>
                        <span className="font-medium tabular-nums">
                            {segment.count.toLocaleString()}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function UserBeneficiaryShow({
    beneficiary,
    department,
    assistance_summary,
    assistances,
    form_options,
}: {
    beneficiary: BeneficiaryProfile;
    department: DepartmentSummary;
    assistance_summary?: BeneficiaryAssistanceSummary;
    assistances?: PaginatedBeneficiaryAssistances;
    search: string;
    form_options?: FormOptions;
}) {
    const [editOpen, setEditOpen] = useState(false);
    const details = beneficiary.details as Record<string, unknown>;
    const isOrganization = beneficiary.type === 'organization';
    const organizationMembers = isOrganization
        ? ((details.members as OrganizationMember[] | undefined) ?? [])
        : [];
    const individualOrganizations = !isOrganization
        ? ((details.organizations as
              | IndividualOrganizationMembership[]
              | undefined) ?? [])
        : [];
    const identifications = !isOrganization
        ? ((details.identifications as
              | { name: string; number: string }[]
              | undefined) ?? [])
        : [];
    const attributeFlags = [
        { label: 'Indigenous', active: details.indigenous === true },
        { label: 'PWD', active: details.pwd === true },
        {
            label: '4Ps beneficiary',
            active: details.is_4ps_beneficiary === true,
        },
        { label: 'Solo parent', active: details.is_solo_parent === true },
    ].filter((flag) => flag.active);

    const address = (details.address as string | undefined) || null;
    const otherAddress = (details.other_address as string | undefined) || null;
    const mobileNumber =
        (details.mobile_number as string | undefined) || null;
    const president = details.president as
        | { name?: string; cais_number?: string }
        | null
        | undefined;
    const membershipRows = isOrganization
        ? organizationMembers
        : individualOrganizations;
    const membershipEntityLabel = isOrganization ? 'Name' : 'Organization';
    const membershipTitle = isOrganization ? 'Members' : 'Organizations';
    const membershipDescription = isOrganization
        ? 'Individuals registered under this organization'
        : 'Organizations this individual belongs to';
    const membershipEmpty = isOrganization
        ? 'No members listed.'
        : 'Not a member of any organization.';

    const membershipColumns = useMemo(
        () =>
            createMembershipColumns({
                departmentSlug: department.slug,
                entityLabel: membershipEntityLabel,
            }),
        [department.slug, membershipEntityLabel],
    );

    const assistanceColumns = useMemo(
        () => createBeneficiaryAssistanceColumns(),
        [],
    );

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Beneficiaries',
                    href: beneficiariesIndex.url(department.slug),
                },
                {
                    title: beneficiary.cais_number,
                    href: beneficiaryShow.url({
                        department: department.slug,
                        beneficiary: beneficiary.id,
                    }),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [beneficiary.cais_number, beneficiary.id, department.slug]);

    return (
        <>
            <Head title={beneficiary.name} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {beneficiary.name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge variant="outline">
                                {isOrganization ? (
                                    <Building2 aria-hidden />
                                ) : (
                                    <UserRound aria-hidden />
                                )}
                                {isOrganization
                                    ? 'Organization'
                                    : 'Individual'}
                            </Badge>
                            <Badge variant="outline" className="font-mono">
                                {beneficiary.cais_number}
                            </Badge>
                            {isOrganization &&
                            typeof details.total_member === 'number' ? (
                                <Badge variant="outline">
                                    <Users aria-hidden />
                                    {details.total_member.toLocaleString()}{' '}
                                    members
                                </Badge>
                            ) : null}
                            {beneficiary.programs.length > 0 ? (
                                <Badge variant="outline">
                                    <FolderOpen aria-hidden />
                                    {beneficiary.programs.length}{' '}
                                    {beneficiary.programs.length === 1
                                        ? 'program'
                                        : 'programs'}
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                    <Button type="button" onClick={() => setEditOpen(true)}>
                        <Pencil className="size-4" />
                        Edit beneficiary
                    </Button>
                </div>

                {/* KPIs */}
                <WhenVisible
                    data="assistance_summary"
                    buffer={200}
                    fallback={<AssistanceSummaryCardsSkeleton />}
                >
                    {assistance_summary ? (
                        <AssistanceSummaryCards summary={assistance_summary} />
                    ) : (
                        <AssistanceSummaryCardsSkeleton />
                    )}
                </WhenVisible>

                {/* Overview: profile + side panel */}
                <section
                    data-tour="beneficiary-overview"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-col gap-4 p-4">
                        <SectionHeading
                            title="Overview"
                            description={
                                isOrganization
                                    ? 'Organization contact, membership, and linked programs'
                                    : 'Demographic details, contact, attributes, and linked programs'
                            }
                        />

                        <div className="grid gap-6 lg:grid-cols-3">
                            <div className="min-w-0 space-y-5 lg:col-span-2">
                                <div>
                                    <p className="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Profile
                                    </p>
                                    <dl className="grid gap-4 sm:grid-cols-2">
                                        {isOrganization ? (
                                            <>
                                                <DetailItem
                                                    label="President"
                                                    value={president?.name}
                                                />
                                                <DetailItem
                                                    label="Total members"
                                                    value={
                                                        details.total_member as
                                                            | number
                                                            | undefined
                                                    }
                                                />
                                                <DetailItem
                                                    label="Mobile number"
                                                    value={mobileNumber}
                                                />
                                                <DetailItem
                                                    label="Address"
                                                    value={address}
                                                />
                                            </>
                                        ) : (
                                            <>
                                                <DetailItem
                                                    label="Birthday"
                                                    value={formatDate(
                                                        details.birthday as
                                                            | string
                                                            | undefined,
                                                    )}
                                                />
                                                <DetailItem
                                                    label="Sex"
                                                    value={
                                                        details.sex as
                                                            | string
                                                            | undefined
                                                    }
                                                />
                                                <DetailItem
                                                    label="Civil status"
                                                    value={
                                                        details.civil_status as
                                                            | string
                                                            | undefined
                                                    }
                                                />
                                                <DetailItem
                                                    label="Spouse"
                                                    value={
                                                        details.spouse as
                                                            | string
                                                            | undefined
                                                    }
                                                />
                                                <DetailItem
                                                    label="Ethnicity"
                                                    value={
                                                        details.ethnicity as
                                                            | string
                                                            | undefined
                                                    }
                                                />
                                                <DetailItem
                                                    label="Mobile number"
                                                    value={mobileNumber}
                                                />
                                                <DetailItem
                                                    label="Address"
                                                    value={address}
                                                />
                                                <DetailItem
                                                    label="Other address"
                                                    value={otherAddress}
                                                />
                                            </>
                                        )}
                                    </dl>
                                </div>

                                {!isOrganization ? (
                                    <div>
                                        <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                            Attributes
                                        </p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {attributeFlags.length === 0 ? (
                                                <p className="text-sm text-muted-foreground/60 italic">
                                                    No special attributes
                                                </p>
                                            ) : (
                                                attributeFlags.map((flag) => (
                                                    <Badge
                                                        key={flag.label}
                                                        variant="secondary"
                                                    >
                                                        {flag.label}
                                                    </Badge>
                                                ))
                                            )}
                                        </div>
                                    </div>
                                ) : null}

                                {identifications.length > 0 ? (
                                    <div>
                                        <p className="mb-2 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                            <IdCard className="size-3.5" />
                                            Identifications
                                        </p>
                                        <ul className="divide-y rounded-lg border border-border">
                                            {identifications.map(
                                                (identification) => (
                                                    <li
                                                        key={`${identification.name}-${identification.number}`}
                                                        className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm"
                                                    >
                                                        <span className="text-muted-foreground">
                                                            {
                                                                identification.name
                                                            }
                                                        </span>
                                                        <span className="font-medium tabular-nums">
                                                            {
                                                                identification.number
                                                            }
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                ) : null}
                            </div>

                            <div className="min-w-0 space-y-5 lg:border-l lg:border-border lg:pl-6">
                                <div className="space-y-2">
                                    <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        <Phone className="size-3.5" />
                                        Contact
                                    </p>
                                    {mobileNumber || address ? (
                                        <div className="space-y-2 text-sm">
                                            {mobileNumber ? (
                                                <p className="flex items-start gap-2">
                                                    <Phone
                                                        className="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                                        aria-hidden
                                                    />
                                                    <span className="tabular-nums">
                                                        {mobileNumber}
                                                    </span>
                                                </p>
                                            ) : null}
                                            {address ? (
                                                <p className="flex items-start gap-2">
                                                    <MapPin
                                                        className="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                                        aria-hidden
                                                    />
                                                    <span>{address}</span>
                                                </p>
                                            ) : null}
                                            {otherAddress ? (
                                                <p className="pl-5 text-xs text-muted-foreground">
                                                    Also: {otherAddress}
                                                </p>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground/60 italic">
                                            No contact details on file
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        <HeartHandshake className="size-3.5" />
                                        Delivery rate
                                    </p>
                                    {assistance_summary === undefined ? (
                                        <div className="space-y-2" aria-busy>
                                            <Skeleton className="h-3 w-28 rounded-sm" />
                                            <Skeleton className="h-2 w-full rounded-full" />
                                        </div>
                                    ) : (
                                        <DeliveryBreakdown
                                            summary={assistance_summary}
                                        />
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        <FolderOpen className="size-3.5" />
                                        Programs
                                    </p>
                                    {beneficiary.programs.length === 0 ? (
                                        <p className="text-sm text-muted-foreground/60 italic">
                                            No programs linked yet
                                        </p>
                                    ) : (
                                        <ul className="space-y-2">
                                            {beneficiary.programs.map(
                                                (program) => (
                                                    <li
                                                        key={program.id}
                                                        className="flex items-start justify-between gap-2 text-sm"
                                                    >
                                                        <div className="min-w-0">
                                                            <p className="truncate font-medium">
                                                                {program.name}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {program
                                                                    .department
                                                                    ?.name ??
                                                                    '—'}
                                                            </p>
                                                        </div>
                                                        <Badge
                                                            variant="outline"
                                                            className="shrink-0"
                                                        >
                                                            {program.is_organization
                                                                ? 'Org'
                                                                : 'Ind'}
                                                        </Badge>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                    {beneficiary.assistances_count > 0 ? (
                                        <p className="text-xs tabular-nums text-muted-foreground">
                                            {beneficiary.assistances_count.toLocaleString()}{' '}
                                            total assistance{' '}
                                            {beneficiary.assistances_count ===
                                            1
                                                ? 'request'
                                                : 'requests'}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Memberships */}
                <section
                    data-tour="beneficiary-memberships"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-col gap-4 p-4">
                        <SectionHeading
                            title={membershipTitle}
                            description={membershipDescription}
                        />
                        {membershipRows.length === 0 ? (
                            <p className="text-sm text-muted-foreground/60 italic">
                                {membershipEmpty}
                            </p>
                        ) : (
                            <DataTable
                                columns={membershipColumns}
                                data={membershipRows}
                                emptyMessage={membershipEmpty}
                                toolbar={(table, columnVisibility) => (
                                    <BeneficiaryShowTableToolbar
                                        table={table}
                                        columnVisibility={columnVisibility}
                                    />
                                )}
                            />
                        )}
                    </div>
                </section>

                {/* Assistances */}
                <section
                    data-tour="beneficiary-assistances"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-col gap-4 p-4">
                        <SectionHeading
                            title="Assistances"
                            description="Assistance records for this beneficiary"
                        />
                        <WhenVisible
                            data="assistances"
                            fallback={
                                <DataTableSkeleton
                                    columnCount={5}
                                    rowCount={5}
                                />
                            }
                        >
                            <DataTable
                                columns={assistanceColumns}
                                data={assistances?.data ?? []}
                                emptyMessage="No assistances found."
                                toolbar={(table, columnVisibility) => (
                                    <BeneficiaryShowTableToolbar
                                        table={table}
                                        columnVisibility={columnVisibility}
                                    />
                                )}
                            />
                        </WhenVisible>
                    </div>
                </section>
            </div>

            <BeneficiaryEditDrawer
                open={editOpen}
                onOpenChange={setEditOpen}
                department={department}
                beneficiaryId={beneficiary.id}
                beneficiaryType={beneficiary.type}
                formOptions={form_options}
            />
        </>
    );
}
