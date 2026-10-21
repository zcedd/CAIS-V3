import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import {
    DistributionComposition,
    formatDistributionQuantity,
} from '@/components/distribution/composition';
import type { DistributionCompositionData } from '@/components/distribution/composition';
import { StatusDialog } from '@/components/distribution/status-dialog';
import type { VisitCallbacks } from '@/components/distribution/status-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { CodeChip } from '@/components/user/code-chip';
import { StatCard } from '@/components/user/stat-card';
import { formatPeso } from '@/lib/format-peso';
import { cn } from '@/lib/utils';
import {
    index as distributionsIndex,
    show as distributionShow,
    venue,
} from '@/routes/user/distributions';
import { store as assignPersonnel } from '@/routes/user/distributions/personnel';
import { store as storeRelease } from '@/routes/user/distributions/releases';
import {
    finalize,
    receipt as receiptRoute,
    store as startTransaction,
    voidMethod,
} from '@/routes/user/distributions/transactions';
import { store as storeEvidence } from '@/routes/user/distributions/transactions/evidence';

type DepartmentSummary = { slug: string; name: string };
type Officer = { id: number; name: string };
type BeneficiaryRow = {
    id: number;
    assistance_id: number;
    name: string | null;
    cais_number: string | null;
    approval_status: string;
    release_status: string;
};
type Line = {
    id: number;
    name: string | null;
    kind: string | null;
    unit: string | null;
    approved_quantity: string | null;
    approved_amount: string | null;
    remaining_quantity: string | null;
    remaining_amount: string | null;
};
type EvidenceRow = {
    id: number;
    type: string;
    sha256_hash: string;
    preview_url: string;
};
type Selected = {
    id: number;
    assistance_id: number;
    name: string | null;
    cais_number: string | null;
    approval_status: string;
    exclusion_reason: string | null;
    lines: Line[];
    transaction: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        evidence: EvidenceRow[];
        receipt: { id: number; number: string; status: string } | null;
    } | null;
};

type VenueProps = {
    department: DepartmentSummary;
    batch: {
        id: number;
        batch_code: string;
        title: string;
        status: string;
        status_label: string;
        program: string | null;
        venue: string | null;
        venue_details: string | null;
        scheduled_at: string | null;
        progress: number;
    };
    summary: {
        approved_beneficiaries: number;
        served_beneficiaries: number;
        partial_beneficiaries: number;
        unserved_beneficiaries: number;
        excluded_beneficiaries: number;
        absent_beneficiaries: number;
        voided_transactions: number;
        approved_cash: string;
        actual_cash: string;
        remaining_cash: string;
        composition: DistributionCompositionData;
    };
    filters: { q: string | null; item: number | null };
    beneficiaries: {
        data: BeneficiaryRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    selected: Selected | null;
    personnel: Officer[];
    officers: Officer[];
    can: {
        execute: boolean;
        assign: boolean;
        void: boolean;
        waive: boolean;
        evidence: boolean;
        reprint: boolean;
        audit: boolean;
    };
    audit: {
        id: number;
        event: string | null;
        actor: string | null;
        created_at: string | null;
    }[];
    privacy_notice: string;
    retention_notice: string;
    release_only?: boolean;
};

function routeArgs(department: string, batchId: number) {
    return { department, distributionBatch: batchId };
}

function formatWhen(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export default function DistributionVenue({
    department,
    batch,
    summary,
    filters,
    beneficiaries,
    selected,
    personnel,
    officers,
    can,
    audit,
    privacy_notice,
    retention_notice,
    release_only = false,
}: VenueProps) {
    const args = routeArgs(department.slug, batch.id);

    useEffect(() => {
        const breadcrumbArgs = routeArgs(department.slug, batch.id);

        setLayoutProps({
            breadcrumbs: release_only
                ? [
                      {
                          title: 'Distributions',
                          href: distributionsIndex.url(department.slug),
                      },
                      { title: 'Release', href: venue.url(breadcrumbArgs) },
                  ]
                : [
                      {
                          title: 'Distributions',
                          href: distributionsIndex.url(department.slug),
                      },
                      {
                          title: batch.batch_code,
                          href: distributionShow.url(breadcrumbArgs),
                      },
                      {
                          title: 'Venue desk',
                          href: venue.url(breadcrumbArgs),
                      },
                  ],
        });
    }, [batch.batch_code, batch.id, department.slug, release_only]);

    if (release_only) {
        return (
            <>
                <Head title="Release" />
                <div className="mx-auto flex h-full min-w-0 w-full max-w-3xl flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Release
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {batch.program} · {department.name}
                            {batch.venue ? ` · ${batch.venue}` : ''}
                            {batch.scheduled_at
                                ? ` · ${formatWhen(batch.scheduled_at)}`
                                : ''}
                        </p>
                    </div>
                    {selected ? (
                        <ReleasePanel
                            department={department.slug}
                            batchId={batch.id}
                            batchStatus={batch.status}
                            selected={selected}
                            officers={officers}
                            can={can}
                            privacyNotice={privacy_notice}
                            retentionNotice={retention_notice}
                        />
                    ) : (
                        <Card>
                            <CardContent className="py-8 text-sm text-muted-foreground">
                                This release has no beneficiary to record.
                            </CardContent>
                        </Card>
                    )}
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`${batch.batch_code} venue`} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {batch.title}
                    </h1>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <CodeChip>{batch.batch_code}</CodeChip>
                        <Badge variant="outline">{batch.status_label}</Badge>
                        {batch.program ? (
                            <Badge variant="outline">{batch.program}</Badge>
                        ) : null}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {department.name}
                        {batch.venue ? ` · ${batch.venue}` : ' · Venue not set'}
                    </p>
                    {batch.venue_details ? (
                        <p className="text-sm text-muted-foreground">
                            {batch.venue_details}
                        </p>
                    ) : null}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Eligible"
                        value={summary.approved_beneficiaries.toLocaleString()}
                    />
                    <StatCard
                        label="Completed"
                        value={summary.served_beneficiaries.toLocaleString()}
                    />
                    <StatCard
                        label="Partial"
                        value={summary.partial_beneficiaries.toLocaleString()}
                    />
                    <StatCard
                        label="Pending"
                        value={summary.unserved_beneficiaries.toLocaleString()}
                    />
                    <StatCard
                        label="Excluded"
                        value={summary.excluded_beneficiaries.toLocaleString()}
                    />
                    <StatCard
                        label="Absent"
                        value={summary.absent_beneficiaries.toLocaleString()}
                    />
                    <StatCard
                        label="Voided releases"
                        value={summary.voided_transactions.toLocaleString()}
                    />
                </div>

                <section className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4">
                    <h2 className="text-[15px] font-semibold tracking-tight">
                        Release progress
                    </h2>
                    <Progress
                        value={batch.progress}
                        aria-label="Completed releases"
                    />
                    <p className="text-sm text-muted-foreground">
                        Excluded beneficiaries are not counted as released.
                    </p>
                    <DistributionComposition composition={summary.composition} />
                </section>

                {can.assign && (
                    <PersonnelForm
                        officers={officers}
                        selected={personnel.map((person) => person.id)}
                        onSubmit={(userIds) =>
                            router.post(
                                assignPersonnel.url(args),
                                { user_ids: userIds },
                                { preserveScroll: true },
                            )
                        }
                    />
                )}

                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            venue.url(args),
                            { q: String(form.get('q') ?? '') },
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        name="q"
                        defaultValue={filters.q ?? ''}
                        placeholder="Name, control number, or request ID"
                        aria-label="Find a beneficiary"
                        className="h-9 max-w-md"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <div className="grid gap-4 lg:grid-cols-[minmax(16rem,22rem)_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Beneficiaries</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {beneficiaries.data.map((row) => (
                                <Link
                                    key={row.id}
                                    href={venue.url(args, {
                                        query: {
                                            q: filters.q ?? undefined,
                                            item: row.id,
                                        },
                                    })}
                                    className={cn(
                                        'rounded-lg border border-border px-3 py-2 text-sm hover:bg-muted',
                                        selected?.id === row.id && 'bg-muted',
                                    )}
                                >
                                    <span className="block font-medium">
                                        {row.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {row.cais_number} · Request{' '}
                                        {row.assistance_id} ·{' '}
                                        {row.release_status}
                                    </span>
                                </Link>
                            ))}
                            <div className="flex gap-2">
                                {beneficiaries.prev_page_url ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                    >
                                        <Link href={beneficiaries.prev_page_url}>
                                            Previous
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Previous
                                    </Button>
                                )}
                                {beneficiaries.next_page_url ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                    >
                                        <Link href={beneficiaries.next_page_url}>
                                            Next
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Next
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {selected ? (
                        <ReleasePanel
                            department={department.slug}
                            batchId={batch.id}
                            batchStatus={batch.status}
                            selected={selected}
                            officers={officers}
                            can={can}
                            privacyNotice={privacy_notice}
                            retentionNotice={retention_notice}
                        />
                    ) : (
                        <Card>
                            <CardContent className="py-8 text-sm text-muted-foreground">
                                Select a beneficiary to verify the release.
                            </CardContent>
                        </Card>
                    )}
                </div>

                {can.audit && audit.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent audit</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1 text-sm">
                            {audit.map((entry) => (
                                <p key={entry.id}>
                                    {entry.event} · {entry.actor ?? 'System'} ·{' '}
                                    {formatWhen(entry.created_at)}
                                </p>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function PersonnelForm({
    officers,
    selected,
    onSubmit,
}: {
    officers: Officer[];
    selected: number[];
    onSubmit: (userIds: number[]) => void;
}) {
    const [ids, setIds] = useState<number[]>(selected);

    return (
        <form
            className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit(ids);
            }}
        >
            <p className="font-medium">Assigned personnel</p>
            <div className="grid gap-2 sm:grid-cols-2">
                {officers.map((officer) => (
                    <label
                        key={officer.id}
                        className="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            checked={ids.includes(officer.id)}
                            onCheckedChange={(checked) =>
                                setIds((current) =>
                                    checked
                                        ? [...current, officer.id]
                                        : current.filter(
                                              (id) => id !== officer.id,
                                          ),
                                )
                            }
                        />
                        {officer.name}
                    </label>
                ))}
            </div>
            <Button type="submit" variant="outline" className="w-fit">
                Save assignment
            </Button>
        </form>
    );
}

function ReleasePanel({
    department,
    batchId,
    batchStatus,
    selected,
    officers,
    can,
    privacyNotice,
    retentionNotice,
}: {
    department: string;
    batchId: number;
    batchStatus: string;
    selected: Selected;
    officers: Officer[];
    can: VenueProps['can'];
    privacyNotice: string;
    retentionNotice: string;
}) {
    const args = { department, distributionBatch: batchId, item: selected.id };
    const transaction = selected.transaction;
    const draft = transaction?.status === 'draft' ? transaction : null;
    const blocked =
        batchStatus !== 'in_progress' ||
        selected.approval_status !== 'approved' ||
        !can.execute;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{selected.name}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <p className="text-sm text-muted-foreground">
                    Control number {selected.cais_number} · Request{' '}
                    {selected.assistance_id} · {selected.approval_status}
                </p>
                {selected.exclusion_reason && (
                    <p className="text-sm">
                        Excluded: {selected.exclusion_reason}
                    </p>
                )}
                <BeneficiaryLines lines={selected.lines} />
                {blocked && (
                    <p className="text-sm text-muted-foreground">
                        Releases are available only for an approved beneficiary
                        while this batch is in progress, and only for an officer
                        allowed to record them.
                    </p>
                )}
                {can.execute &&
                    batchStatus === 'in_progress' &&
                    selected.approval_status === 'approved' &&
                    !draft &&
                    transaction?.status !== 'finalized' && (
                        <Button
                            onClick={() =>
                                router.post(
                                    startTransaction.url(args),
                                    {
                                        idempotency_key: sessionKey(
                                            selected.id,
                                        ),
                                    },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Begin release
                        </Button>
                    )}
                {draft && can.execute && (
                    <ReleaseForm
                        department={department}
                        batchId={batchId}
                        selected={selected}
                        transactionId={draft.id}
                        officers={officers}
                        canWaive={can.waive}
                        canViewEvidence={can.evidence}
                        privacyNotice={privacyNotice}
                        retentionNotice={retentionNotice}
                    />
                )}
                {transaction?.status === 'finalized' && transaction.receipt && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={receiptRoute.url({
                                    department,
                                    distributionBatch: batchId,
                                    transaction: transaction.id,
                                })}
                            >
                                Receipt {transaction.receipt.number}
                            </Link>
                        </Button>
                        {can.void && (
                            <VoidForm
                                onSubmit={(reason, visit) =>
                                    router.post(
                                        voidMethod.url({
                                            department,
                                            distributionBatch: batchId,
                                            transaction: transaction.id,
                                        }),
                                        { reason },
                                        { preserveScroll: true, ...visit },
                                    )
                                }
                            />
                        )}
                    </div>
                )}
                {can.execute &&
                    batchStatus === 'in_progress' &&
                    selected.approval_status === 'approved' && (
                        <AbsentForm
                            onSubmit={(remarks, visit) =>
                                router.post(
                                    storeRelease.url({
                                        department,
                                        distributionBatch: batchId,
                                    }),
                                    {
                                        distribution_batch_item_id: selected.id,
                                        outcome: 'absent',
                                        remarks,
                                    },
                                    { preserveScroll: true, ...visit },
                                )
                            }
                        />
                    )}
            </CardContent>
        </Card>
    );
}

function BeneficiaryLines({ lines }: { lines: Line[] }) {
    const groups = (
        [
            { kind: 'goods', label: 'Goods' },
            { kind: 'service', label: 'Services' },
            { kind: 'cash', label: 'Cash' },
        ] as const
    )
        .map((group) => ({
            ...group,
            lines: lines.filter(
                (line) => (line.kind ?? 'goods') === group.kind,
            ),
        }))
        .filter((group) => group.lines.length > 0);

    if (groups.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No assistance lines</p>
        );
    }

    return (
        <div className="flex flex-col gap-3 text-sm">
            {groups.map((group) => (
                <div key={group.kind} className="flex flex-col gap-1">
                    <div className="text-xs font-medium text-muted-foreground">
                        {group.label}
                    </div>
                    {group.lines.map((line) => (
                        <div key={line.id}>
                            {line.name}: approved{' '}
                            {group.kind === 'cash'
                                ? formatPeso(line.approved_amount)
                                : formatDistributionQuantity(
                                      line.approved_quantity,
                                      line.unit,
                                  )}
                            {' · remaining '}
                            {group.kind === 'cash'
                                ? formatPeso(line.remaining_amount)
                                : formatDistributionQuantity(
                                      line.remaining_quantity,
                                      line.unit,
                                  )}
                        </div>
                    ))}
                </div>
            ))}
        </div>
    );
}

function sessionKey(itemId: number): string {
    const storageKey = `cais-distribution-item-${itemId}`;
    const existing = window.sessionStorage.getItem(storageKey);

    if (existing) {
        return existing;
    }

    const next = globalThis.crypto.randomUUID();
    window.sessionStorage.setItem(storageKey, next);

    return next;
}

function ReleaseForm({
    department,
    batchId,
    selected,
    transactionId,
    officers,
    canWaive,
    canViewEvidence,
    privacyNotice,
    retentionNotice,
}: {
    department: string;
    batchId: number;
    selected: Selected;
    transactionId: number;
    officers: Officer[];
    canWaive: boolean;
    canViewEvidence: boolean;
    privacyNotice: string;
    retentionNotice: string;
}) {
    const tx = {
        department,
        distributionBatch: batchId,
        transaction: transactionId,
    };
    const [photo, setPhoto] = useState<File | null>(null);
    const [signature, setSignature] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [finalizeOpen, setFinalizeOpen] = useState(false);
    const form = useForm({
        received_by_type: 'beneficiary',
        received_by_name: selected.name ?? '',
        representative_relationship: '',
        representative_verification: '',
        verification_method: 'control_number',
        identity_verified: false,
        acknowledgment_mode: 'signature',
        alternative_reason: '',
        privacy_acknowledged: false,
        camera_waiver_reason: '',
        notes: '',
        witnessed_by: '',
        lines: selected.lines.map((line) => ({
            id: line.id,
            actual_quantity:
                line.kind === 'cash' ? '' : (line.remaining_quantity ?? ''),
            actual_amount:
                line.kind === 'cash' ? (line.remaining_amount ?? '') : '',
        })),
    });

    function upload(
        file: File,
        evidenceType: string,
        captureSource?: string,
    ): void {
        setUploading(true);
        router.post(
            storeEvidence.url(tx),
            {
                file,
                evidence_type: evidenceType,
                capture_source: captureSource ?? '',
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setUploading(false),
            },
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm">{privacyNotice}</p>
            <p className="text-sm text-muted-foreground">{retentionNotice}</p>
            <LivePhotoCapture
                disabled={uploading}
                onCapture={(file) => {
                    setPhoto(file);
                    upload(file, 'live_photo', 'camera');
                }}
            />
            {photo && <p className="text-sm">Photo ready for upload.</p>}
            {canViewEvidence &&
                selected.transaction?.evidence
                    .filter((row) => row.type === 'live_photo')
                    .map((row) => (
                        <a
                            key={row.id}
                            href={row.preview_url}
                            className="text-sm underline"
                        >
                            View saved photo
                        </a>
                    ))}
            {canWaive && (
                <label className="flex flex-col gap-1 text-sm">
                    Camera unavailable waiver
                    <Input
                        value={form.data.camera_waiver_reason}
                        onChange={(event) =>
                            form.setData(
                                'camera_waiver_reason',
                                event.target.value,
                            )
                        }
                        placeholder="Why the camera cannot be used"
                    />
                </label>
            )}
            <SignaturePad
                disabled={
                    uploading || form.data.acknowledgment_mode === 'alternative'
                }
                onChange={setSignature}
            />
            {signature && form.data.acknowledgment_mode === 'signature' && (
                <Button
                    type="button"
                    variant="outline"
                    disabled={uploading}
                    onClick={() => upload(signature, 'signature')}
                >
                    Save signature
                </Button>
            )}
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={form.data.acknowledgment_mode === 'alternative'}
                    onCheckedChange={(checked) =>
                        form.setData(
                            'acknowledgment_mode',
                            checked ? 'alternative' : 'signature',
                        )
                    }
                />
                Recipient cannot sign. Use an alternative acknowledgment.
            </label>
            {form.data.acknowledgment_mode === 'alternative' && (
                <Input
                    value={form.data.alternative_reason}
                    onChange={(event) =>
                        form.setData('alternative_reason', event.target.value)
                    }
                    placeholder="Reason and alternative used"
                />
            )}
            <Label>Recipient</Label>
            <Select
                value={form.data.received_by_type}
                onValueChange={(value) =>
                    form.setData('received_by_type', value)
                }
            >
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="beneficiary">Beneficiary</SelectItem>
                    <SelectItem value="representative">
                        Authorized representative
                    </SelectItem>
                </SelectContent>
            </Select>
            <Input
                value={form.data.received_by_name}
                onChange={(event) =>
                    form.setData('received_by_name', event.target.value)
                }
                aria-label="Recipient name"
            />
            {form.data.received_by_type === 'representative' && (
                <>
                    <Input
                        value={form.data.representative_relationship}
                        onChange={(event) =>
                            form.setData(
                                'representative_relationship',
                                event.target.value,
                            )
                        }
                        placeholder="Relationship"
                    />
                    <Input
                        value={form.data.representative_verification}
                        onChange={(event) =>
                            form.setData(
                                'representative_verification',
                                event.target.value,
                            )
                        }
                        placeholder="How authorization was checked"
                    />
                </>
            )}
            <Select
                value={form.data.verification_method}
                onValueChange={(value) =>
                    form.setData('verification_method', value)
                }
            >
                <SelectTrigger aria-label="Verification method">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="control_number">
                        Control number
                    </SelectItem>
                    <SelectItem value="request_reference">
                        Request reference
                    </SelectItem>
                    <SelectItem value="officer_attestation">
                        Officer attestation
                    </SelectItem>
                </SelectContent>
            </Select>
            <FieldGroup className="gap-4">
                {form.data.lines.map((line, index) => {
                    const source = selected.lines[index];
                    const cash = source?.kind === 'cash';
                    const kindLabel =
                        source?.kind === 'service'
                            ? 'Service'
                            : cash
                              ? 'Cash'
                              : 'Goods';

                    return (
                        <Field key={line.id}>
                            <FieldLabel htmlFor={`release-line-${line.id}`}>
                                {source?.name ?? 'Line'} · {kindLabel}
                            </FieldLabel>
                            <Input
                                id={`release-line-${line.id}`}
                                value={
                                    cash
                                        ? line.actual_amount
                                        : line.actual_quantity
                                }
                                inputMode="decimal"
                                aria-label={
                                    cash
                                        ? `Amount for ${source?.name ?? 'cash'}`
                                        : `Quantity for ${source?.name ?? 'line'}`
                                }
                                onChange={(event) => {
                                    const lines = [...form.data.lines];
                                    lines[index] = cash
                                        ? {
                                              ...line,
                                              actual_amount: event.target.value,
                                              actual_quantity: '',
                                          }
                                        : {
                                              ...line,
                                              actual_quantity:
                                                  event.target.value,
                                              actual_amount: '',
                                          };
                                    form.setData('lines', lines);
                                }}
                                placeholder={cash ? 'Amount' : 'Quantity'}
                            />
                        </Field>
                    );
                })}
            </FieldGroup>
            <Input
                value={form.data.notes}
                onChange={(event) => form.setData('notes', event.target.value)}
                placeholder="Notes, required for a partial release"
            />
            <Select
                value={form.data.witnessed_by || 'none'}
                onValueChange={(value) =>
                    form.setData('witnessed_by', value === 'none' ? '' : value)
                }
            >
                <SelectTrigger aria-label="Witness">
                    <SelectValue placeholder="Witness" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">No witness</SelectItem>
                    {officers.map((officer) => (
                        <SelectItem key={officer.id} value={String(officer.id)}>
                            {officer.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={form.data.identity_verified}
                    onCheckedChange={(checked) =>
                        form.setData('identity_verified', checked === true)
                    }
                />
                I verified this recipient.
            </label>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={form.data.privacy_acknowledged}
                    onCheckedChange={(checked) =>
                        form.setData('privacy_acknowledged', checked === true)
                    }
                />
                The privacy notice was explained and acknowledged.
            </label>
            {Object.entries(form.errors).map(([key, message]) => (
                <p key={key} className="text-sm text-destructive">
                    {message}
                </p>
            ))}
            <Button
                type="button"
                disabled={form.processing || uploading}
                onClick={() => setFinalizeOpen(true)}
            >
                Finalize release
            </Button>
            <StatusDialog
                open={finalizeOpen}
                onOpenChange={setFinalizeOpen}
                title="Finalize this release?"
                description="It will be recorded only after the server confirms it."
                status="Finalized"
                statusVariant="default"
                confirmLabel="Finalize release"
                pending={form.processing}
                onConfirm={() => {
                    form.transform((data) => ({
                        ...data,
                        witnessed_by:
                            data.witnessed_by === ''
                                ? null
                                : Number(data.witnessed_by),
                        lines: data.lines.map((line) => ({
                            id: line.id,
                            actual_quantity:
                                line.actual_quantity === ''
                                    ? null
                                    : line.actual_quantity,
                            actual_amount:
                                line.actual_amount === ''
                                    ? null
                                    : line.actual_amount,
                        })),
                    }));
                    form.post(finalize.url(tx), {
                        preserveScroll: true,
                        onSuccess: () => setFinalizeOpen(false),
                        onError: () => setFinalizeOpen(false),
                    });
                }}
            />
        </div>
    );
}

function LivePhotoCapture({
    disabled,
    onCapture,
}: {
    disabled: boolean;
    onCapture: (file: File) => void;
}) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [live, setLive] = useState(false);

    useEffect(
        () => () => {
            streamRef.current?.getTracks().forEach((track) => track.stop());
        },
        [],
    );

    useEffect(() => {
        if (videoRef.current && streamRef.current) {
            videoRef.current.srcObject = streamRef.current;
        }
    }, [live]);

    async function start(): Promise<void> {
        setError(null);

        if (!navigator.mediaDevices?.getUserMedia) {
            setError(
                'This device has no camera API. Ask an authorized officer to record the camera waiver. Do not upload a gallery photo as live evidence.',
            );

            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });
            streamRef.current = stream;

            if (videoRef.current) {
                videoRef.current.srcObject = stream;
            }

            setLive(true);
        } catch {
            setError(
                'Camera permission was denied or no camera is available. Ask an authorized officer to record the camera waiver. A gallery photo is not accepted as live evidence.',
            );
        }
    }

    function capture(): void {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        canvas.getContext('2d')?.drawImage(video, 0, 0);
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    setError('The photo could not be captured.');

                    return;
                }

                const file = new File([blob], 'live-photo.jpg', {
                    type: 'image/jpeg',
                });
                setPreview(URL.createObjectURL(blob));
                onCapture(file);
            },
            'image/jpeg',
            0.92,
        );
    }

    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">Live photo</p>
            {live && (
                <video
                    ref={videoRef}
                    autoPlay
                    playsInline
                    muted
                    className="max-h-64 w-full rounded-md bg-black"
                />
            )}
            {preview && (
                <img
                    src={preview}
                    alt="Captured live photo preview"
                    className="max-h-64 rounded-md"
                />
            )}
            {error && <p className="text-sm text-destructive">{error}</p>}
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    onClick={() => void start()}
                >
                    Open camera
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={disabled || !live}
                    onClick={capture}
                >
                    Capture
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!preview}
                    onClick={() => {
                        setPreview(null);
                    }}
                >
                    Retake
                </Button>
            </div>
        </div>
    );
}

function SignaturePad({
    disabled,
    onChange,
}: {
    disabled: boolean;
    onChange: (file: File | null) => void;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const strokes = useRef<{ x: number; y: number }[][]>([]);
    const drawing = useRef(false);

    function resize(): void {
        const canvas = canvasRef.current;

        if (!canvas) {
            return;
        }

        const ratio = window.devicePixelRatio || 1;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        const context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        context.scale(ratio, ratio);
        context.lineWidth = 2;
        context.lineCap = 'round';
        strokes.current.forEach((stroke) => {
            stroke.forEach((point, index) => {
                if (index === 0) {
                    context.beginPath();
                    context.moveTo(point.x * width, point.y * height);
                } else {
                    context.lineTo(point.x * width, point.y * height);
                }
            });
            context.stroke();
        });
    }

    useEffect(() => {
        resize();
        window.addEventListener('resize', resize);

        return () => window.removeEventListener('resize', resize);
    }, []);

    function point(event: React.PointerEvent<HTMLCanvasElement>): {
        x: number;
        y: number;
    } {
        const bounds = event.currentTarget.getBoundingClientRect();

        return {
            x: (event.clientX - bounds.left) / bounds.width,
            y: (event.clientY - bounds.top) / bounds.height,
        };
    }

    function exportPng(): void {
        const canvas = canvasRef.current;

        if (!canvas || strokes.current.length === 0) {
            onChange(null);

            return;
        }

        canvas.toBlob((blob) => {
            onChange(
                blob
                    ? new File([blob], 'signature.png', { type: 'image/png' })
                    : null,
            );
        }, 'image/png');
    }

    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">Signature</p>
            <canvas
                ref={canvasRef}
                className="h-40 w-full touch-none rounded-md border bg-white"
                aria-label="Signature canvas"
                onPointerDown={(event) => {
                    if (disabled) {
                        return;
                    }

                    drawing.current = true;
                    strokes.current.push([point(event)]);
                    event.currentTarget.setPointerCapture(event.pointerId);
                }}
                onPointerMove={(event) => {
                    if (!drawing.current || disabled) {
                        return;
                    }

                    strokes.current.at(-1)?.push(point(event));
                    resize();
                }}
                onPointerUp={() => {
                    drawing.current = false;
                    exportPng();
                }}
            />
            <Button
                type="button"
                variant="outline"
                className="w-fit"
                onClick={() => {
                    strokes.current = [];
                    onChange(null);
                    resize();
                }}
            >
                Clear signature
            </Button>
        </div>
    );
}

function AbsentForm({
    onSubmit,
}: {
    onSubmit: (remarks: string, visit: VisitCallbacks) => void;
}) {
    const remarksId = useId();
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);
    const [remarks, setRemarks] = useState('');

    return (
        <>
            <Button
                type="button"
                variant="outline"
                onClick={() => setOpen(true)}
            >
                Mark absent
            </Button>
            <StatusDialog
                open={open}
                onOpenChange={setOpen}
                title="Mark this beneficiary absent?"
                description="The absence is stored with the release record."
                status="Absent"
                statusVariant="secondary"
                confirmLabel="Mark absent"
                pending={pending}
                onConfirm={() =>
                    onSubmit(remarks, {
                        onStart: () => setPending(true),
                        onSuccess: () => {
                            setOpen(false);
                            setRemarks('');
                        },
                        onFinish: () => setPending(false),
                    })
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
                    </Field>
                </FieldGroup>
            </StatusDialog>
        </>
    );
}

function VoidForm({
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
                variant="outline"
                onClick={() => {
                    setNotice(null);
                    setOpen(true);
                }}
            >
                Void release
            </Button>
            <StatusDialog
                open={open}
                onOpenChange={setOpen}
                title="Void this finalized release?"
                description="The record stays in the audit log."
                status="Voided"
                statusVariant="destructive"
                confirmLabel="Void release"
                confirmVariant="destructive"
                pending={pending}
                onConfirm={() => {
                    if (reason.trim() === '') {
                        setNotice('A void reason is required.');

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
                        <FieldLabel htmlFor={reasonId}>Void reason</FieldLabel>
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
