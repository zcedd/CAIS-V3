import type {
    AbsentSyncResult,
    AccountProfile,
    AuthSession,
    Department,
    LineKind,
    LoginResult,
    MobileUser,
    OfflinePackage,
    PackageBatch,
    PackageItem,
    PackageLine,
    ReleaseStatus,
    ReleaseSyncResult,
    RemoteBatch,
    SyncStatus,
    TwoFactorChallenge,
} from '@/api/types';
import { asRecord } from '@/domain/text';

function requiredString(row: Record<string, unknown>, key: string): string {
    const value = row[key];

    if (typeof value !== 'string' || value.trim() === '') {
        throw new Error(`The server response is missing ${key}.`);
    }

    return value;
}

function optionalString(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function requiredNumber(row: Record<string, unknown>, key: string): number {
    const value = Number(row[key]);

    if (!Number.isFinite(value)) {
        throw new Error(`The server response is missing ${key}.`);
    }

    return value;
}

function optionalNumber(value: unknown): number | null {
    if (value == null || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : null;
}

function decimal(value: unknown): string | null {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }

    return null;
}

function readSyncStatus(value: unknown): SyncStatus {
    if (value === 'applied' || value === 'already_applied') {
        return value;
    }

    throw new Error('The server returned an unexpected sync status.');
}

export function readUser(value: unknown): MobileUser {
    const row = asRecord(value);

    if (!row) {
        throw new Error('The server did not return an account.');
    }

    return {
        id: requiredNumber(row, 'id'),
        name: requiredString(row, 'name'),
        email: requiredString(row, 'email'),
        department_id: optionalNumber(row.department_id),
    };
}

function readDepartment(value: unknown): Department | null {
    if (value == null) {
        return null;
    }

    const row = asRecord(value);

    if (!row) {
        throw new Error('The server returned an unreadable department.');
    }

    return {
        id: requiredNumber(row, 'id'),
        name: requiredString(row, 'name'),
        slug: optionalString(row.slug) ?? '',
    };
}

export function readLogin(value: unknown): LoginResult {
    const row = asRecord(value);

    if (!row) {
        throw new Error('The server returned an unexpected sign-in response.');
    }

    if (row.two_factor === true) {
        const challenge: TwoFactorChallenge = {
            twoFactor: true,
            challengeToken: requiredString(row, 'challenge_token'),
        };

        return challenge;
    }

    const session: AuthSession = {
        token: requiredString(row, 'token'),
        tokenType: optionalString(row.token_type) ?? 'Bearer',
        user: readUser(row.user),
    };

    return session;
}

export function readProfile(value: unknown): AccountProfile {
    const row = asRecord(value);

    if (!row) {
        throw new Error('The server did not return an account.');
    }

    const permissions = Array.isArray(row.permissions)
        ? row.permissions.filter((entry): entry is string => typeof entry === 'string')
        : [];

    return {
        user: readUser(row.user),
        department: readDepartment(row.department),
        permissions,
    };
}

export function readBatch(value: unknown): RemoteBatch {
    const row = asRecord(value);

    if (!row) {
        throw new Error('The server returned an unreadable batch.');
    }

    return {
        id: requiredNumber(row, 'id'),
        batch_code: requiredString(row, 'batch_code'),
        title: requiredString(row, 'title'),
        status: requiredString(row, 'status'),
        status_label: requiredString(row, 'status_label'),
        program: optionalString(row.program),
        venue: optionalString(row.venue),
        venue_details: optionalString(row.venue_details),
        scheduled_at: optionalString(row.scheduled_at),
    };
}

export function readBatchList(value: unknown): RemoteBatch[] {
    const row = asRecord(value);

    if (!row || !Array.isArray(row.data)) {
        throw new Error('The server did not return a batch list.');
    }

    return row.data.map(readBatch);
}

function readPackageBatch(value: unknown): PackageBatch {
    const batch = readBatch(value);
    const row = asRecord(value);

    return {
        ...batch,
        department_id: optionalNumber(row?.department_id),
    };
}

function readKind(value: unknown): LineKind {
    if (value === 'cash' || value === 'service' || value === 'goods') {
        return value;
    }

    return 'goods';
}

function readLine(value: unknown): PackageLine {
    const row = asRecord(value);

    if (!row) {
        throw new Error('The server returned an unreadable assistance line.');
    }

    return {
        id: requiredNumber(row, 'id'),
        name: optionalString(row.name) ?? 'Assistance',
        kind: readKind(row.kind),
        unit: optionalString(row.unit),
        approved_quantity: decimal(row.approved_quantity),
        approved_amount: decimal(row.approved_amount),
        released_quantity: decimal(row.released_quantity),
        released_amount: decimal(row.released_amount),
        remaining_quantity: decimal(row.remaining_quantity),
        remaining_amount: decimal(row.remaining_amount),
    };
}

function readReleaseStatus(value: unknown): ReleaseStatus {
    if (value === 'pending' || value === 'partial' || value === 'served' || value === 'absent' || value === 'excluded') {
        return value;
    }

    return 'pending';
}

function readItem(value: unknown): PackageItem {
    const row = asRecord(value);

    if (!row) {
        throw new Error('The server returned an unreadable beneficiary.');
    }

    const lines = Array.isArray(row.lines) ? row.lines.map(readLine) : [];

    return {
        id: requiredNumber(row, 'id'),
        assistance_id: requiredNumber(row, 'assistance_id'),
        name: optionalString(row.name) ?? 'Beneficiary',
        cais_number: optionalString(row.cais_number),
        approval_status: optionalString(row.approval_status) ?? 'approved',
        release_status: readReleaseStatus(row.release_status),
        lines,
    };
}

export function readPackage(value: unknown): OfflinePackage {
    const row = asRecord(value);

    if (!row || !row.batch) {
        throw new Error('The server did not return an offline package.');
    }

    const can = asRecord(row.can) ?? {};
    const officers = Array.isArray(row.officers)
        ? row.officers.flatMap((entry) => {
              const officer = asRecord(entry);

              if (!officer) {
                  return [];
              }

              return [{ id: requiredNumber(officer, 'id'), name: optionalString(officer.name) ?? 'Officer' }];
          })
        : [];
    const items = Array.isArray(row.items) ? row.items.map(readItem) : [];

    return {
        batch: readPackageBatch(row.batch),
        items,
        officers,
        can: {
            execute: can.execute === true || can.execute === 1,
            waive: can.waive === true || can.waive === 1,
            issueReceipt: can.issueReceipt === true || can.issueReceipt === 1,
        },
        privacy_notice: optionalString(row.privacy_notice) ?? '',
        retention_notice: optionalString(row.retention_notice) ?? '',
        server_time: optionalString(row.server_time),
    };
}

export function readReleaseSyncResult(value: unknown): ReleaseSyncResult {
    const row = asRecord(value);

    if (!row) {
        throw new Error('The server returned an unexpected sync response.');
    }

    return {
        status: readSyncStatus(row.status),
        transaction_number: requiredString(row, 'transaction_number'),
        receipt_number: optionalString(row.receipt_number),
        receipt_pdf_url: requiredString(row, 'receipt_pdf_url'),
    };
}

export function readAbsentSyncResult(value: unknown): AbsentSyncResult {
    const row = asRecord(value);

    if (!row) {
        throw new Error('The server returned an unexpected sync response.');
    }

    return {
        status: readSyncStatus(row.status),
        distribution_release_id: requiredNumber(row, 'distribution_release_id'),
    };
}

export function readErrorBody(value: unknown): { message: string; errors: Record<string, string[]> } {
    const row = asRecord(value);
    const message = optionalString(row?.message) ?? 'The server rejected this request.';
    const errors: Record<string, string[]> = {};
    const rawErrors = asRecord(row?.errors);

    if (rawErrors) {
        for (const [field, messages] of Object.entries(rawErrors)) {
            if (Array.isArray(messages)) {
                errors[field] = messages.map((entry) => String(entry));
            } else if (typeof messages === 'string') {
                errors[field] = [messages];
            }
        }
    }

    return { message, errors };
}
