import { Link, usePoll } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { show as distributionShow } from '@/routes/user/distributions';

export type FieldSyncConflict = {
    id: number;
    beneficiary_name: string | null;
    cais_number: string | null;
    officer_name: string | null;
    type: string;
    type_label: string;
    device_captured_at: string | null;
    received_at: string | null;
    lag_seconds: number;
    lag_warning: boolean;
    message: string | null;
};

export type FieldSyncOfficer = {
    id: number;
    name: string;
    applied_count: number;
    conflict_count: number;
    last_received_at: string | null;
    worst_lag_seconds: number | null;
    lag_warning: boolean;
};

export type FieldSyncSummary = {
    applied_count: number;
    conflict_count: number;
    last_received_at: string | null;
    lag_warning_minutes: number;
    unserved_beneficiaries: number;
    unserved_with_conflict: number;
    officers: FieldSyncOfficer[];
    conflicts: FieldSyncConflict[];
};

export type FieldSyncAlertData = {
    applied_count: number;
    conflict_count: number;
    conflicts: FieldSyncConflict[];
};

export function useFieldSyncPoll(active: boolean): void {
    usePoll(30_000, { only: ['field_sync'] }, { autoStart: active });
}

function formatWhen(value: string | null): string {
    if (!value) {
        return 'Not yet';
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

function formatLag(seconds: number): string {
    if (seconds < 60) {
        return 'Under 1 min';
    }

    const minutes = Math.floor(seconds / 60);

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

function personLabel(conflict: FieldSyncConflict): string {
    const name = conflict.beneficiary_name ?? 'Unknown beneficiary';

    return conflict.cais_number
        ? `${name} · CAIS ${conflict.cais_number}`
        : name;
}

export function FieldSyncPanel({ sync }: { sync: FieldSyncSummary }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Field sync</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Accepted
                        </dt>
                        <dd className="font-medium tabular-nums">
                            {sync.applied_count.toLocaleString()}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Refused
                        </dt>
                        <dd className="font-medium tabular-nums">
                            {sync.conflict_count.toLocaleString()}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Last received
                        </dt>
                        <dd className="font-medium">
                            {formatWhen(sync.last_received_at)}
                        </dd>
                    </div>
                </dl>
                <p className="text-sm text-muted-foreground">
                    {sync.unserved_beneficiaries.toLocaleString()} approved{' '}
                    {sync.unserved_beneficiaries === 1
                        ? 'beneficiary has'
                        : 'beneficiaries have'}{' '}
                    no outcome yet.
                    {sync.unserved_with_conflict > 0
                        ? ` ${sync.unserved_with_conflict.toLocaleString()} of them ${sync.unserved_with_conflict === 1 ? 'has' : 'have'} a refused phone record.`
                        : ''}{' '}
                    A phone can still be holding a record that has not reached
                    the server. An upload slower than {sync.lag_warning_minutes}{' '}
                    minutes is marked. Times captured on the device can differ
                    from the server clock.
                </p>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Officer</TableHead>
                                <TableHead>Accepted</TableHead>
                                <TableHead>Refused</TableHead>
                                <TableHead>Last received</TableHead>
                                <TableHead>Slowest upload</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sync.officers.map((officer) => (
                                <TableRow key={officer.id}>
                                    <TableCell>{officer.name}</TableCell>
                                    <TableCell className="tabular-nums">
                                        {officer.applied_count.toLocaleString()}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {officer.conflict_count.toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        {officer.last_received_at
                                            ? formatWhen(
                                                  officer.last_received_at,
                                              )
                                            : 'Nothing received yet'}
                                    </TableCell>
                                    <TableCell>
                                        {officer.worst_lag_seconds === null ? (
                                            '—'
                                        ) : officer.lag_warning ? (
                                            <Badge variant="destructive">
                                                {formatLag(
                                                    officer.worst_lag_seconds,
                                                )}
                                            </Badge>
                                        ) : (
                                            formatLag(officer.worst_lag_seconds)
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {sync.conflicts.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <h3 className="text-sm font-medium">Refused records</h3>
                        <ul className="flex flex-col gap-3">
                            {sync.conflicts.map((conflict) => (
                                <li key={conflict.id} className="text-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {personLabel(conflict)}
                                        </span>
                                        <Badge variant="outline">
                                            {conflict.type_label}
                                        </Badge>
                                        {conflict.lag_warning ? (
                                            <Badge variant="destructive">
                                                {formatLag(
                                                    conflict.lag_seconds,
                                                )}{' '}
                                                to arrive
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <p className="text-muted-foreground">
                                        {conflict.officer_name ??
                                            'Unknown officer'}
                                        {' · captured on device '}
                                        {formatWhen(
                                            conflict.device_captured_at,
                                        )}
                                        {' · received '}
                                        {formatWhen(conflict.received_at)}
                                    </p>
                                    {conflict.message ? (
                                        <p>{conflict.message}</p>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export function FieldSyncAlert({
    sync,
    department,
    batchId,
}: {
    sync: FieldSyncAlertData | null;
    department: string;
    batchId: number;
}) {
    if (!sync || sync.conflict_count === 0) {
        return null;
    }

    const shown = sync.conflicts.slice(0, 3);

    return (
        <Alert variant="destructive">
            <AlertTriangle />
            <AlertTitle>
                {sync.conflict_count.toLocaleString()} field{' '}
                {sync.conflict_count === 1
                    ? 'record was refused'
                    : 'records were refused'}
            </AlertTitle>
            <AlertDescription>
                <ul className="flex flex-col gap-1">
                    {shown.map((conflict) => (
                        <li key={conflict.id}>
                            {personLabel(conflict)}
                            {conflict.message ? `: ${conflict.message}` : ''}
                        </li>
                    ))}
                </ul>
                <Link
                    href={distributionShow.url({
                        department,
                        distributionBatch: batchId,
                    })}
                    className="mt-2 inline-block font-medium"
                >
                    Open the batch
                </Link>
            </AlertDescription>
        </Alert>
    );
}
