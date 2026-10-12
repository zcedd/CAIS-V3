import { router } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { NoticeBanner } from '@/components/user/notice-banner';
import { formatPeso } from '@/lib/format-peso';
import { cn } from '@/lib/utils';
import { receipt as assistanceReceipt, show as assistanceShow } from '@/routes/user/assistances';
import { claim as claimAssistance } from '@/routes/user/programs/assistances';

export type QueueSelection = {
    id: number;
    program_id: number;
    program_name: string;
    cais_number: string | null;
    beneficiary_name: string | null;
    address: string | null;
    age: number | null;
    sex: string | null;
    primary_id: { type: string | null; number: string | null } | null;
    status: string | null;
    was_delivered: boolean;
    can_claim: boolean;
    items: { name: string; kind: string | null; quantity: number }[];
    findings: { code: string; message: string; severity: string }[];
};

function initials(name: string | null): string {
    return (name ?? '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

type DeskRow = {
    id: number;
    beneficiary_name: string | null;
    program_name: string | null;
    request_status: string | null;
};

export function PayoutDesk({
    departmentSlug,
    rows,
    selectedId,
    selected,
    onSelect,
}: {
    departmentSlug: string;
    rows: DeskRow[];
    selectedId: number | null;
    selected: QueueSelection | null;
    onSelect: (id: number) => void;
}) {
    const [claiming, setClaiming] = useState(false);

    return (
        <div className="grid items-start gap-4 lg:grid-cols-[340px_1fr]">
            <section className="rounded-xl border border-border bg-card">
                <div className="border-b border-border px-4 py-3">
                    <h2 className="text-sm font-medium">
                        1. Select recipient ({rows.length})
                    </h2>
                </div>
                <div className="max-h-[640px] space-y-2 overflow-y-auto p-3">
                    {rows.length === 0 ? (
                        <p className="px-2 py-6 text-sm text-muted-foreground">
                            No requests match this queue.
                        </p>
                    ) : (
                        rows.map((row) => (
                            <button
                                key={row.id}
                                type="button"
                                onClick={() => onSelect(row.id)}
                                className={cn(
                                    'flex w-full items-start gap-2 rounded-lg border px-3 py-2 text-left',
                                    selectedId === row.id
                                        ? 'border-emerald-500/40 bg-emerald-500/10'
                                        : 'border-border hover:bg-muted/40',
                                )}
                            >
                                <Avatar size="sm">
                                    <AvatarFallback>
                                        {initials(row.beneficiary_name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-medium">
                                        {row.beneficiary_name}
                                    </span>
                                    <span className="block truncate text-[11px] text-muted-foreground">
                                        {row.program_name}
                                    </span>
                                    <span className="mt-1 inline-flex rounded-full border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                        {row.request_status ?? 'Open'}
                                    </span>
                                </span>
                            </button>
                        ))
                    )}
                </div>
            </section>

            <div className="space-y-4">
                {selected === null ? (
                    <div className="rounded-xl border border-dashed border-border px-4 py-16 text-center text-sm text-muted-foreground">
                        Select a recipient to review the package and release
                        it.
                    </div>
                ) : (
                    <>
                        <section className="rounded-xl border border-border bg-card p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        {selected.beneficiary_name}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {[
                                            selected.address,
                                            selected.age !== null
                                                ? `Age ${selected.age}`
                                                : null,
                                            selected.sex,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || 'No profile details'}
                                    </p>
                                    <p className="mt-1 font-mono text-[11px] text-muted-foreground">
                                        {selected.primary_id?.type ?? 'CAIS'}{' '}
                                        {selected.primary_id?.number ??
                                            selected.cais_number}
                                    </p>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={assistanceShow.url({
                                            department: departmentSlug,
                                            program: selected.program_id,
                                            assistance: selected.id,
                                        })}
                                    >
                                        Full record
                                    </a>
                                </Button>
                            </div>
                        </section>

                        {selected.findings.map((finding) => (
                            <NoticeBanner
                                key={finding.code}
                                tone="amber"
                                icon={ShieldAlert}
                                title={
                                    finding.code === 'cooldown'
                                        ? 'Cooldown still applies'
                                        : 'Duplicate claim in the same program cycle'
                                }
                                description={finding.message}
                            />
                        ))}

                        <section className="rounded-xl border border-border bg-card p-4">
                            <h2 className="text-sm font-medium">
                                2. Assistance program & package
                            </h2>
                            <p className="mt-1 text-sm">{selected.program_name}</p>
                            <ul className="mt-3 space-y-2">
                                {selected.items.length === 0 ? (
                                    <li className="text-xs text-muted-foreground">
                                        No items are recorded.
                                    </li>
                                ) : (
                                    selected.items.map((item) => (
                                        <li
                                            key={`${item.name}-${item.quantity}`}
                                            className="flex items-center justify-between text-sm"
                                        >
                                            <span>{item.name}</span>
                                            <span className="font-mono tabular-nums">
                                                {item.kind === 'cash'
                                                    ? formatPeso(item.quantity)
                                                    : item.quantity.toLocaleString()}
                                            </span>
                                        </li>
                                    ))
                                )}
                            </ul>
                        </section>

                        <section className="rounded-xl border border-border bg-card p-4">
                            <h2 className="text-sm font-medium">
                                3. Verification & release
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Status: {selected.status ?? 'Open'}
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {selected.can_claim ? (
                                    <Button
                                        type="button"
                                        disabled={claiming}
                                        onClick={() => {
                                            setClaiming(true);
                                            router.patch(
                                                claimAssistance.url({
                                                    department: departmentSlug,
                                                    program:
                                                        selected.program_id,
                                                    assistance: selected.id,
                                                }),
                                                {},
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        toast.success(
                                                            'Request claimed.',
                                                        );
                                                        router.reload({
                                                            only: [
                                                                'selected',
                                                                'assistances',
                                                            ],
                                                        });
                                                    },
                                                    onError: () =>
                                                        toast.error(
                                                            'Could not claim this request.',
                                                        ),
                                                    onFinish: () =>
                                                        setClaiming(false),
                                                },
                                            );
                                        }}
                                    >
                                        {claiming ? 'Claiming...' : 'Claim'}
                                    </Button>
                                ) : null}
                                {selected.was_delivered ? (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={assistanceReceipt.url({
                                                department: departmentSlug,
                                                program: selected.program_id,
                                                assistance: selected.id,
                                            })}
                                        >
                                            Print receipt
                                        </a>
                                    </Button>
                                ) : null}
                            </div>
                        </section>
                    </>
                )}
            </div>
        </div>
    );
}
