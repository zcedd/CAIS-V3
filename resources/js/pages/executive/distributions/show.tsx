import { Head, router, setLayoutProps } from '@inertiajs/react';
import { AlertTriangle, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AssistanceLines } from '@/components/distribution/assistance-lines';
import { DistributionComposition } from '@/components/distribution/composition';
import type { DistributionCompositionData } from '@/components/distribution/composition';
import { DistributionDecisionDialog } from '@/components/distribution/decision-dialog';
import { ConfirmActionButton } from '@/components/distribution/status-dialog';
import type { VisitCallbacks } from '@/components/distribution/status-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { CodeChip } from '@/components/user/code-chip';
import { StatCard } from '@/components/user/stat-card';
import { useCan } from '@/hooks/use-can';
import { Permission } from '@/lib/permissions';
import {
    decide,
    finalize,
    index,
    show,
} from '@/routes/executive/distributions';
import {
    bulkDecide,
    decide as decideItem,
} from '@/routes/executive/distributions/items';

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
    kind: string | null;
    unit?: string | null;
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
    reconciliation: {
        composition: DistributionCompositionData;
    };
};

export default function ExecutiveDistributionShow({ batch }: { batch: Batch }) {
    const [selected, setSelected] = useState<number[]>([]);
    const { can } = useCan();
    const canApprove = can(Permission.DistributionApprove);
    const canFinalize = can(Permission.DistributionFinalize);
    const canModify = can(Permission.DistributionModifyBeneficiary);
    const batchDecisions = [
        canApprove ? { value: 'approved', label: 'Approve' } : null,
        can(Permission.DistributionReturn)
            ? { value: 'returned', label: 'Return' }
            : null,
        can(Permission.DistributionReject)
            ? { value: 'rejected', label: 'Reject' }
            : null,
    ].filter(
        (choice): choice is { value: string; label: string } => choice !== null,
    );

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Distributions', href: index.url() },
                { title: batch.batch_code, href: show.url(batch.id) },
            ],
        });
    }, [batch.batch_code, batch.id]);

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
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {batch.title}
                    </h1>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <CodeChip>{batch.batch_code}</CodeChip>
                        <Badge variant="outline">{batch.status_label}</Badge>
                        <Badge variant="outline">
                            Revision {batch.revision}
                        </Badge>
                        {batch.program.name ? (
                            <Badge variant="outline">{batch.program.name}</Badge>
                        ) : null}
                        {batch.department.name ? (
                            <Badge variant="outline">
                                {batch.department.name}
                            </Badge>
                        ) : null}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {batch.venue ?? 'Venue not set'}
                        {batch.scheduled_at
                            ? ` · ${new Intl.DateTimeFormat('en-PH', {
                                  dateStyle: 'medium',
                                  timeStyle: 'short',
                              }).format(new Date(batch.scheduled_at))}`
                            : ''}
                    </p>
                </div>

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Approved beneficiaries"
                        value={`${batch.approved_beneficiary_count}/${batch.beneficiary_count}`}
                        description="Approved of the roster"
                        icon={Users}
                    />
                </div>
                <DistributionComposition
                    composition={batch.reconciliation.composition}
                />

                {batch.policy ? (
                    <p className="text-sm text-muted-foreground">
                        {batch.policy.name} · {batch.policy.strategy}
                        {batch.policy.allow_partial_finalization
                            ? ' · partial finalization allowed'
                            : ' · every beneficiary must be decided'}
                        {batch.finalization_label
                            ? ` · ${batch.finalization_label}`
                            : ''}
                    </p>
                ) : null}

                {(batch.validation_summary?.warnings ?? []).length > 0 ? (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>Review warnings</AlertTitle>
                        <AlertDescription>
                            <ul className="flex flex-col gap-1">
                                {(batch.validation_summary?.warnings ?? []).map(
                                    (warning) => (
                                        <li key={warning}>{warning}</li>
                                    ),
                                )}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : null}

                {batch.status === 'for_approval' &&
                    (batchDecisions.length > 0 ||
                        canApprove ||
                        canFinalize) && (
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
                                            decide.url(batch.id),
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
                            {canApprove ? (
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
                                            bulkDecide.url(batch.id),
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
                            {canFinalize ? (
                                <ConfirmActionButton
                                    label="Finalize review"
                                    title="Finalize this review?"
                                    description="Lock in the individual beneficiary decisions."
                                    status="Finalized"
                                    statusVariant="default"
                                    onConfirm={(visit) =>
                                        post(finalize.url(batch.id), {}, visit)
                                    }
                                />
                            ) : null}
                        </div>
                    )}

                <section className="rounded-xl border border-border bg-card">
                    <div className="flex flex-col gap-4 p-4">
                        <div className="space-y-1">
                            <h2 className="text-[15px] font-semibold tracking-tight">
                                Roster
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Beneficiaries included in this distribution.
                            </p>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Beneficiary</TableHead>
                                    <TableHead>Assistance</TableHead>
                                    <TableHead>Decision</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {batch.items.map((item) => (
                                    <TableRow
                                        key={item.id}
                                        className="align-top"
                                    >
                                        <TableCell className="whitespace-normal">
                                            {canApprove &&
                                                item.approval_status ===
                                                    'pending' &&
                                                batch.status ===
                                                    'for_approval' && (
                                                    <Checkbox
                                                        className="mr-2"
                                                        checked={selected.includes(
                                                            item.id,
                                                        )}
                                                        aria-label={`Select ${item.beneficiary.name}`}
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
                                                    />
                                                )}
                                            <div className="font-medium">
                                                {item.beneficiary.name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {item.beneficiary.cais_number} ·{' '}
                                                {item.request_status}
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            <AssistanceLines
                                                lines={item.lines}
                                            />
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
                                            {canApprove &&
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
                                                            canModify &&
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
                                                                    distributionBatch:
                                                                        batch.id,
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
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <section className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4">
                    <h2 className="text-[15px] font-semibold tracking-tight">
                        Approval history
                    </h2>
                    <ul className="flex flex-col gap-2 text-sm">
                            {batch.tasks
                                .filter(
                                    (task) => task.revision === batch.revision,
                                )
                                .map((task) => (
                                    <li key={task.id}>
                                        {task.stage}: {task.status}
                                    </li>
                                ))}
                            {batch.decisions.map((decision) => (
                                <li key={decision.id}>
                                    Rev {decision.revision}: {decision.decision}{' '}
                                    by {decision.actor}
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
