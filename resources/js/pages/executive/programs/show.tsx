import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { type ReactNode, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ProgramDecisionDialog } from '@/components/user/programs/program-decision-dialog';
import type { ProgramDecisionAction } from '@/components/user/programs/program-decision-dialog';
import {
    approve as approveProgram,
    index as governorProgramsIndex,
    returnMethod as returnProgram,
    show as governorProgramShow,
} from '@/routes/executive/programs';
import type { BreadcrumbItem } from '@/types';

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
}: {
    program: GovernorProgram;
}) {
    const canDecide = program.approval_status === 'awaiting_governor';
    const description = program.descriptions?.trim();
    const hasEligibility =
        program.eligibility.cooldown_days !== null ||
        program.eligibility.requirements.length > 0 ||
        program.eligibility.item_caps.length > 0;
    const [decision, setDecision] = useState<Extract<
        ProgramDecisionAction,
        'approve' | 'return'
    > | null>(null);

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
                        <Link href={governorProgramsIndex()}>
                            Back to programs
                        </Link>
                    </Button>
                </div>

                {canDecide ? (
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            onClick={() => setDecision('approve')}
                        >
                            Approve
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDecision('return')}
                        >
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
                    action={decision ?? 'approve'}
                    url={
                        decision === 'return'
                            ? returnProgram.url(program.id)
                            : approveProgram.url(program.id)
                    }
                />

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
            </div>
        </>
    );
}
