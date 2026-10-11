import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { decide, finalize, index, show } from '@/routes/executive/dispersals';
import {
    bulkDecide,
    decide as decideItem,
} from '@/routes/executive/dispersals/items';

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

type Line = {
    id: number;
    item_name: string | null;
    proposed_quantity: string | null;
    approved_quantity: string | null;
    proposed_amount: string | null;
    approved_amount: string | null;
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
};

type Batch = {
    id: number;
    batch_code: string;
    title: string;
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
    department: { name: string | null };
    validation_summary: { warnings?: string[] } | null;
    policy: {
        name: string;
        strategy: string;
        allow_partial_finalization: boolean;
        allow_amount_modification: boolean;
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
        revision: number;
    }[];
};

export default function ExecutiveDispersalShow({ batch }: { batch: Batch }) {
    const [selected, setSelected] = useState<number[]>([]);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Dispersals', href: index.url() },
                { title: batch.batch_code, href: show.url(batch.id) },
            ],
        });
    }, [batch.batch_code, batch.id]);

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
                            {batch.program.name} · {batch.department.name} ·{' '}
                            {batch.venue}
                        </p>
                    </div>
                    <Badge variant="outline">{batch.status_label}</Badge>
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                    <Summary label="Mode" value={batch.approval_mode_label} />
                    <Summary
                        label="Beneficiaries"
                        value={`${batch.approved_beneficiary_count} / ${batch.beneficiary_count}`}
                    />
                    <Summary
                        label="Proposed cash"
                        value={batch.proposed_cash_total}
                    />
                    <Summary
                        label="Approved cash"
                        value={batch.approved_cash_total}
                    />
                </div>

                {batch.policy && (
                    <p className="text-sm text-muted-foreground">
                        {batch.policy.name} · {batch.policy.strategy}
                        {batch.policy.allow_partial_finalization
                            ? ' · partial finalization allowed'
                            : ' · every beneficiary must be decided'}
                        {batch.finalization_label
                            ? ` · ${batch.finalization_label}`
                            : ''}
                    </p>
                )}

                {(batch.validation_summary?.warnings ?? []).map((warning) => (
                    <p key={warning} className="text-sm text-amber-700">
                        {warning}
                    </p>
                ))}

                {batch.status === 'for_approval' &&
                    batch.approval_mode === 'batch' && (
                        <form
                            className="flex flex-wrap items-end gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const form = new FormData(event.currentTarget);
                                const decision = String(form.get('decision'));
                                const remarks = String(
                                    form.get('remarks') ?? '',
                                );

                                if (
                                    (decision === 'returned' ||
                                        decision === 'rejected') &&
                                    remarks.trim() === ''
                                ) {
                                    window.alert(
                                        'A reason is required for this decision.',
                                    );

                                    return;
                                }

                                if (
                                    !window.confirm(
                                        `Record a ${decision} decision for the whole batch?`,
                                    )
                                ) {
                                    return;
                                }

                                post(decide.url(batch.id), {
                                    decision,
                                    remarks,
                                    lock_version: batch.lock_version,
                                });
                            }}
                        >
                            <select
                                name="decision"
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                aria-label="Decision"
                            >
                                <option value="approved">Approve</option>
                                <option value="returned">Return</option>
                                <option value="rejected">Reject</option>
                            </select>
                            <input
                                name="remarks"
                                placeholder="Reason, if required"
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                            />
                            <Button type="submit">Record decision</Button>
                        </form>
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

                                    post(bulkDecide.url(batch.id), {
                                        item_ids: selected,
                                        decision: 'approved',
                                    });
                                }}
                            >
                                Approve selected
                            </Button>
                            <Button
                                onClick={() => {
                                    if (
                                        !window.confirm(
                                            'Finalize this review with the current decisions?',
                                        )
                                    ) {
                                        return;
                                    }

                                    post(finalize.url(batch.id));
                                }}
                            >
                                Finalize review
                            </Button>
                        </div>
                    )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Beneficiary</th>
                                <th className="px-3 py-2">Assistance</th>
                                <th className="px-3 py-2">Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            {batch.items.map((item) => (
                                <tr
                                    key={item.id}
                                    className="border-t align-top"
                                >
                                    <td className="px-3 py-2">
                                        {item.approval_status === 'pending' &&
                                            batch.approval_mode ===
                                                'beneficiary' && (
                                                <input
                                                    type="checkbox"
                                                    className="mr-2"
                                                    checked={selected.includes(
                                                        item.id,
                                                    )}
                                                    aria-label={`Select ${item.beneficiary.name}`}
                                                    onChange={(event) =>
                                                        setSelected(
                                                            (current) =>
                                                                event.target
                                                                    .checked
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
                                                />
                                            )}
                                        <div className="font-medium">
                                            {item.beneficiary.name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {item.beneficiary.cais_number} ·{' '}
                                            {item.request_status}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {item.lines.map((line) => (
                                            <div key={line.id}>
                                                {line.item_name}: proposed{' '}
                                                {line.proposed_amount ??
                                                    line.proposed_quantity}
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
                                    </td>
                                    <td className="px-3 py-2">
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
                                                <form
                                                    className="mt-2 grid gap-2"
                                                    onSubmit={(event) => {
                                                        event.preventDefault();
                                                        const form =
                                                            new FormData(
                                                                event.currentTarget,
                                                            );
                                                        const decision = String(
                                                            form.get(
                                                                'decision',
                                                            ),
                                                        );
                                                        const remarks = String(
                                                            form.get(
                                                                'remarks',
                                                            ) ?? '',
                                                        );

                                                        if (
                                                            (decision ===
                                                                'returned' ||
                                                                decision ===
                                                                    'excluded') &&
                                                            remarks.trim() ===
                                                                ''
                                                        ) {
                                                            window.alert(
                                                                'A reason is required for this decision.',
                                                            );

                                                            return;
                                                        }

                                                        if (
                                                            !window.confirm(
                                                                `Record this beneficiary as ${decision}?`,
                                                            )
                                                        ) {
                                                            return;
                                                        }

                                                        post(
                                                            decideItem.url({
                                                                dispersalBatch:
                                                                    batch.id,
                                                                item: item.id,
                                                            }),
                                                            {
                                                                decision,
                                                                remarks,
                                                                exclusion_reason:
                                                                    remarks,
                                                                lines: batch
                                                                    .policy
                                                                    ?.allow_amount_modification
                                                                    ? item.lines.map(
                                                                          (
                                                                              line,
                                                                          ) => ({
                                                                              id: line.id,
                                                                              approved_amount:
                                                                                  String(
                                                                                      form.get(
                                                                                          `amount-${line.id}`,
                                                                                      ) ??
                                                                                          '',
                                                                                  ) ||
                                                                                  null,
                                                                              approved_quantity:
                                                                                  String(
                                                                                      form.get(
                                                                                          `quantity-${line.id}`,
                                                                                      ) ??
                                                                                          '',
                                                                                  ) ||
                                                                                  null,
                                                                          }),
                                                                      )
                                                                    : [],
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <select
                                                        name="decision"
                                                        className="rounded-md border px-2 py-1"
                                                    >
                                                        <option value="approved">
                                                            Approve
                                                        </option>
                                                        <option value="returned">
                                                            Return
                                                        </option>
                                                        <option value="excluded">
                                                            Exclude
                                                        </option>
                                                    </select>
                                                    {batch.policy
                                                        ?.allow_amount_modification &&
                                                        item.lines.map(
                                                            (line) => (
                                                                <input
                                                                    key={
                                                                        line.id
                                                                    }
                                                                    name={
                                                                        line.proposed_amount !==
                                                                        null
                                                                            ? `amount-${line.id}`
                                                                            : `quantity-${line.id}`
                                                                    }
                                                                    placeholder="Approved value"
                                                                    className="rounded-md border px-2 py-1"
                                                                />
                                                            ),
                                                        )}
                                                    <input
                                                        name="remarks"
                                                        placeholder="Reason, if required"
                                                        className="rounded-md border px-2 py-1"
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        Save
                                                    </Button>
                                                </form>
                                            )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <section className="rounded-lg border p-4 text-sm">
                    <h2 className="font-medium">History</h2>
                    <ul className="mt-2 space-y-1">
                        {batch.tasks
                            .filter((task) => task.revision === batch.revision)
                            .map((task) => (
                                <li key={task.id}>
                                    {task.stage}: {task.status}
                                </li>
                            ))}
                        {batch.decisions.map((decision) => (
                            <li key={decision.id}>
                                Rev {decision.revision}: {decision.decision} by{' '}
                                {decision.actor}
                                {decision.remarks
                                    ? ` — ${decision.remarks}`
                                    : ''}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-medium">{value}</div>
        </div>
    );
}
