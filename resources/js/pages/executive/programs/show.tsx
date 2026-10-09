import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    approve as approveProgram,
    index as governorProgramsIndex,
    returnMethod as returnProgram,
    show as governorProgramShow,
} from '@/routes/executive/programs';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';

type GovernorProgram = {
    id: number;
    name: string;
    descriptions: string | null;
    kind: string | null;
    approval_status: string;
    approval_label: string;
    requires_beneficiaries: boolean;
    beneficiary_approval_label: string;
    department: { id: number; name: string } | null;
};

type BeneficiaryRow = {
    id: number;
    beneficiary_name: string | null;
    cais_number: string | null;
    status: string | null;
    sub_status: string | null;
};

type PaginatedBeneficiaries = {
    data: BeneficiaryRow[];
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function GovernorProgramShow({
    program,
    beneficiaries,
}: {
    program: GovernorProgram;
    beneficiaries: PaginatedBeneficiaries;
}) {
    const canDecide = program.approval_status === 'awaiting_governor';

    useEffect(() => {
        const breadcrumbs: BreadcrumbItem[] = [
            {
                title: 'Executive',
                href: governorProgramsIndex(),
            },
            {
                title: program.name,
                href: governorProgramShow(program.id),
            },
        ];

        setLayoutProps({ breadcrumbs });
    }, [program.id, program.name]);

    return (
        <>
            <Head title={program.name} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {program.name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">{program.approval_label}</Badge>
                            <Badge variant="outline">
                                {program.beneficiary_approval_label}
                            </Badge>
                            {program.department ? (
                                <Badge variant="outline">{program.department.name}</Badge>
                            ) : null}
                        </div>
                        {program.descriptions ? (
                            <p className="max-w-3xl text-sm text-muted-foreground">
                                {program.descriptions}
                            </p>
                        ) : null}
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={governorProgramsIndex()}>Back to programs</Link>
                    </Button>
                </div>

                {canDecide ? (
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Form {...approveProgram.form.post(program.id)}>
                            {({ processing, errors }) => (
                                <div className="space-y-2">
                                    <Button type="submit" disabled={processing}>
                                        Approve
                                    </Button>
                                    <InputError message={errors.program} />
                                </div>
                            )}
                        </Form>
                        <Form
                            {...returnProgram.form.post(program.id)}
                            className="flex flex-1 flex-col gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Textarea
                                        name="remark"
                                        placeholder="Remark for the department"
                                    />
                                    <InputError message={errors.remark} />
                                    <InputError message={errors.program} />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                        className="w-fit"
                                    >
                                        Return to department
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Beneficiaries</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {!program.requires_beneficiaries ? (
                            <p className="mb-4 text-sm text-muted-foreground">
                                This approval covers the program. Staff add
                                beneficiaries after it is approved.
                            </p>
                        ) : null}
                        {beneficiaries.data.length === 0 &&
                        program.requires_beneficiaries ? (
                            <p className="text-sm text-muted-foreground">
                                This program has no beneficiaries yet.
                            </p>
                        ) : null}
                        {beneficiaries.data.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>CAIS number</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Detail</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {beneficiaries.data.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                {row.beneficiary_name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {row.cais_number ?? '—'}
                                            </TableCell>
                                            <TableCell>{row.status ?? '—'}</TableCell>
                                            <TableCell>
                                                {row.sub_status ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : null}
                        {beneficiaries.last_page > 1 ? (
                            <div className="mt-4 flex gap-2">
                                {beneficiaries.prev_page_url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={beneficiaries.prev_page_url}>
                                            Previous
                                        </Link>
                                    </Button>
                                ) : null}
                                {beneficiaries.next_page_url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={beneficiaries.next_page_url}>
                                            Next
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
