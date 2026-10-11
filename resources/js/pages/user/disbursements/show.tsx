import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    cancel,
    complete,
    decide,
    edit,
    report as exportReport,
    finalize,
    index as disbursementsIndex,
    reopen,
    schedule,
    show as disbursementShow,
    venue,
    start,
    submit,
    validate,
} from '@/routes/user/disbursements';
import {
    show as documentShow,
    store as storeDocument,
} from '@/routes/user/disbursements/documents';
import {
    bulkDecide,
    decide as decideItem,
} from '@/routes/user/disbursements/items';
import { store as storeRelease } from '@/routes/user/disbursements/releases';

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

type Line = {
    id: number;
    item_name: string | null;
    kind: string | null;
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
    title: string;
    description: string | null;
    venue: string | null;
    scheduled_at: string | null;
    approval_mode: string;
    approval_mode_label: string;
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
    };
};

function routeArgs(department: string, batchId: number) {
    return { department, disbursementBatch: batchId };
}

export default function DisbursementShow({
    department,
    batch,
}: {
    department: DepartmentSummary;
    batch: Batch;
}) {
    const [selected, setSelected] = useState<number[]>([]);
    const args = routeArgs(department.slug, batch.id);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Disbursements',
                    href: disbursementsIndex.url(department.slug),
                },
                {
                    title: batch.batch_code,
                    href: disbursementShow.url({
                        department: department.slug,
                        disbursementBatch: batch.id,
                    }),
                },
            ],
        });
    }, [batch.batch_code, batch.id, department.slug]);

    function post(url: string, data: VisitData = {}): void {
        router.post(url, data, { preserveScroll: true });
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
                    </div>
                    <Badge variant="outline">{batch.status_label}</Badge>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Summary label="Mode" value={batch.approval_mode_label} />
                    <Summary
                        label="Beneficiaries"
                        value={`${batch.approved_beneficiary_count} approved / ${batch.beneficiary_count}`}
                    />
                    <Summary
                        label="Proposed cash"
                        value={batch.proposed_cash_total}
                    />
                    <Summary
                        label="Approved cash"
                        value={batch.approved_cash_total}
                    />
                    <Summary
                        label="Released cash"
                        value={batch.reconciliation.actual_cash}
                    />
                    <Summary
                        label="Remaining cash"
                        value={batch.reconciliation.remaining_cash}
                    />
                </div>

                {batch.finalization_label && (
                    <p className="text-sm">{batch.finalization_label}</p>
                )}

                {(batch.validation_summary?.warnings ?? []).length > 0 && (
                    <ul className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        {batch.validation_summary?.warnings?.map((warning) => (
                            <li key={warning}>{warning}</li>
                        ))}
                    </ul>
                )}

                <div className="flex flex-wrap gap-2">
                    {['draft', 'returned'].includes(batch.status) && (
                        <Button variant="outline" asChild>
                            <Link href={edit.url(args)}>Edit roster</Link>
                        </Button>
                    )}
                    {['draft', 'returned'].includes(batch.status) && (
                        <Button onClick={() => post(validate.url(args))}>
                            Validate
                        </Button>
                    )}
                    {batch.status === 'for_validation' && (
                        <Button onClick={() => post(submit.url(args))}>
                            Submit for approval
                        </Button>
                    )}
                    {batch.status === 'approved' && (
                        <ScheduleForm
                            onSubmit={(scheduledAt, venueName, details) =>
                                post(schedule.url(args), {
                                    scheduled_at: scheduledAt,
                                    venue: venueName,
                                    venue_details: details,
                                    lock_version: batch.lock_version,
                                })
                            }
                        />
                    )}
                    {batch.status === 'rejected' &&
                        batch.policy?.allow_resubmit_after_reject && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (
                                        !window.confirm(
                                            'Reopen this rejected batch as a new draft?',
                                        )
                                    ) {
                                        return;
                                    }

                                    post(reopen.url(args));
                                }}
                            >
                                Reopen as draft
                            </Button>
                        )}
                    {batch.status === 'scheduled' && (
                        <Button onClick={() => post(start.url(args))}>
                            Start disbursement
                        </Button>
                    )}
                    {(batch.status === 'scheduled' ||
                        batch.status === 'in_progress') && (
                        <Button variant="outline" asChild>
                            <Link href={venue.url(args)}>Venue desk</Link>
                        </Button>
                    )}
                    {batch.status === 'in_progress' && (
                        <Button
                            disabled={!batch.reconciliation.ready_to_complete}
                            onClick={() => post(complete.url(args))}
                        >
                            Complete
                        </Button>
                    )}
                    {![
                        'completed',
                        'cancelled',
                        'rejected',
                        'in_progress',
                    ].includes(batch.status) && (
                        <CancelForm
                            onSubmit={(reason) =>
                                post(cancel.url(args), {
                                    reason,
                                    lock_version: batch.lock_version,
                                })
                            }
                        />
                    )}
                </div>

                {batch.status === 'for_approval' &&
                    batch.approval_mode === 'batch' && (
                        <DecisionForm
                            onSubmit={(decision, remarks) =>
                                post(decide.url(args), {
                                    decision,
                                    remarks,
                                    lock_version: batch.lock_version,
                                })
                            }
                        />
                    )}

                {batch.status === 'for_approval' &&
                    batch.approval_mode === 'beneficiary' && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                disabled={selected.length === 0}
                                onClick={() => {
                                    if (
                                        !window.confirm(
                                            `Approve ${selected.length} selected beneficiaries?`,
                                        )
                                    ) {
                                        return;
                                    }

                                    post(bulkDecide.url(args), {
                                        item_ids: selected,
                                        decision: 'approved',
                                    });
                                }}
                            >
                                Approve selected
                            </Button>
                            <Button onClick={() => post(finalize.url(args))}>
                                Finalize review
                            </Button>
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
                                <TableRow key={item.id} className="align-top">
                                    <TableCell className="whitespace-normal">
                                        {batch.approval_mode ===
                                            'beneficiary' &&
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
                                        {item.lines.map((line) => (
                                            <div key={line.id}>
                                                {line.item_name}: proposed{' '}
                                                {line.proposed_amount ??
                                                    line.proposed_quantity}
                                                {line.approved_amount ||
                                                line.approved_quantity
                                                    ? ` / approved ${line.approved_amount ?? line.approved_quantity}`
                                                    : ''}
                                            </div>
                                        ))}
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
                                            <div>{item.exclusion_reason}</div>
                                        )}
                                        {batch.status === 'for_approval' &&
                                            batch.approval_mode ===
                                                'beneficiary' &&
                                            item.approval_status ===
                                                'pending' && (
                                                <ItemActions
                                                    item={item}
                                                    allowAmount={
                                                        batch.policy
                                                            ?.allow_amount_modification ??
                                                        false
                                                    }
                                                    onDecide={(payload) =>
                                                        post(
                                                            decideItem.url({
                                                                ...args,
                                                                item: item.id,
                                                            }),
                                                            payload,
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
                                        {batch.status === 'in_progress' &&
                                            item.distributable &&
                                            item.releases.length === 0 && (
                                                <ReleaseForm
                                                    item={item}
                                                    onSubmit={(payload) =>
                                                        post(
                                                            storeRelease.url(
                                                                args,
                                                            ),
                                                            payload,
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
                                {batch.reconciliation.approved_beneficiaries}
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
                                {batch.reconciliation.excluded_beneficiaries}
                            </li>
                            <li>
                                Unserved{' '}
                                {batch.reconciliation.unserved_beneficiaries}
                            </li>
                            <li>
                                Approved cash{' '}
                                {batch.reconciliation.approved_cash} / actual{' '}
                                {batch.reconciliation.actual_cash}
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
                                    Rev {decision.revision}: {decision.decision}{' '}
                                    by {decision.actor}
                                    {decision.remarks
                                        ? ` — ${decision.remarks}`
                                        : ''}
                                </li>
                            ))}
                            {batch.tasks
                                .filter(
                                    (task) => task.revision === batch.revision,
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
                    <ul className="text-sm">
                        {batch.documents.map((document) => (
                            <li key={document.id}>
                                <a
                                    className="underline"
                                    href={documentShow.url({
                                        ...args,
                                        disbursementDocument: document.id,
                                    })}
                                >
                                    {document.original_name}
                                </a>
                            </li>
                        ))}
                    </ul>
                </form>
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

function DecisionForm({
    onSubmit,
}: {
    onSubmit: (decision: string, remarks: string) => void;
}) {
    const [decision, setDecision] = useState('approved');
    const [remarks, setRemarks] = useState('');

    return (
        <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(event) => {
                event.preventDefault();

                if (
                    (decision === 'returned' || decision === 'rejected') &&
                    remarks.trim() === ''
                ) {
                    window.alert('A reason is required for this decision.');

                    return;
                }

                if (
                    !window.confirm(
                        `Record a ${decision} decision for the whole batch?`,
                    )
                ) {
                    return;
                }

                onSubmit(decision, remarks);
            }}
        >
            <div className="grid gap-2">
                <Label>Whole-batch decision</Label>
                <Select value={decision} onValueChange={setDecision}>
                    <SelectTrigger className="w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="approved">Approve</SelectItem>
                        <SelectItem value="returned">Return</SelectItem>
                        <SelectItem value="rejected">Reject</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="batch-remarks">Reason</Label>
                <Input
                    id="batch-remarks"
                    value={remarks}
                    onChange={(event) => setRemarks(event.target.value)}
                />
            </div>
            <Button type="submit">Record decision</Button>
        </form>
    );
}

function ItemActions({
    item,
    allowAmount,
    onDecide,
}: {
    item: Item;
    allowAmount: boolean;
    onDecide: (payload: VisitData) => void;
}) {
    const [decision, setDecision] = useState('approved');

    return (
        <form
            className="mt-2 grid gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                const form = new FormData(event.currentTarget);
                const remarks = String(form.get('remarks') ?? '');

                if (
                    (decision === 'returned' || decision === 'excluded') &&
                    remarks.trim() === ''
                ) {
                    window.alert('A reason is required for this decision.');

                    return;
                }

                if (
                    !window.confirm(`Record this beneficiary as ${decision}?`)
                ) {
                    return;
                }

                onDecide({
                    decision,
                    remarks,
                    exclusion_reason: remarks,
                    lines: allowAmount
                        ? item.lines.map((line) => ({
                              id: line.id,
                              approved_amount:
                                  String(form.get(`amount-${line.id}`) ?? '') ||
                                  null,
                              approved_quantity:
                                  String(
                                      form.get(`quantity-${line.id}`) ?? '',
                                  ) || null,
                          }))
                        : [],
                });
            }}
        >
            <Select value={decision} onValueChange={setDecision}>
                <SelectTrigger className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="approved">Approve</SelectItem>
                    <SelectItem value="returned">Return</SelectItem>
                    <SelectItem value="excluded">Exclude</SelectItem>
                </SelectContent>
            </Select>
            {allowAmount &&
                item.lines.map((line) => (
                    <Input
                        key={line.id}
                        name={
                            line.proposed_amount !== null
                                ? `amount-${line.id}`
                                : `quantity-${line.id}`
                        }
                        placeholder="Approved value"
                    />
                ))}
            <Input name="remarks" placeholder="Reason, if required" />
            <Button type="submit" size="sm" variant="outline">
                Save
            </Button>
        </form>
    );
}

function ReleaseForm({
    item,
    onSubmit,
}: {
    item: Item;
    onSubmit: (payload: VisitData) => void;
}) {
    return (
        <form
            className="mt-2 grid gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                const form = new FormData(event.currentTarget);
                onSubmit({
                    disbursement_batch_item_id: item.id,
                    outcome: 'absent',
                    remarks: String(form.get('remarks') ?? ''),
                });
            }}
        >
            <p className="text-xs text-muted-foreground">
                Served and partial releases are recorded at the venue desk.
            </p>
            <Input name="remarks" placeholder="Absence reason" />
            <Button type="submit" size="sm" variant="outline">
                Mark absent
            </Button>
        </form>
    );
}

function ScheduleForm({
    onSubmit,
}: {
    onSubmit: (scheduledAt: string, venue: string, details: string) => void;
}) {
    return (
        <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                const form = new FormData(event.currentTarget);
                onSubmit(
                    String(form.get('scheduled_at')),
                    String(form.get('venue') ?? ''),
                    String(form.get('venue_details') ?? ''),
                );
            }}
        >
            <Input type="datetime-local" name="scheduled_at" required />
            <Input name="venue" placeholder="Venue" />
            <Input name="venue_details" placeholder="Venue details" />
            <Button type="submit">Schedule</Button>
        </form>
    );
}

function CancelForm({ onSubmit }: { onSubmit: (reason: string) => void }) {
    return (
        <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                const form = new FormData(event.currentTarget);
                onSubmit(String(form.get('reason') ?? ''));
            }}
        >
            <Input name="reason" required placeholder="Cancellation reason" />
            <Button type="submit" variant="destructive">
                Cancel batch
            </Button>
        </form>
    );
}
