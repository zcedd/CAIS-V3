'use client';

import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { BeneficiaryEditDrawer } from '@/pages/user/beneficiaries/beneficiary-edit-drawer';
import { show as assistanceShow } from '@/routes/user/assistances';
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
import { Head, Link, setLayoutProps, WhenVisible } from '@inertiajs/react';
import {
    Building2,
    Clock3,
    FolderOpen,
    HeartHandshake,
    MapPin,
    PackageCheck,
    Pencil,
    Phone,
    UserRound,
    Users,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

const STATUS_BADGE_CLASSES: Record<string, string> = {
    Delivered:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    Verified:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
    Pending:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    Denied: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
};

function statusBadge(status: string) {
    return (
        <Badge
            variant="outline"
            className={cn(STATUS_BADGE_CLASSES[status] ?? '')}
        >
            {status}
        </Badge>
    );
}

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
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium">{String(value)}</dd>
        </div>
    );
}

function HeaderChip({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/40 px-2 py-1 text-xs text-muted-foreground">
            <Icon className="size-3.5 shrink-0" aria-hidden />
            {children}
        </span>
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
            return summary.last_requested_at
                ? `Last requested ${formatDate(summary.last_requested_at)}`
                : 'Nothing requested yet';
        }

        return 'Requests marked as denied';
    };

    return (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
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

const tableHeadClass =
    'text-xs tracking-wide text-muted-foreground uppercase';

function MembershipTable({
    rows,
    entityLabel,
    departmentSlug,
}: {
    rows: (OrganizationMember | IndividualOrganizationMembership)[];
    entityLabel: string;
    departmentSlug: string;
}) {
    return (
        <div className="overflow-hidden rounded-lg border border-border">
            <Table>
                <TableHeader className="bg-muted/50">
                    <TableRow className="hover:bg-transparent">
                        <TableHead className={cn('pl-4', tableHeadClass)}>
                            CAIS Number
                        </TableHead>
                        <TableHead className={tableHeadClass}>
                            {entityLabel}
                        </TableHead>
                        <TableHead className={cn('pr-4', tableHeadClass)}>
                            Role
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.id}>
                            <TableCell className="py-3 pl-4">
                                {row.beneficiary_id ? (
                                    <Link
                                        href={beneficiaryShow.url({
                                            department: departmentSlug,
                                            beneficiary: row.beneficiary_id,
                                        })}
                                        className="font-medium text-primary hover:underline"
                                    >
                                        {row.cais_number}
                                    </Link>
                                ) : (
                                    row.cais_number
                                )}
                            </TableCell>
                            <TableCell className="py-3 font-medium">
                                {row.name}
                            </TableCell>
                            <TableCell className="py-3 pr-4">
                                {row.is_president ? (
                                    <Badge variant="secondary">President</Badge>
                                ) : (
                                    <Badge variant="outline">Member</Badge>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
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
        { label: '4Ps beneficiary', active: details.is_4ps_beneficiary === true },
        { label: 'Solo parent', active: details.is_solo_parent === true },
    ].filter((flag) => flag.active);

    const address = details.address as string | undefined;
    const mobileNumber = details.mobile_number as string | undefined;

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Beneficiaries',
                    href: beneficiariesIndex.url(department.slug),
                },
                {
                    title: beneficiary.cais_number,
                    href: '#',
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [beneficiary.cais_number, department.slug]);

    return (
        <>
            <Head title={beneficiary.name} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {beneficiary.name}
                            </h1>
                            {isOrganization ? (
                                <Badge variant="secondary">
                                    <Building2 aria-hidden />
                                    Organization
                                </Badge>
                            ) : (
                                <Badge variant="outline">
                                    <UserRound aria-hidden />
                                    Individual
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            CAIS{' '}
                            <span className="font-medium text-foreground">
                                {beneficiary.cais_number}
                            </span>
                        </p>
                        <div className="flex flex-wrap items-center gap-1.5">
                            {address ? (
                                <HeaderChip icon={MapPin}>{address}</HeaderChip>
                            ) : null}
                            {mobileNumber ? (
                                <HeaderChip icon={Phone}>
                                    {mobileNumber}
                                </HeaderChip>
                            ) : null}
                            {isOrganization &&
                                typeof details.total_member === 'number' ? (
                                <HeaderChip icon={Users}>
                                    {details.total_member.toLocaleString()}{' '}
                                    members
                                </HeaderChip>
                            ) : null}
                            {beneficiary.programs.length > 0 ? (
                                <HeaderChip icon={FolderOpen}>
                                    {beneficiary.programs.length}{' '}
                                    {beneficiary.programs.length === 1
                                        ? 'program'
                                        : 'programs'}
                                </HeaderChip>
                            ) : null}
                        </div>
                    </div>
                    <Button type="button" onClick={() => setEditOpen(true)}>
                        <Pencil className="size-4" />
                        Edit beneficiary
                    </Button>
                </div>

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

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Profile</CardTitle>
                            <CardDescription>
                                Beneficiary demographic and contact details.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <dl className="grid gap-4 sm:grid-cols-2">
                                {isOrganization ? (
                                    <>
                                        <DetailItem
                                            label="Mobile number"
                                            value={mobileNumber}
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
                                            label="Address"
                                            value={address}
                                        />
                                        <DetailItem
                                            label="President"
                                            value={
                                                (
                                                    details.president as {
                                                        name?: string;
                                                    } | null
                                                )?.name
                                            }
                                        />
                                    </>
                                ) : (
                                    <>
                                        <DetailItem
                                            label="Birthday"
                                            value={
                                                details.birthday
                                                    ? formatDate(
                                                        details.birthday as string,
                                                    )
                                                    : null
                                            }
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
                                            label="Mobile number"
                                            value={mobileNumber}
                                        />
                                        <DetailItem
                                            label="Address"
                                            value={address}
                                        />
                                        <DetailItem
                                            label="Other address"
                                            value={
                                                details.other_address as
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
                                            label="Spouse"
                                            value={
                                                details.spouse as
                                                | string
                                                | undefined
                                            }
                                        />
                                    </>
                                )}
                            </dl>

                            {!isOrganization ? (
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Attributes
                                    </p>
                                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                                        {attributeFlags.length === 0 ? (
                                            <span className="text-sm text-muted-foreground/60">
                                                None
                                            </span>
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
                                    <p className="text-xs text-muted-foreground">
                                        Identifications
                                    </p>
                                    <ul className="mt-1.5 space-y-1">
                                        {identifications.map(
                                            (identification) => (
                                                <li
                                                    key={`${identification.name}-${identification.number}`}
                                                    className="flex items-center justify-between gap-2 text-sm"
                                                >
                                                    <span className="text-muted-foreground">
                                                        {identification.name}
                                                    </span>
                                                    <span className="font-medium tabular-nums">
                                                        {identification.number}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Programs</CardTitle>
                            <CardDescription>
                                Programs linked through assistances (
                                {beneficiary.assistances_count.toLocaleString()}{' '}
                                total requests).
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {beneficiary.programs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No programs linked yet.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {beneficiary.programs.map((program) => (
                                        <li
                                            key={program.id}
                                            className="flex items-center justify-between gap-2 py-3 text-sm"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {program.name}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {program.department?.name ??
                                                        '—'}
                                                </p>
                                            </div>
                                            <Badge variant="outline">
                                                {program.is_organization
                                                    ? 'Organization'
                                                    : 'Individual'}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {isOrganization ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Members</CardTitle>
                            <CardDescription>
                                Individuals registered under this organization.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {organizationMembers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No members listed.
                                </p>
                            ) : (
                                <MembershipTable
                                    rows={organizationMembers}
                                    entityLabel="Name"
                                    departmentSlug={department.slug}
                                />
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Organizations
                            </CardTitle>
                            <CardDescription>
                                Organizations this individual belongs to.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {individualOrganizations.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Not a member of any organization.
                                </p>
                            ) : (
                                <MembershipTable
                                    rows={individualOrganizations}
                                    entityLabel="Organization"
                                    departmentSlug={department.slug}
                                />
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Assistances</CardTitle>
                        <CardDescription>
                            Assistance records for this beneficiary.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <WhenVisible
                            data="assistances"
                            fallback={
                                <DataTableSkeleton
                                    columnCount={5}
                                    rowCount={5}
                                />
                            }
                        >
                            {(assistances?.data ?? []).length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No assistances found.
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-lg border border-border">
                                    <Table>
                                        <TableHeader className="bg-muted/50">
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead
                                                    className={cn(
                                                        'pl-4',
                                                        tableHeadClass,
                                                    )}
                                                >
                                                    Program
                                                </TableHead>
                                                <TableHead
                                                    className={tableHeadClass}
                                                >
                                                    Department
                                                </TableHead>
                                                <TableHead
                                                    className={tableHeadClass}
                                                >
                                                    Mode
                                                </TableHead>
                                                <TableHead
                                                    className={tableHeadClass}
                                                >
                                                    Requested
                                                </TableHead>
                                                <TableHead
                                                    className={cn(
                                                        'pr-4',
                                                        tableHeadClass,
                                                    )}
                                                >
                                                    Status
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {(assistances?.data ?? []).map(
                                                (row) => (
                                                    <TableRow key={row.id}>
                                                        <TableCell className="py-3 pl-4">
                                                            {row.department_slug ? (
                                                                <Link
                                                                    href={assistanceShow.url(
                                                                        {
                                                                            department:
                                                                                row.department_slug,
                                                                            program:
                                                                                row.program_id,
                                                                            assistance:
                                                                                row.id,
                                                                        },
                                                                    )}
                                                                    className="font-medium text-primary hover:underline"
                                                                >
                                                                    {
                                                                        row.program_name
                                                                    }
                                                                </Link>
                                                            ) : (
                                                                <span className="font-medium">
                                                                    {
                                                                        row.program_name
                                                                    }
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="py-3 text-muted-foreground">
                                                            {
                                                                row.department_name
                                                            }
                                                        </TableCell>
                                                        <TableCell className="py-3 text-muted-foreground">
                                                            {
                                                                row.mode_of_request
                                                            }
                                                        </TableCell>
                                                        <TableCell className="py-3 tabular-nums text-muted-foreground">
                                                            {formatDate(
                                                                row.date_requested,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="py-3 pr-4">
                                                            {statusBadge(
                                                                row.status,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </WhenVisible>
                    </CardContent>
                </Card>
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
