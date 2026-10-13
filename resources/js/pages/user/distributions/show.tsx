import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { AssistanceLines } from '@/components/distribution/assistance-lines';
import { DistributionComposition } from '@/components/distribution/composition';
import type { DistributionCompositionData } from '@/components/distribution/composition';
import { DistributionDecisionDialog } from '@/components/distribution/decision-dialog';
import {
    ConfirmActionButton,
    StatusDialog,
} from '@/components/distribution/status-dialog';
import type { VisitCallbacks } from '@/components/distribution/status-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    cancel,
    complete,
    decide,
    edit,
    report as exportReport,
    finalize,
    index as distributionsIndex,
    reopen,
    schedule,
    show as distributionShow,
    venue,
    start,
    submit,
    validate,
} from '@/routes/user/distributions';
import {
    show as documentShow,
    store as storeDocument,
} from '@/routes/user/distributions/documents';
import {
    bulkDecide,
    decide as decideItem,
} from '@/routes/user/distributions/items';
import { store as storeRelease } from '@/routes/user/distributions/releases';

type VisitData = {
    [key: string]:
        | string
        | number
        | boolean
        | null
        | undefined
        | VisitData
        | VisitData[]
        | Array<string | number | null>;
};

type DepartmentSummary = { slug: string; name: string };

type DistributionAbilities = {
    update: boolean;
    validate: boolean;
    submit: boolean;
    approve: boolean;
    return: boolean;
    reject: boolean;
    modify: boolean;
    finalize: boolean;
    schedule: boolean;
    execute: boolean;
    complete: boolean;
    cancel: boolean;
    reopen: boolean;
};

const closedAbilities: DistributionAbilities = {
    update: false,
    validate: false,
    submit: false,
    approve: false,
    return: false,
    reject: false,
    modify: false,
    finalize: false,
    schedule: false,
    execute: false,
    complete: false,
    cancel: false,
    reopen: false,
};

type Line = {
    id: number;
    item_name: string | null;
    kind: string | null;
    unit?: string | null;
    proposed_quantity: string | null;
    approved_quantity: string | null;
    proposed_amount: string | null;
    approved_amount: string | null;
};

type Release = {
    id: number;
    outcome: string;
    actual_quantity: string | null;
    actual_amount: string | null;
    receipt_reference: string | null;
    remarks: string | null;
};

type Item = {
    id: number;
    approval_status: string;
    approval_label: string;
    review_remarks: string | null;
    exclusion_reason: string | null;
    warnings: string[];
    beneficiary: { name: string | null; cais_number: string | null };
    request_status: string | null;
    lines: Line[];
    releases: Release[];
    distributable: boolean;
};

type Batch = {
    id: number;
    batch_code: string;
    distribution_type_label?: string;
    approval_requirement?: string;
    title: string;
    description: string | null;
    venue: string | null;
    scheduled_at: string | null;
    status: string;
    status_label: string;
    revision: number;
    lock_version: number;
    finalization_label: string | null;
    beneficiary_count: number;
    approved_beneficiary_count: number;
    proposed_cash_total: string;
    approved_cash_total: string;
    program: { name: string | null };
    creator: { name: string | null };
    validation_summary: {
        errors?: string[];
        warnings?: string[];
    } | null;
    policy: {
        name: string;
        strategy: string;
        allow_partial_finalization: boolean;
        allow_amount_modification: boolean;
        allow_resubmit_after_reject: boolean;
        stages: { name: string; sequence: number; role_name: string | null }[];
    } | null;
    items: Item[];
    tasks: {
        id: number;
        revision: number;
        status: string;
        stage: string | null;
    }[];
    decisions: {
        id: number;
        decision: string;
        remarks: string | null;
        actor: string | null;
        created_at: string | null;
        revision: number;
    }[];
    documents: { id: number; original_name: string }[];
    reconciliation: {
        approved_beneficiaries: number;
        served_beneficiaries: number;
        partial_beneficiaries: number;
        absent_beneficiaries: number;
        excluded_beneficiaries: number;
        unserved_beneficiaries: number;
        approved_cash: string;
        actual_cash: string;
        remaining_cash: string;
        ready_to_complete: boolean;
        composition: DistributionCompositionData;
    };
};

function routeArgs(department: string, batchId: number) {
    return { department, distributionBatch: batchId };
}

function messageList(value: string | string[] | undefined): string[] {
    if (value == null) {
        return [];
    }

    return (Array.isArray(value) ? value : [value]).filter(
        (message) => message !== '',
    );
}

export default function DistributionShow({
    department,
    batch,
    can = closedAbilities,
}: {
    department: DepartmentSummary;
    batch: Batch;
    can?: DistributionAbilities;
}) {
    const [selected, setSelected] = useState<number[]>([]);
    const args = routeArgs(department.slug, batch.id);
    const batchDecisions = [
        can.approve ? { value: 'approved', label: 'Approve' } : null,
        can.return ? { value: 'returned', label: 'Return' } : null,
        can.reject ? { value: 'rejected', label: 'Reject' } : null,
    ].filter(
        (choice): choice is { value: string; label: string } => choice !== null,
    );
    const pageErrors = (usePage().props.errors ?? {}) as Record<
        string,
        string | string[]
    >;
    const validationErrors = [
        ...messageList(pageErrors.roster),
        ...messageList(pageErrors.status),
        ...(batch.validation_summary?.errors ?? []),
    ].filter((message, index, messages) => messages.indexOf(message) === index);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Distributions',
                    href: distributionsIndex.url(department.slug),
                },
                {
                    title: batch.batch_code,
                    href: distributionShow.url({
                        department: department.slug,
                        distributionBatch: batch.id,
                    }),
                },
            ],
        });
    }, [batch.batch_code, batch.id, department.slug]);

    function post(
        url: string,
        data: VisitData = {},
        visit: VisitCallbacks = {},
    ): void {
        router.post(url, data, { preserveScroll: true, ...visit });
    }

    return (
        <>
            <Head title={batch.batch_code} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {batch.batch_code} · Revision {batch.revision}
                        </p>
                        <h1 className="text-2xl font-semibold">
                            {batch.title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {batch.program.name} · {department.name} ·{' '}
                            {batch.venue}
                        </p>
                        {batch.distribution_type_label ? (
                            <p className="mt-2 text-sm">
                                <Badge variant="outline">
                                    {batch.distribution_type_label}
                                </Badge>
                                {batch.approval_requirement ? (
                                    <span className="ml-2 text-muted-foreground">
                                        {batch.approval_requirement}
                                    </span>
                                ) : null}
                            </p>
                        ) : null}
                    </div>
                    <Badge variant="outline">{batch.status_label}</Badge>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <Summary
                        label="Beneficiaries"
                        value={`${batch.approved_beneficiary_count} approved / ${batch.beneficiary_count}`}
                    />
                </div>
                <DistributionComposition
                    composition={batch.reconciliation.composition}
                />

                {batch.finalization_label && (
                    <p className="text-sm">{batch.finalization_label}</p>
                )}

                {validationErrors.length > 0 && (
                    <ul className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm">
                        {validationErrors.map((error) => (
                            <li key={error}>{error}</li>
                        ))}
                    </ul>
                )}

                {(batch.validation_summary?.warnings ?? []).length > 0 && (
                    <ul className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        {batch.validation_summary?.warnings?.map((warning) => (
                            <li key={warning}>{warning}</li>
                        ))}
                    </ul>
                )}

                <div className="flex flex-wrap gap-2">
                    {can.update &&
                        ['draft', 'returned'].includes(batch.status) && (
                            <Button variant="outline" asChild>
                                <Link href={edit.url(args)}>Edit roster</Link>
                            </Button>
                        )}
                    {can.validate &&
                        ['draft', 'returned'].includes(batch.status) && (
                            <ConfirmActionButton
                                label="Validate"
                                title="Validate this batch?"
                                description="The roster is checked before it can be submitted for approval."
                                status="For validation"
                                onConfirm={(visit) =>
                                    post(validate.url(args), {}, visit)
                                }
                            />
                        )}
                    {can.submit && batch.status === 'for_validation' && (
                        <ConfirmActionButton
                            label="Submit for approval"
                            title="Submit this batch for approval?"
                            description="Reviewers will receive the current roster."
                            status="For approval"
                            onConfirm={(visit) =>
                                post(submit.url(args), {}, visit)
                            }
                        />
                    )}
                    {can.schedule && batch.status === 'approved' && (
                        <ScheduleForm
                            onSubmit={(
                                scheduledAt,
                                venueName,
                                details,
                                visit,
                            ) =>
                                post(
                                    schedule.url(args),
                                    {
                                        scheduled_at: scheduledAt,
                                        venue: venueName,
                                        venue_details: details,
                                        lock_version: batch.lock_version,
                                    },
                                    visit,
                                )
                            }
                        />
                    )}
                    {can.reopen &&
                        batch.status === 'rejected' &&
                        batch.policy?.allow_resubmit_after_reject && (
                            <ConfirmActionButton
                                label="Reopen as draft"
                                buttonVariant="outline"
                                title="Reopen this batch?"
                                description="The rejected batch becomes a new draft. Earlier decisions stay in the history."
                                status="Draft"
                                statusVariant="secondary"
                                onConfirm={(visit) =>
                                    post(reopen.url(args), {}, visit)
                                }
                            />
                        )}
                    {can.execute && batch.status === 'scheduled' && (
                        <ConfirmActionButton
                            label="Start distribution"
                            title="Start this distribution?"
                            description="Officers can record releases after the batch is in progress."
                            status="In progress"
                            onConfirm={(visit) =>
                                post(start.url(args), {}, visit)
                            }
                        />
                    )}
                    {(batch.status === 'scheduled' ||
                        batch.status === 'in_progress') && (
                        <Button variant="outline" asChild>
                            <Link href={venue.url(args)}>Venue desk</Link>
                        </Button>
                    )}
                    {can.complete && batch.status === 'in_progress' && (
                        <ConfirmActionButton
                            label="Complete"
                            title="Complete this distribution?"
                            description="The batch closes after every approved beneficiary has a release outcome."
                            status="Completed"
                            statusVariant="default"
                            disabled={!batch.reconciliation.ready_to_complete}
                            onConfirm={(visit) =>
                                post(complete.url(args), {}, visit)
                            }
                        />
                    )}
                    {can.cancel &&
                        ![
                            'completed',
                            'cancelled',
                            'rejected',
                            'in_progress',
                        ].includes(batch.status) && (
                            <CancelForm
                                onSubmit={(reason, visit) =>
                                    post(
                                        cancel.url(args),
                                        {
                                            reason,
                                            lock_version: batch.lock_version,
                                        },
                                        visit,
                                    )
                                }
                            />
                        )}
                </div>

                {batch.status === 'for_approval' &&
                    (batchDecisions.length > 0 ||
                        can.approve ||
                        can.finalize) && (
                        <div className="flex flex-wrap gap-2">
                            {batchDecisions.length > 0 ? (
                                <DistributionDecisionDialog
                                    triggerLabel="Approve whole batch"
                                    title="Approve the whole batch"
                                    description="Approve, return, or reject every beneficiary still on this batch."
                                    choices={batchDecisions}
                                    reasonRequired={['returned', 'rejected']}
                                    onDecide={(payload, visit) =>
                                        post(
                                            decide.url(args),
                                            {
                                                decision: payload.decision,
                                                remarks: payload.remarks,
                                                lock_version:
                                                    batch.lock_version,
                                            },
                                            visit,
                                        )
                                    }
                                />
                            ) : null}
                            {can.approve ? (
                                <ConfirmActionButton
                                    label="Approve selected"
                                    buttonVariant="outline"
                                    title="Approve selected beneficiaries?"
                                    description={`Approve ${selected.length} selected ${selected.length === 1 ? 'beneficiary' : 'beneficiaries'}.`}
                                    status="Approved"
                                    statusVariant="default"
                                    disabled={selected.length === 0}
                                    onConfirm={(visit) =>
                                        post(
                                            bulkDecide.url(args),
                                            {
                                                item_ids: selected,
                                                decision: 'approved',
                                            },
                                            {
                                                ...visit,
                                                onSuccess: () => {
                                                    visit.onSuccess?.();
                                                    setSelected([]);
                                                },
                                            },
                                        )
                                    }
                                />
                            ) : null}
                            {can.finalize ? (
                                <ConfirmActionButton
                                    label="Finalize review"
                                    title="Finalize this review?"
                                    description="Lock in the individual beneficiary decisions."
                                    status="Finalized"
                                    statusVariant="default"
                                    onConfirm={(visit) =>
                                        post(finalize.url(args), {}, visit)
                                    }
                                />
                            ) : null}
                        </div>
                    )}

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Beneficiary</TableHead>
                                    <TableHead>Assistance</TableHead>
                                    <TableHead>Decision</TableHead>
                                    <TableHead>Release</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {batch.items.map((item) => (
                                    <TableRow
                                        key={item.id}
                                        className="align-top"
                                    >
                                        <TableCell className="whitespace-normal">
                                            {can.approve &&
                                                batch.status ===
                                                    'for_approval' &&
                                                item.approval_status ===
                                                    'pending' && (
                                                    <Checkbox
                                                        className="mr-2"
                                                        checked={selected.includes(
                                                            item.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setSelected(
                                                                (current) =>
                                                                    checked ===
                                                                    true
                                                                        ? [
                                                                              ...current,
                                                                              item.id,
                                                                          ]
                                                                        : current.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  item.id,
                                                                          ),
                                                            )
                                                        }
                                                        aria-label={`Select ${item.beneficiary.name}`}
                                                    />
                                                )}
                                            <div className="font-medium">
                                                {item.beneficiary.name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {item.beneficiary.cais_number}
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            <AssistanceLines
                                                lines={item.lines}
                                            />
                                            {item.request_status && (
                                                <div className="text-muted-foreground">
                                                    {item.request_status}
                                                </div>
                                            )}
                                            {item.warnings.map((warning) => (
                                                <div
                                                    key={warning}
                                                    className="text-amber-700"
                                                >
                                                    {warning}
                                                </div>
                                            ))}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            <Badge variant="outline">
                                                {item.approval_label}
                                            </Badge>
                                            {item.exclusion_reason && (
                                                <div>
                                                    {item.exclusion_reason}
                                                </div>
                                            )}
                                            {can.approve &&
                                                batch.status ===
                                                    'for_approval' &&
                                                item.approval_status ===
                                                    'pending' && (
                                                    <DistributionDecisionDialog
                                                        triggerLabel="Record decision"
                                                        triggerVariant="outline"
                                                        triggerSize="sm"
                                                        title={`Decide ${item.beneficiary.name ?? 'beneficiary'}`}
                                                        description="This decision applies only to this beneficiary."
                                                        choices={[
                                                            {
                                                                value: 'approved',
                                                                label: 'Approve',
                                                            },
                                                            {
                                                                value: 'returned',
                                                                label: 'Return',
                                                            },
                                                            {
                                                                value: 'excluded',
                                                                label: 'Exclude',
                                                            },
                                                        ]}
                                                        reasonRequired={[
                                                            'returned',
                                                            'excluded',
                                                        ]}
                                                        lines={
                                                            can.modify &&
                                                            batch.policy
                                                                ?.allow_amount_modification
                                                                ? item.lines.map(
                                                                      (
                                                                          line,
                                                                      ) => ({
                                                                          id: line.id,
                                                                          label:
                                                                              line.item_name ??
                                                                              'Line',
                                                                          kind:
                                                                              line.kind ===
                                                                              'cash'
                                                                                  ? 'amount'
                                                                                  : 'quantity',
                                                                      }),
                                                                  )
                                                                : []
                                                        }
                                                        onDecide={(
                                                            payload,
                                                            visit,
                                                        ) =>
                                                            post(
                                                                decideItem.url({
                                                                    ...args,
                                                                    item: item.id,
                                                                }),
                                                                {
                                                                    decision:
                                                                        payload.decision,
                                                                    remarks:
                                                                        payload.remarks,
                                                                    exclusion_reason:
                                                                        payload.remarks,
                                                                    lines: payload.lines,
                                                                },
                                                                visit,
                                                            )
                                                        }
                                                    />
                                                )}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {item.releases.map((release) => (
                                                <div key={release.id}>
                                                    {release.outcome}
                                                    {release.receipt_reference
                                                        ? ` · ${release.receipt_reference}`
                                                        : ''}
                                                </div>
                                            ))}
                                            {can.execute &&
                                                batch.status ===
                                                    'in_progress' &&
                                                item.distributable &&
                                                item.releases.length === 0 && (
                                                    <ReleaseForm
                                                        item={item}
                                                        onSubmit={(
                                                            payload,
                                                            visit,
                                                        ) =>
                                                            post(
                                                                storeRelease.url(
                                                                    args,
                                                                ),
                                                                payload,
                                                                visit,
                                                            )
                                                        }
                                                    />
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <section className="grid gap-3 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Reconciliation</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="mt-2 text-sm">
                                <li>
                                    Approved{' '}
                                    {
                                        batch.reconciliation
                                            .approved_beneficiaries
                                    }
                                </li>
                                <li>
                                    Served{' '}
                                    {batch.reconciliation.served_beneficiaries}
                                </li>
                                <li>
                                    Partial{' '}
                                    {batch.reconciliation.partial_beneficiaries}
                                </li>
                                <li>
                                    Absent{' '}
                                    {batch.reconciliation.absent_beneficiaries}
                                </li>
                                <li>
                                    Excluded{' '}
                                    {
                                        batch.reconciliation
                                            .excluded_beneficiaries
                                    }
                                </li>
                                <li>
                                    Unserved{' '}
                                    {
                                        batch.reconciliation
                                            .unserved_beneficiaries
                                    }
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Approval history</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="mt-2 space-y-2">
                                {batch.decisions.map((decision) => (
                                    <li key={decision.id}>
                                        Rev {decision.revision}:{' '}
                                        {decision.decision} by {decision.actor}
                                        {decision.remarks
                                            ? ` — ${decision.remarks}`
                                            : ''}
                                    </li>
                                ))}
                                {batch.tasks
                                    .filter(
                                        (task) =>
                                            task.revision === batch.revision,
                                    )
                                    .map((task) => (
                                        <li key={task.id}>
                                            {task.stage}: {task.status}
                                        </li>
                                    ))}
                            </ul>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {[
                                    'roster',
                                    'acknowledgements',
                                    'history',
                                    'releases',
                                    'reconciliation',
                                ].map((report) => (
                                    <a
                                        key={report}
                                        className="underline"
                                        href={exportReport.url({
                                            ...args,
                                            report,
                                        })}
                                    >
                                        {report}
                                    </a>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </section>

                {can.update ? (
                    <form
                        className="flex flex-wrap items-end gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const data = new FormData(event.currentTarget);
                            router.post(storeDocument.url(args), data, {
                                forceFormData: true,
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="grid gap-2 text-sm">
                            <Label htmlFor="supporting-document">
                                Supporting document
                            </Label>
                            <Input
                                id="supporting-document"
                                type="file"
                                name="file"
                                required
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Attach
                        </Button>
                    </form>
                ) : null}
                {batch.documents.length > 0 ? (
                    <ul className="text-sm">
                        {batch.documents.map((document) => (
                            <li key={document.id}>
                                <a
                                    className="underline"
                                    href={documentShow.url({
                                        ...args,
                                        distributionDocument: document.id,
                                    })}
                                >
                                    {document.original_name}
                                </a>
                            </li>
                        ))}
                    </ul>
                ) : null}
            </div>
        </>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <Card size="sm">
            <CardHeader>
                <CardTitle className="text-muted-foreground">{label}</CardTitle>
            </CardHeader>
            <CardContent className="font-medium">{value}</CardContent>
        </Card>
    );
}

function ReleaseForm({
    item,
    onSubmit,
}: {
    item: Item;
    onSubmit: (payload: VisitData, visit: VisitCallbacks) => void;
}) {
    const remarksId = useId();
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);
    const [remarks, setRemarks] = useState('');

    return (
        <>
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => setOpen(true)}
            >
                Mark absent
            </Button>
            <StatusDialog
                open={open}
                onOpenChange={setOpen}
                title="Mark this beneficiary absent?"
                description="Served and partial releases are recorded at the venue desk."
                status="Absent"
                statusVariant="secondary"
                confirmLabel="Mark absent"
                pending={pending}
                onConfirm={() =>
                    onSubmit(
                        {
                            distribution_batch_item_id: item.id,
                            outcome: 'absent',
                            remarks,
                        },
                        {
                            onStart: () => setPending(true),
                            onSuccess: () => {
                                setOpen(false);
                                setRemarks('');
                            },
                            onFinish: () => setPending(false),
                        },
                    )
                }
            >
                <FieldGroup className="gap-4">
                    <Field>
                        <FieldLabel htmlFor={remarksId}>
                            Absence reason
                        </FieldLabel>
                        <Textarea
                            id={remarksId}
                            value={remarks}
                            onChange={(event) => setRemarks(event.target.value)}
                        />
                        <FieldDescription>
                            Optional note for the release record.
                        </FieldDescription>
                    </Field>
                </FieldGroup>
            </StatusDialog>
        </>
    );
}

function ScheduleForm({
    onSubmit,
}: {
    onSubmit: (
        scheduledAt: string,
        venue: string,
        details: string,
        visit: VisitCallbacks,
    ) => void;
}) {
    const scheduleId = useId();
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);
    const [scheduledAt, setScheduledAt] = useState('');
    const [venueName, setVenueName] = useState('');
    const [details, setDetails] = useState('');
    const [notice, setNotice] = useState<string | null>(null);

    return (
        <>
            <Button type="button" onClick={() => setOpen(true)}>
                Schedule
            </Button>
            <StatusDialog
                open={open}
                onOpenChange={setOpen}
                title="Schedule this distribution?"
                description="Set when and where the batch will be released."
                status="Scheduled"
                statusVariant="default"
                confirmLabel="Schedule"
                pending={pending}
                onConfirm={() => {
                    if (scheduledAt.trim() === '') {
                        setNotice('A schedule time is required.');

                        return;
                    }

                    onSubmit(scheduledAt, venueName, details, {
                        onStart: () => setPending(true),
                        onSuccess: () => setOpen(false),
                        onFinish: () => setPending(false),
                    });
                }}
            >
                <FieldGroup className="gap-4">
                    <Field data-invalid={notice ? true : undefined}>
                        <FieldLabel htmlFor={scheduleId}>
                            Scheduled time
                        </FieldLabel>
                        <Input
                            id={scheduleId}
                            type="datetime-local"
                            value={scheduledAt}
                            aria-invalid={notice ? true : undefined}
                            onChange={(event) => {
                                setScheduledAt(event.target.value);
                                setNotice(null);
                            }}
                        />
                        {notice ? <FieldError>{notice}</FieldError> : null}
                    </Field>
                    <Field>
                        <FieldLabel htmlFor={`${scheduleId}-venue`}>
                            Venue
                        </FieldLabel>
                        <Input
                            id={`${scheduleId}-venue`}
                            value={venueName}
                            onChange={(event) =>
                                setVenueName(event.target.value)
                            }
                        />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor={`${scheduleId}-details`}>
                            Venue details
                        </FieldLabel>
                        <Textarea
                            id={`${scheduleId}-details`}
                            value={details}
                            onChange={(event) => setDetails(event.target.value)}
                        />
                    </Field>
                </FieldGroup>
            </StatusDialog>
        </>
    );
}

function CancelForm({
    onSubmit,
}: {
    onSubmit: (reason: string, visit: VisitCallbacks) => void;
}) {
    const reasonId = useId();
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);
    const [reason, setReason] = useState('');
    const [notice, setNotice] = useState<string | null>(null);

    return (
        <>
            <Button
                type="button"
                variant="destructive"
                onClick={() => {
                    setNotice(null);
                    setOpen(true);
                }}
            >
                Cancel batch
            </Button>
            <StatusDialog
                open={open}
                onOpenChange={setOpen}
                title="Cancel this batch?"
                description="The batch stops here. Give the reason that should stay on the record."
                status="Cancelled"
                statusVariant="destructive"
                confirmLabel="Cancel batch"
                confirmVariant="destructive"
                pending={pending}
                onConfirm={() => {
                    if (reason.trim() === '') {
                        setNotice('A cancellation reason is required.');

                        return;
                    }

                    onSubmit(reason, {
                        onStart: () => setPending(true),
                        onSuccess: () => setOpen(false),
                        onFinish: () => setPending(false),
                    });
                }}
            >
                <FieldGroup className="gap-4">
                    <Field data-invalid={notice ? true : undefined}>
                        <FieldLabel htmlFor={reasonId}>
                            Cancellation reason
                        </FieldLabel>
                        <Textarea
                            id={reasonId}
                            value={reason}
                            aria-invalid={notice ? true : undefined}
                            onChange={(event) => {
                                setReason(event.target.value);
                                setNotice(null);
                            }}
                        />
                        {notice ? <FieldError>{notice}</FieldError> : null}
                    </Field>
                </FieldGroup>
            </StatusDialog>
        </>
    );
}
