import { useHttp } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { ApprovalBadge } from '@/components/user/approval-badge';
import { CodeChip } from '@/components/user/code-chip';
import type { ProgramCardModel } from '@/components/user/program-card';
import { formatPeso } from '@/lib/format-peso';
import { cn } from '@/lib/utils';
import { MunicipalityMapSkeleton } from '@/pages/user/dashboard/municipality-map-skeleton';
import { exportMethod as exportAssistances } from '@/routes/user/programs/assistances';
import type { MunicipalityMapPoint } from '@/types/dashboard';

const MunicipalityMap = lazy(() =>
    import('@/pages/user/dashboard/municipality-map').then((module) => ({
        default: module.MunicipalityMap,
    })),
);

type AnalyticsPayload = {
    summary: {
        beneficiaries: number;
        avg_age: number | null;
        age_min: number | null;
        age_max: number | null;
        pwd: number;
        seniors: number;
        budget: number;
        cash_released: number;
        budget_burn: number | null;
    };
    gender: { female: number; male: number; other: number };
    vulnerability: { key: string; label: string; count: number }[];
    age_brackets: { label: string; count: number }[];
    municipalities: { city: string; count: number; percent: number }[];
    roster: {
        data: {
            assistance_id: number;
            name: string | null;
            cais_number: string | null;
            sex: string | null;
            age: number | null;
            city: string | null;
            barangay: string | null;
            classifications: string[];
            status: string | null;
            was_delivered: boolean;
            receipt_url: string | null;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

const tabs = [
    { value: 'summary', label: 'Key Demographics & Summary' },
    { value: 'breakdown', label: 'Gender, PWD & Age' },
    { value: 'reach', label: 'Municipal Reach' },
    { value: 'roster', label: 'Beneficiary Census Roster' },
] as const;

export function ProgramAnalyticsDialog({
    program,
    url,
    exportUrl,
    open,
    onOpenChange,
}: {
    program: ProgramCardModel | null;
    url: string | null;
    exportUrl?: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { get, processing } = useHttp({});
    const getRef = useRef(get);

    useEffect(() => {
        getRef.current = get;
    });
    const [payload, setPayload] = useState<AnalyticsPayload | null>(null);
    const [tab, setTab] = useState<(typeof tabs)[number]['value']>('summary');
    const [search, setSearch] = useState('');
    const [sex, setSex] = useState('all');
    const [classification, setClassification] = useState('all');

    useEffect(() => {
        if (!open || !url) {
            return;
        }

        const params = new URLSearchParams();

        if (search.trim() !== '') {
            params.set('search', search.trim());
        }

        if (sex !== 'all') {
            params.set('sex', sex);
        }

        if (classification !== 'all') {
            params.set('classification', classification);
        }

        const query = params.toString();
        const handle = window.setTimeout(() => {
            getRef.current(query === '' ? url : `${url}?${query}`, {
                onSuccess: (response) => {
                    setPayload(response as AnalyticsPayload);
                },
            });
        }, 250);

        return () => window.clearTimeout(handle);
    }, [open, url, search, sex, classification]);

    const genderTotal =
        (payload?.gender.female ?? 0) +
        (payload?.gender.male ?? 0) +
        (payload?.gender.other ?? 0);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3 pr-8">
                        <div className="min-w-0 space-y-2">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <CodeChip>
                                    {program
                                        ? `PRG-${String(program.id).padStart(4, '0')}`
                                        : 'Program'}
                                </CodeChip>
                                <ApprovalBadge
                                    status={program?.approval_status}
                                />
                            </div>
                            <DialogTitle>{program?.name}</DialogTitle>
                            <DialogDescription>
                                {program?.descriptions?.trim() ||
                                    'Program analytics from recorded requests.'}
                            </DialogDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            {exportUrl ? (
                                <Button variant="outline" size="sm" asChild>
                                    <a href={exportUrl}>
                                        <Download className="size-4" />
                                        Export CSV
                                    </a>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex flex-wrap gap-2">
                    {tabs.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => setTab(item.value)}
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs',
                                tab === item.value
                                    ? 'border-foreground/20 bg-foreground text-background'
                                    : 'border-border text-muted-foreground',
                            )}
                        >
                            {item.label}
                            {item.value === 'roster' && payload
                                ? ` (${payload.roster.total})`
                                : ''}
                        </button>
                    ))}
                </div>

                {processing && !payload ? (
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Skeleton className="h-20" />
                        <Skeleton className="h-20" />
                        <Skeleton className="h-20" />
                    </div>
                ) : null}

                {payload && tab === 'summary' ? (
                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <SummaryTile
                                label="Beneficiaries"
                                value={payload.summary.beneficiaries.toLocaleString()}
                            />
                            <SummaryTile
                                label="Average age"
                                value={
                                    payload.summary.avg_age === null
                                        ? '—'
                                        : `${payload.summary.avg_age} yrs`
                                }
                                hint={
                                    payload.summary.age_min !== null &&
                                    payload.summary.age_max !== null
                                        ? `Range ${payload.summary.age_min}–${payload.summary.age_max}`
                                        : undefined
                                }
                            />
                            <SummaryTile
                                label="PWD"
                                value={payload.summary.pwd.toLocaleString()}
                            />
                            <SummaryTile
                                label="Seniors (60+)"
                                value={payload.summary.seniors.toLocaleString()}
                            />
                            <SummaryTile
                                label="Budget burn"
                                value={
                                    payload.summary.budget_burn === null
                                        ? '—'
                                        : `${payload.summary.budget_burn}%`
                                }
                                hint={
                                    payload.summary.budget > 0
                                        ? `${formatPeso(payload.summary.cash_released)} of ${formatPeso(payload.summary.budget)}`
                                        : undefined
                                }
                            />
                            <SummaryTile
                                label="Municipalities reached"
                                value={payload.municipalities.length.toLocaleString()}
                            />
                        </div>
                        <GenderBar gender={payload.gender} total={genderTotal} />
                    </div>
                ) : null}

                {payload && tab === 'breakdown' ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <BarList
                            title="Vulnerability & priority groups"
                            rows={payload.vulnerability.map((row) => ({
                                label: row.label,
                                count: row.count,
                            }))}
                            total={payload.summary.beneficiaries}
                        />
                        <BarList
                            title="Age bracket distribution"
                            rows={payload.age_brackets}
                            total={payload.age_brackets.reduce(
                                (sum, row) => sum + row.count,
                                0,
                            )}
                        />
                    </div>
                ) : null}

                {payload && tab === 'reach' ? (
                    <div className="grid items-start gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                        <Suspense fallback={<MunicipalityMapSkeleton compact />}>
                            <MunicipalityMap
                                municipalities={mapPoints(
                                    payload.municipalities,
                                )}
                                details="people"
                                compact
                            />
                        </Suspense>
                        <div className="space-y-3">
                            <p className="text-sm font-medium">
                                Municipal distribution
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {payload.municipalities.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No municipality is recorded yet.
                                    </p>
                                ) : (
                                    payload.municipalities.map((city) => (
                                        <div
                                            key={city.city}
                                            className="rounded-xl border border-border p-3"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="text-sm font-medium">
                                                    {city.city}
                                                </p>
                                                <p className="font-mono text-sm tabular-nums">
                                                    {city.count}{' '}
                                                    <span className="text-xs text-muted-foreground">
                                                        {city.count === 1
                                                            ? 'person'
                                                            : 'people'}
                                                    </span>
                                                </p>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {city.percent}% of recorded
                                                individuals
                                            </p>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                ) : null}

                {payload && tab === 'roster' ? (
                    <div className="space-y-3">
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search by resident name or CAIS number"
                            />
                            <Select value={sex} onValueChange={setSex}>
                                <SelectTrigger className="sm:w-40">
                                    <SelectValue placeholder="All genders" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All genders
                                    </SelectItem>
                                    <SelectItem value="Female">
                                        Female
                                    </SelectItem>
                                    <SelectItem value="Male">Male</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={classification}
                                onValueChange={setClassification}
                            >
                                <SelectTrigger className="sm:w-48">
                                    <SelectValue placeholder="All classifications" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All classifications
                                    </SelectItem>
                                    <SelectItem value="pwd">PWD</SelectItem>
                                    <SelectItem value="solo_parent">
                                        Solo parent
                                    </SelectItem>
                                    <SelectItem value="four_ps">4Ps</SelectItem>
                                    <SelectItem value="indigenous">
                                        Indigenous
                                    </SelectItem>
                                    <SelectItem value="senior">
                                        Senior
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="text-muted-foreground">
                                    <tr>
                                        <th className="py-2 pr-3 font-medium">
                                            Resident
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Sex / age
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            City
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Classification
                                        </th>
                                        <th className="py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payload.roster.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="py-6 text-muted-foreground"
                                            >
                                                No beneficiaries match these
                                                filters.
                                            </td>
                                        </tr>
                                    ) : (
                                        payload.roster.data.map((row) => (
                                            <tr
                                                key={row.assistance_id}
                                                className="border-t border-border"
                                            >
                                                <td className="py-2 pr-3">
                                                    <p className="font-medium">
                                                        {row.name}
                                                    </p>
                                                    <p className="font-mono text-[10px] text-muted-foreground">
                                                        {row.cais_number}
                                                    </p>
                                                </td>
                                                <td className="py-2 pr-3">
                                                    {row.sex ?? '—'}
                                                    {row.age !== null
                                                        ? ` · ${row.age}`
                                                        : ''}
                                                </td>
                                                <td className="py-2 pr-3">
                                                    <p>{row.city ?? '—'}</p>
                                                    <p className="text-muted-foreground">
                                                        {row.barangay}
                                                    </p>
                                                </td>
                                                <td className="py-2 pr-3">
                                                    {row.classifications.join(
                                                        ', ',
                                                    ) || '—'}
                                                </td>
                                                <td className="py-2">
                                                    {row.was_delivered &&
                                                    row.receipt_url ? (
                                                        <a
                                                            href={
                                                                row.receipt_url
                                                            }
                                                            className="text-emerald-700 dark:text-emerald-400"
                                                        >
                                                            {row.status ??
                                                                'Delivered'}
                                                        </a>
                                                    ) : (
                                                        (row.status ?? '—')
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

export function programExportUrl(
    departmentSlug: string,
    programId: number,
): string {
    return exportAssistances.url({
        department: departmentSlug,
        program: programId,
    });
}

function mapPoints(
    municipalities: AnalyticsPayload['municipalities'],
): MunicipalityMapPoint[] {
    return municipalities
        .filter((city) => city.city !== 'Unspecified')
        .map((city, index) => ({
            city_id: index + 1,
            city: city.city,
            beneficiaries: city.count,
            delivered_requests: 0,
            goods_released: 0,
            cash_disbursed: 0,
        }));
}

function SummaryTile({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-muted/30 p-3">
            <p className="text-[10px] tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 font-mono text-lg tabular-nums">{value}</p>
            {hint ? (
                <p className="mt-1 text-[10px] text-muted-foreground">{hint}</p>
            ) : null}
        </div>
    );
}

function GenderBar({
    gender,
    total,
}: {
    gender: AnalyticsPayload['gender'];
    total: number;
}) {
    const width = (count: number) =>
        total > 0 ? `${(count / total) * 100}%` : '0%';

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium">Gender</p>
            <div className="flex h-2 overflow-hidden rounded-full bg-muted">
                {total > 0 ? (
                    <>
                        <span
                            className="bg-rose-500"
                            style={{ width: width(gender.female) }}
                        />
                        <span
                            className="bg-sky-500"
                            style={{ width: width(gender.male) }}
                        />
                        <span
                            className="bg-violet-500"
                            style={{ width: width(gender.other) }}
                        />
                    </>
                ) : null}
            </div>
            <p className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-rose-500" />
                    {gender.female} female
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-sky-500" />
                    {gender.male} male
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-violet-500" />
                    {gender.other} other
                </span>
            </p>
        </div>
    );
}

function BarList({
    title,
    rows,
    total,
}: {
    title: string;
    rows: { label: string; count: number }[];
    total: number;
}) {
    return (
        <div className="space-y-3 rounded-xl border border-border p-3">
            <p className="text-sm font-medium">{title}</p>
            {rows.map((row) => (
                <div key={row.label} className="space-y-1">
                    <div className="flex justify-between text-xs">
                        <span>{row.label}</span>
                        <span className="font-mono tabular-nums">
                            {row.count} (
                            {total > 0
                                ? Math.round((row.count / total) * 100)
                                : 0}
                            %)
                        </span>
                    </div>
                    <Progress
                        value={total > 0 ? (row.count / total) * 100 : 0}
                    />
                </div>
            ))}
        </div>
    );
}
