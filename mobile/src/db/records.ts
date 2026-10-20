import type { AbsentPayload, OperationStatus, OperationType, PackageLine, ReleasePayload, SyncResult } from '@/api/types';

export type BatchRecord = {
    id: number;
    userId: number;
    batchCode: string;
    title: string;
    status: string;
    statusLabel: string;
    program: string | null;
    venue: string | null;
    venueDetails: string | null;
    scheduledAt: string | null;
    privacyNotice: string;
    retentionNotice: string;
    canExecute: boolean;
    canWaive: boolean;
    canIssueReceipt: boolean;
    serverTime: string | null;
    downloadedAt: string;
    itemCount: number;
    pendingCount: number;
    conflictCount: number;
};

export type ItemRecord = {
    id: number;
    batchId: number;
    assistanceId: number;
    name: string;
    caisNumber: string | null;
    approvalStatus: string;
    releaseStatus: string;
    lines: PackageLine[];
};

export type OfficerRecord = {
    id: number;
    name: string;
};

export type OperationRecord = {
    clientOperationId: string;
    idempotencyKey: string;
    userId: number;
    batchId: number;
    itemId: number;
    type: OperationType;
    payload: ReleasePayload | AbsentPayload;
    livePhotoPath: string | null;
    signaturePath: string | null;
    capturedAt: string;
    status: OperationStatus;
    attempts: number;
    lastError: string | null;
    result: SyncResult | null;
    createdAt: string;
    nextAttemptAt: string | null;
    batchCode: string | null;
    batchTitle: string | null;
    itemName: string | null;
    caisNumber: string | null;
};

export type BatchRow = {
    id: number;
    user_id: number;
    batch_code: string;
    title: string;
    status: string;
    status_label: string;
    program: string | null;
    venue: string | null;
    venue_details: string | null;
    scheduled_at: string | null;
    privacy_notice: string;
    retention_notice: string;
    can_execute: number;
    can_waive: number;
    can_issue_receipt: number;
    server_time: string | null;
    downloaded_at: string;
    item_count: number;
    pending_count: number;
    conflict_count: number;
};

export type ItemRow = {
    id: number;
    batch_id: number;
    assistance_id: number;
    name: string;
    cais_number: string | null;
    approval_status: string;
    release_status: string;
    lines_json: string;
};

export type OperationRow = {
    client_operation_id: string;
    idempotency_key: string;
    user_id: number;
    batch_id: number;
    item_id: number;
    type: string;
    payload_json: string;
    live_photo_path: string | null;
    signature_path: string | null;
    captured_at: string;
    status: string;
    attempts: number;
    last_error: string | null;
    result_json: string | null;
    created_at: string;
    next_attempt_at: string | null;
    batch_code: string | null;
    batch_title: string | null;
    item_name: string | null;
    cais_number: string | null;
};

function parseJson<T>(value: string | null, fallback: T): T {
    if (!value) {
        return fallback;
    }

    try {
        return JSON.parse(value) as T;
    } catch {
        return fallback;
    }
}

export function mapBatch(row: BatchRow): BatchRecord {
    return {
        id: row.id,
        userId: row.user_id,
        batchCode: row.batch_code,
        title: row.title,
        status: row.status,
        statusLabel: row.status_label,
        program: row.program,
        venue: row.venue,
        venueDetails: row.venue_details,
        scheduledAt: row.scheduled_at,
        privacyNotice: row.privacy_notice,
        retentionNotice: row.retention_notice,
        canExecute: row.can_execute === 1,
        canWaive: row.can_waive === 1,
        canIssueReceipt: row.can_issue_receipt === 1,
        serverTime: row.server_time,
        downloadedAt: row.downloaded_at,
        itemCount: row.item_count ?? 0,
        pendingCount: row.pending_count ?? 0,
        conflictCount: row.conflict_count ?? 0,
    };
}

export function mapItem(row: ItemRow): ItemRecord {
    const lines = parseJson<PackageLine[]>(row.lines_json, []);

    return {
        id: row.id,
        batchId: row.batch_id,
        assistanceId: row.assistance_id,
        name: row.name,
        caisNumber: row.cais_number,
        approvalStatus: row.approval_status,
        releaseStatus: row.release_status,
        lines: Array.isArray(lines) ? lines : [],
    };
}

export function mapOperation(row: OperationRow): OperationRecord {
    const type: OperationType = row.type === 'absent' ? 'absent' : 'release';
    const status = row.status as OperationStatus;

    return {
        clientOperationId: row.client_operation_id,
        idempotencyKey: row.idempotency_key,
        userId: row.user_id,
        batchId: row.batch_id,
        itemId: row.item_id,
        type,
        payload: parseJson<ReleasePayload | AbsentPayload>(row.payload_json, {
            remarks: '',
            beneficiary_name: '',
        }),
        livePhotoPath: row.live_photo_path,
        signaturePath: row.signature_path,
        capturedAt: row.captured_at,
        status,
        attempts: row.attempts,
        lastError: row.last_error,
        result: parseJson<SyncResult | null>(row.result_json, null),
        createdAt: row.created_at,
        nextAttemptAt: row.next_attempt_at,
        batchCode: row.batch_code,
        batchTitle: row.batch_title,
        itemName: row.item_name,
        caisNumber: row.cais_number,
    };
}
