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
import { BeneficiaryEditDrawer } from '@/pages/user/beneficiaries/beneficiary-edit-drawer';
import { index as beneficiariesIndex } from '@/routes/user/beneficiaries';
import { show as assistanceShow } from '@/routes/user/assistances';
import type {
    BeneficiaryProfile,
    DepartmentSummary,
    FormOptions,
    PaginatedBeneficiaryAssistances,
} from '@/types/beneficiary';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, setLayoutProps, WhenVisible } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';

function DetailItem({
    label,
    value,
}: {
    label: string;
    value: string | number | boolean | null | undefined;
}) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const display =
        typeof value === 'boolean' ? (value ? 'Yes' : 'No') : String(value);

    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{display}</dd>
        </div>
    );
}

export default function UserBeneficiaryShow({
    beneficiary,
    department,
    assistances,
    form_options,
}: {
    beneficiary: BeneficiaryProfile;
    department: DepartmentSummary;
    assistances?: PaginatedBeneficiaryAssistances;
    search: string;
    form_options?: FormOptions;
}) {
    const [editOpen, setEditOpen] = useState(false);
    const details = beneficiary.details as Record<string, unknown>;

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
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {beneficiary.name}
                            </h1>
                            <Badge
                                variant={
                                    beneficiary.type === 'organization'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {beneficiary.type === 'organization'
                                    ? 'Organization'
                                    : 'Individual'}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            CAIS {beneficiary.cais_number}
                        </p>
                    </div>
                    <Button type="button" onClick={() => setEditOpen(true)}>
                        <Pencil className="size-4" />
                        Edit beneficiary
                    </Button>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Profile</CardTitle>
                            <CardDescription>
                                Beneficiary demographic and contact details.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                {beneficiary.type === 'individual' ? (
                                    <>
                                        <DetailItem
                                            label="Birthday"
                                            value={
                                                details.birthday as
                                                    | string
                                                    | undefined
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
                                            label="Mobile number"
                                            value={
                                                details.mobile_number as
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
                                            label="Address"
                                            value={
                                                details.address as
                                                    | string
                                                    | undefined
                                            }
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
                                            label="Indigenous"
                                            value={
                                                details.indigenous as
                                                    | boolean
                                                    | undefined
                                            }
                                        />
                                        <DetailItem
                                            label="PWD"
                                            value={
                                                details.pwd as
                                                    | boolean
                                                    | undefined
                                            }
                                        />
                                        <DetailItem
                                            label="4Ps beneficiary"
                                            value={
                                                details.is_4ps_beneficiary as
                                                    | boolean
                                                    | undefined
                                            }
                                        />
                                        <DetailItem
                                            label="Solo parent"
                                            value={
                                                details.is_solo_parent as
                                                    | boolean
                                                    | undefined
                                            }
                                        />
                                    </>
                                ) : (
                                    <>
                                        <DetailItem
                                            label="Mobile number"
                                            value={
                                                details.mobile_number as
                                                    | string
                                                    | undefined
                                            }
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
                                            value={
                                                details.address as
                                                    | string
                                                    | undefined
                                            }
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
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Programs</CardTitle>
                            <CardDescription>
                                Programs linked through assistances (
                                {beneficiary.assistances_count} total).
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
                                            className="flex items-center justify-between py-3 text-sm"
                                        >
                                            <div>
                                                <p className="font-medium">
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
                                <DataTableSkeleton columnCount={5} rowCount={5} />
                            }
                        >
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="pb-3 pr-4 font-medium">
                                                Program
                                            </th>
                                            <th className="pb-3 pr-4 font-medium">
                                                Department
                                            </th>
                                            <th className="pb-3 pr-4 font-medium">
                                                Mode
                                            </th>
                                            <th className="pb-3 pr-4 font-medium">
                                                Requested
                                            </th>
                                            <th className="pb-3 font-medium">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(assistances?.data ?? []).length ===
                                        0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No assistances found.
                                                </td>
                                            </tr>
                                        ) : (
                                            (assistances?.data ?? []).map(
                                                (row) => (
                                                    <tr
                                                        key={row.id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-3 pr-4">
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
                                                                row.program_name
                                                            )}
                                                        </td>
                                                        <td className="py-3 pr-4">
                                                            {row.department_name}
                                                        </td>
                                                        <td className="py-3 pr-4">
                                                            {row.mode_of_request}
                                                        </td>
                                                        <td className="py-3 pr-4">
                                                            {row.date_requested ??
                                                                '—'}
                                                        </td>
                                                        <td className="py-3">
                                                            {row.status}
                                                        </td>
                                                    </tr>
                                                ),
                                            )
                                        )}
                                    </tbody>
                                </table>
                            </div>
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
