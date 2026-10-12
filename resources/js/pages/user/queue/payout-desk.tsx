import { Link, router } from '@inertiajs/react';
import { Check, Search, ShieldAlert, UserSearch } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { NoticeBanner } from '@/components/user/notice-banner';
import { EMPTY_CELL } from '@/lib/empty-cell';
import { formatPeso } from '@/lib/format-peso';
import { cn } from '@/lib/utils';
import {
    receipt as assistanceReceipt,
    show as assistanceShow,
} from '@/routes/user/assistances';
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

type DeskStep = 1 | 2 | 3;

const steps: { id: DeskStep; label: string }[] = [
    { id: 1, label: 'Recipient' },
    { id: 2, label: 'Package' },
    { id: 3, label: 'Release' },
];

const statusBadgeClasses: Record<string, string> = {
    submitted: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    review: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-300',
    approved:
        'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    delivered: 'border-border bg-muted text-muted-foreground',
    denied: 'border-destructive/40 bg-destructive/10 text-destructive',
    'on hold':
        'border-orange-500/40 bg-orange-500/10 text-orange-700 dark:text-orange-300',
    on_hold:
        'border-orange-500/40 bg-orange-500/10 text-orange-700 dark:text-orange-300',
};

function queueStatusBadgeClass(status: string | null): string {
    const key = (status ?? '').trim().toLowerCase();

    return statusBadgeClasses[key] ?? 'border-border text-muted-foreground';
}

function isDeliveredStatus(status: string | null): boolean {
    return (status ?? '').trim().toLowerCase() === 'delivered';
}

function QueueStatusBadge({ status }: { status: string | null }) {
    const label = status ?? 'Open';

    return (
        <Badge
            variant="outline"
            className={cn(
                'h-auto py-0.5 text-[10px]',
                queueStatusBadgeClass(label),
            )}
        >
            {isDeliveredStatus(label) ? <Check /> : null}
            {label}
        </Badge>
    );
}

function displayValue(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return EMPTY_CELL;
    }

    return String(value);
}

function kindLabel(kind: string | null): string {
    if (kind === null || kind.trim() === '') {
        return EMPTY_CELL;
    }

    return kind.charAt(0).toUpperCase() + kind.slice(1);
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-border px-3 py-2">
            <dt className="text-[11px] text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm break-words">{value}</dd>
        </div>
    );
}

function DeskStepper({
    departmentSlug,
    selected,
}: {
    departmentSlug: string;
    selected: QueueSelection;
}) {
    const [step, setStep] = useState<DeskStep>(1);
    const [claiming, setClaiming] = useState(false);
    const cashItems = selected.items.filter((item) => item.kind === 'cash');
    const cashTotal = cashItems.reduce((sum, item) => sum + item.quantity, 0);

    return (
        <section
            className="rounded-xl border border-border bg-card"
            data-tour="queue-release"
        >
            <div className="flex items-start justify-between gap-3 border-b border-border px-4 py-4">
                <div className="min-w-0">
                    <h2 className="truncate text-base font-semibold">
                        {selected.beneficiary_name ?? 'Unknown recipient'}
                    </h2>
                    <p className="truncate text-xs text-muted-foreground">
                        {selected.program_name}
                    </p>
                </div>
                <Button variant="outline" size="sm" asChild>
                    <Link
                        href={assistanceShow.url({
                            department: departmentSlug,
                            program: selected.program_id,
                            assistance: selected.id,
                        })}
                    >
                        Full record
                    </Link>
                </Button>
            </div>

            <nav
                aria-label="Release steps"
                className="border-b border-border px-4 py-3"
                data-tour="queue-steps"
            >
                <ol className="flex items-center gap-2">
                    {steps.map((item, index) => {
                        const done = item.id < step;
                        const current = item.id === step;

                        return (
                            <li
                                key={item.id}
                                className={cn(
                                    'flex min-w-0 items-center gap-2',
                                    index < steps.length - 1 && 'flex-1',
                                )}
                            >
                                <button
                                    type="button"
                                    disabled={!done}
                                    aria-current={current ? 'step' : undefined}
                                    onClick={() => setStep(item.id)}
                                    className="flex shrink-0 items-center gap-2 disabled:cursor-default"
                                >
                                    <span
                                        className={cn(
                                            'flex size-7 items-center justify-center rounded-full border text-xs font-medium',
                                            done &&
                                                'border-emerald-500 bg-emerald-500 text-white',
                                            current &&
                                                'border-primary bg-primary text-primary-foreground',
                                            !done &&
                                                !current &&
                                                'border-border text-muted-foreground',
                                        )}
                                    >
                                        {done ? (
                                            <Check className="size-3.5" />
                                        ) : (
                                            item.id
                                        )}
                                    </span>
                                    <span
                                        className={cn(
                                            'hidden text-xs sm:inline',
                                            current
                                                ? 'font-medium text-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {item.label}
                                    </span>
                                </button>
                                {index < steps.length - 1 ? (
                                    <span
                                        className={cn(
                                            'h-px min-w-4 flex-1',
                                            done
                                                ? 'bg-emerald-500'
                                                : 'bg-border',
                                        )}
                                    />
                                ) : null}
                            </li>
                        );
                    })}
                </ol>
            </nav>

            <div className="min-h-56 p-4">
                {step === 1 ? (
                    <div className="space-y-4">
                        <h3 className="text-sm font-medium">
                            Verify recipient
                        </h3>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <Detail
                                label="Address"
                                value={displayValue(selected.address)}
                            />
                            <Detail
                                label="Age"
                                value={displayValue(selected.age)}
                            />
                            <Detail
                                label="Sex"
                                value={displayValue(selected.sex)}
                            />
                            <Detail
                                label="ID type"
                                value={displayValue(selected.primary_id?.type)}
                            />
                            <Detail
                                label="ID number"
                                value={displayValue(
                                    selected.primary_id?.number,
                                )}
                            />
                            <Detail
                                label="CAIS number"
                                value={displayValue(selected.cais_number)}
                            />
                        </dl>
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
                    </div>
                ) : null}

                {step === 2 ? (
                    <div className="space-y-3">
                        <div>
                            <h3 className="text-sm font-medium">
                                Review package
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {selected.program_name}
                            </p>
                        </div>
                        {selected.items.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                No items are recorded.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-lg border border-border">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead>Item</TableHead>
                                            <TableHead>Kind</TableHead>
                                            <TableHead className="text-right">
                                                Quantity
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {selected.items.map((item, index) => (
                                            <TableRow
                                                key={`${item.name}-${index}`}
                                                className="hover:bg-transparent"
                                            >
                                                <TableCell>
                                                    {item.name}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {kindLabel(item.kind)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono tabular-nums">
                                                    {item.kind === 'cash'
                                                        ? formatPeso(
                                                              item.quantity,
                                                          )
                                                        : item.quantity.toLocaleString()}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                    {cashItems.length > 0 ? (
                                        <TableFooter>
                                            <TableRow className="hover:bg-transparent">
                                                <TableCell colSpan={2}>
                                                    Total cash
                                                </TableCell>
                                                <TableCell className="text-right font-mono tabular-nums">
                                                    {formatPeso(cashTotal)}
                                                </TableCell>
                                            </TableRow>
                                        </TableFooter>
                                    ) : null}
                                </Table>
                            </div>
                        )}
                    </div>
                ) : null}

                {step === 3 ? (
                    <div className="space-y-3">
                        <h3 className="text-sm font-medium">Release</h3>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <Detail
                                label="Recipient"
                                value={displayValue(selected.beneficiary_name)}
                            />
                            <Detail
                                label="Program"
                                value={displayValue(selected.program_name)}
                            />
                            <Detail
                                label="Items"
                                value={selected.items.length.toLocaleString()}
                            />
                            <div className="rounded-lg border border-border px-3 py-2">
                                <dt className="text-[11px] text-muted-foreground">
                                    Status
                                </dt>
                                <dd className="mt-1">
                                    <QueueStatusBadge
                                        status={selected.status}
                                    />
                                </dd>
                            </div>
                        </dl>
                    </div>
                ) : null}
            </div>

            <div
                className="flex items-center justify-between gap-3 border-t border-border px-4 py-3"
                data-tour="queue-step-actions"
            >
                <Button
                    type="button"
                    variant="outline"
                    disabled={step === 1}
                    onClick={() =>
                        setStep((current) =>
                            current > 1 ? ((current - 1) as DeskStep) : current,
                        )
                    }
                >
                    Back
                </Button>
                {step < 3 ? (
                    <Button
                        type="button"
                        onClick={() =>
                            setStep((current) =>
                                current < 3
                                    ? ((current + 1) as DeskStep)
                                    : current,
                            )
                        }
                    >
                        {step === 1 && selected.findings.length > 0
                            ? 'Continue anyway'
                            : 'Next'}
                    </Button>
                ) : (
                    <div className="flex flex-wrap justify-end gap-2">
                        {selected.can_claim ? (
                            <Button
                                type="button"
                                disabled={claiming}
                                onClick={() => {
                                    setClaiming(true);
                                    router.patch(
                                        claimAssistance.url({
                                            department: departmentSlug,
                                            program: selected.program_id,
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
                                            onFinish: () => setClaiming(false),
                                        },
                                    );
                                }}
                            >
                                {claiming ? 'Claiming...' : 'Claim'}
                            </Button>
                        ) : null}
                        {selected.was_delivered ? (
                            <Button variant="outline" asChild>
                                <Link
                                    href={assistanceReceipt.url({
                                        department: departmentSlug,
                                        program: selected.program_id,
                                        assistance: selected.id,
                                    })}
                                >
                                    Print receipt
                                </Link>
                            </Button>
                        ) : null}
                        {!selected.can_claim && !selected.was_delivered ? (
                            <p className="text-xs text-muted-foreground">
                                This request cannot be released yet.
                            </p>
                        ) : null}
                    </div>
                )}
            </div>
        </section>
    );
}

export function PayoutDesk({
    departmentSlug,
    rows,
    selectedId,
    selected,
    onSelect,
    tab,
    onTabChange,
    search,
    onSearchChange,
}: {
    departmentSlug: string;
    rows: DeskRow[];
    selectedId: number | null;
    selected: QueueSelection | null;
    onSelect: (id: number) => void;
    tab: 'mine' | 'team';
    onTabChange: (tab: 'mine' | 'team') => void;
    search: string;
    onSearchChange: (search: string) => void;
}) {
    const [searchQuery, setSearchQuery] = useState(search);
    const [trackedSearch, setTrackedSearch] = useState(search);

    if (search !== trackedSearch) {
        setTrackedSearch(search);
        setSearchQuery(search);
    }

    useEffect(() => {
        const trimmed = searchQuery.trim();

        if (trimmed === search.trim()) {
            return;
        }

        const handle = window.setTimeout(() => {
            onSearchChange(trimmed);
        }, 250);

        return () => window.clearTimeout(handle);
    }, [searchQuery, search, onSearchChange]);

    return (
        <div className="grid items-start gap-4 lg:grid-cols-[340px_1fr]">
            <section
                className="rounded-xl border border-border bg-card"
                data-tour="queue-recipients"
            >
                <div className="space-y-3 border-b border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-medium">Recipients</h2>
                        <Badge variant="secondary">{rows.length}</Badge>
                    </div>
                    <Tabs
                        value={tab}
                        onValueChange={(value) =>
                            onTabChange(value === 'team' ? 'team' : 'mine')
                        }
                        data-tour="queue-tabs"
                    >
                        <TabsList className="w-full">
                            <TabsTrigger value="mine" className="flex-1">
                                My queue
                            </TabsTrigger>
                            <TabsTrigger value="team" className="flex-1">
                                Team queue
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <div className="relative" data-tour="queue-search">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={searchQuery}
                            onChange={(event) =>
                                setSearchQuery(event.target.value)
                            }
                            placeholder="Search beneficiary, CAIS, or program..."
                            className="pl-8"
                            aria-label="Search recipients"
                        />
                    </div>
                </div>
                <div className="max-h-[640px] space-y-2 overflow-y-auto p-3">
                    {rows.length === 0 ? (
                        <p className="px-2 py-6 text-sm text-muted-foreground">
                            {search.trim() !== ''
                                ? 'No requests match this search.'
                                : tab === 'mine'
                                  ? 'Nothing is assigned to you.'
                                  : 'No matching requests in the team queue.'}
                        </p>
                    ) : (
                        rows.map((row) => {
                            const selectedRow = selectedId === row.id;

                            return (
                                <button
                                    key={row.id}
                                    type="button"
                                    onClick={() => onSelect(row.id)}
                                    className={cn(
                                        'flex w-full items-start gap-2 rounded-lg border border-l-[3px] px-3 py-2 text-left',
                                        selectedRow
                                            ? 'border-emerald-500/40 border-l-emerald-500 bg-emerald-500/10'
                                            : 'border-border border-l-transparent hover:bg-muted/40',
                                    )}
                                >
                                    <Avatar size="sm">
                                        <AvatarFallback>
                                            {initials(row.beneficiary_name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">
                                            {row.beneficiary_name}
                                        </span>
                                        <span className="block truncate text-[11px] text-muted-foreground">
                                            {row.program_name}
                                        </span>
                                        <span className="mt-1 inline-flex">
                                            <QueueStatusBadge
                                                status={row.request_status}
                                            />
                                        </span>
                                    </span>
                                </button>
                            );
                        })
                    )}
                </div>
            </section>

            {selected === null ? (
                <div
                    className="flex min-h-80 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border px-6 py-16 text-center"
                    data-tour="queue-release"
                >
                    <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <UserSearch className="size-5" />
                    </span>
                    <div className="space-y-1">
                        <p className="text-sm font-medium">
                            No recipient selected
                        </p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Choose someone from the list to verify their
                            details, review the package, and release it.
                        </p>
                    </div>
                </div>
            ) : (
                <DeskStepper
                    key={selected.id}
                    departmentSlug={departmentSlug}
                    selected={selected}
                />
            )}
        </div>
    );
}
