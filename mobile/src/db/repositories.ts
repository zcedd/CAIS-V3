import type { AbsentPayload, OfflinePackage, ReleasePayload, SyncResult } from '@/api/types';
import { getDatabase } from '@/db/database';
import {
    mapBatch,
    mapItem,
    mapOperation,
    type BatchRecord,
    type BatchRow,
    type ItemRecord,
    type ItemRow,
    type OfficerRecord,
    type OperationRecord,
    type OperationRow,
} from '@/db/records';

const BATCH_SELECT = `
    SELECT
        b.*,
        (SELECT COUNT(*) FROM batch_items i WHERE i.batch_id = b.id) AS item_count,
        (SELECT COUNT(*) FROM operations o WHERE o.batch_id = b.id AND o.user_id = b.user_id AND o.status IN ('pending', 'syncing')) AS pending_count,
        (SELECT COUNT(*) FROM operations o WHERE o.batch_id = b.id AND o.user_id = b.user_id AND o.status = 'conflict') AS conflict_count
    FROM batches b
`;

const OPERATION_SELECT = `
    SELECT
        o.*,
        b.batch_code,
        b.title AS batch_title,
        i.name AS item_name,
        i.cais_number
    FROM operations o
    LEFT JOIN batches b ON b.id = o.batch_id
    LEFT JOIN batch_items i ON i.id = o.item_id AND i.batch_id = o.batch_id
`;

export async function listBatches(userId: number): Promise<BatchRecord[]> {
    const db = await getDatabase();
    const rows = await db.getAllAsync<BatchRow>(
        `${BATCH_SELECT} WHERE b.user_id = ? ORDER BY b.scheduled_at IS NULL, b.scheduled_at, b.title`,
        userId,
    );

    return rows.map(mapBatch);
}

export async function getBatch(userId: number, batchId: number): Promise<BatchRecord | null> {
    const db = await getDatabase();
    const row = await db.getFirstAsync<BatchRow>(`${BATCH_SELECT} WHERE b.user_id = ? AND b.id = ?`, userId, batchId);

    return row ? mapBatch(row) : null;
}

export async function listItems(batchId: number): Promise<ItemRecord[]> {
    const db = await getDatabase();
    const rows = await db.getAllAsync<ItemRow>(
        'SELECT * FROM batch_items WHERE batch_id = ? ORDER BY name COLLATE NOCASE, id',
        batchId,
    );

    return rows.map(mapItem);
}

export async function getItem(batchId: number, itemId: number): Promise<ItemRecord | null> {
    const db = await getDatabase();
    const row = await db.getFirstAsync<ItemRow>(
        'SELECT * FROM batch_items WHERE batch_id = ? AND id = ?',
        batchId,
        itemId,
    );

    return row ? mapItem(row) : null;
}

export async function listOfficers(batchId: number): Promise<OfficerRecord[]> {
    const db = await getDatabase();

    return db.getAllAsync<OfficerRecord>(
        'SELECT id, name FROM officers WHERE batch_id = ? ORDER BY name COLLATE NOCASE',
        batchId,
    );
}

export async function savePackage(userId: number, offlinePackage: OfflinePackage): Promise<void> {
    const db = await getDatabase();
    const batch = offlinePackage.batch;
    const downloadedAt = new Date().toISOString();

    await db.withTransactionAsync(async () => {
        await db.runAsync(
            `INSERT INTO batches (
                id, user_id, batch_code, title, status, status_label, program, venue, venue_details,
                scheduled_at, privacy_notice, retention_notice, can_execute, can_waive, can_issue_receipt,
                server_time, downloaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(id) DO UPDATE SET
                user_id = excluded.user_id,
                batch_code = excluded.batch_code,
                title = excluded.title,
                status = excluded.status,
                status_label = excluded.status_label,
                program = excluded.program,
                venue = excluded.venue,
                venue_details = excluded.venue_details,
                scheduled_at = excluded.scheduled_at,
                privacy_notice = excluded.privacy_notice,
                retention_notice = excluded.retention_notice,
                can_execute = excluded.can_execute,
                can_waive = excluded.can_waive,
                can_issue_receipt = excluded.can_issue_receipt,
                server_time = excluded.server_time,
                downloaded_at = excluded.downloaded_at`,
            batch.id,
            userId,
            batch.batch_code,
            batch.title,
            batch.status,
            batch.status_label,
            batch.program,
            batch.venue,
            batch.venue_details,
            batch.scheduled_at,
            offlinePackage.privacy_notice,
            offlinePackage.retention_notice,
            offlinePackage.can.execute ? 1 : 0,
            offlinePackage.can.waive ? 1 : 0,
            offlinePackage.can.issueReceipt ? 1 : 0,
            offlinePackage.server_time,
            downloadedAt,
        );
        await db.runAsync('DELETE FROM batch_items WHERE batch_id = ?', batch.id);
        await db.runAsync('DELETE FROM officers WHERE batch_id = ?', batch.id);

        for (const item of offlinePackage.items) {
            await db.runAsync(
                `INSERT INTO batch_items (
                    id, batch_id, assistance_id, name, cais_number, approval_status, release_status, lines_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
                item.id,
                batch.id,
                item.assistance_id,
                item.name,
                item.cais_number,
                item.approval_status,
                item.release_status,
                JSON.stringify(item.lines),
            );
        }

        for (const officer of offlinePackage.officers) {
            await db.runAsync(
                'INSERT INTO officers (batch_id, id, name) VALUES (?, ?, ?)',
                batch.id,
                officer.id,
                officer.name,
            );
        }
    });
}

export type RemovedBatch =
    | { ok: true; files: { live_photo_path: string | null; signature_path: string | null }[] }
    | { ok: false; reason: string };

export async function removeBatch(batchId: number): Promise<RemovedBatch> {
    const db = await getDatabase();
    const blocking = await db.getFirstAsync<{ count: number }>(
        `SELECT COUNT(*) AS count FROM operations WHERE batch_id = ? AND status IN ('pending', 'syncing')`,
        batchId,
    );

    if ((blocking?.count ?? 0) > 0) {
        return {
            ok: false,
            reason: 'This batch still has releases waiting to sync. Sync or resolve them before removing it.',
        };
    }

    const files = await db.getAllAsync<{ live_photo_path: string | null; signature_path: string | null }>(
        'SELECT live_photo_path, signature_path FROM operations WHERE batch_id = ?',
        batchId,
    );
    await db.runAsync('DELETE FROM operations WHERE batch_id = ?', batchId);
    await db.runAsync('DELETE FROM batches WHERE id = ?', batchId);

    return { ok: true, files };
}

export async function listItemOperations(batchId: number, itemId: number): Promise<OperationRecord[]> {
    const db = await getDatabase();
    const rows = await db.getAllAsync<OperationRow>(
        `${OPERATION_SELECT} WHERE o.batch_id = ? AND o.item_id = ? ORDER BY o.created_at ASC`,
        batchId,
        itemId,
    );

    return rows.map(mapOperation);
}

export async function listBatchOperations(batchId: number): Promise<OperationRecord[]> {
    const db = await getDatabase();
    const rows = await db.getAllAsync<OperationRow>(
        `${OPERATION_SELECT} WHERE o.batch_id = ? ORDER BY o.created_at ASC`,
        batchId,
    );

    return rows.map(mapOperation);
}

export async function listUserOperations(userId: number): Promise<OperationRecord[]> {
    const db = await getDatabase();
    const rows = await db.getAllAsync<OperationRow>(
        `${OPERATION_SELECT} WHERE o.user_id = ? ORDER BY o.created_at DESC`,
        userId,
    );

    return rows.map(mapOperation);
}

export async function countUnsynced(userId: number): Promise<number> {
    const db = await getDatabase();
    const row = await db.getFirstAsync<{ count: number }>(
        `SELECT COUNT(*) AS count FROM operations WHERE user_id = ? AND status IN ('pending', 'syncing', 'conflict')`,
        userId,
    );

    return row?.count ?? 0;
}

export async function countQueue(userId: number): Promise<{ pending: number; conflict: number }> {
    const db = await getDatabase();
    const rows = await db.getAllAsync<{ status: string; count: number }>(
        `SELECT status, COUNT(*) AS count
         FROM operations
         WHERE user_id = ? AND status IN ('pending', 'syncing', 'conflict')
         GROUP BY status`,
        userId,
    );
    let pending = 0;
    let conflict = 0;

    for (const row of rows) {
        if (row.status === 'conflict') {
            conflict += row.count;
        } else {
            pending += row.count;
        }
    }

    return { pending, conflict };
}

export async function insertOperation(input: {
    clientOperationId: string;
    idempotencyKey: string;
    userId: number;
    batchId: number;
    itemId: number;
    type: 'release' | 'absent';
    payload: ReleasePayload | AbsentPayload;
    livePhotoPath: string | null;
    signaturePath: string | null;
    capturedAt: string;
}): Promise<void> {
    const db = await getDatabase();
    await db.runAsync(
        `INSERT INTO operations (
            client_operation_id, idempotency_key, user_id, batch_id, item_id, type, payload_json,
            live_photo_path, signature_path, captured_at, status, attempts, last_error, result_json,
            created_at, next_attempt_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NULL, NULL, ?, NULL)`,
        input.clientOperationId,
        input.idempotencyKey,
        input.userId,
        input.batchId,
        input.itemId,
        input.type,
        JSON.stringify(input.payload),
        input.livePhotoPath,
        input.signaturePath,
        input.capturedAt,
        input.capturedAt,
    );
}

export async function resetInterruptedSync(): Promise<void> {
    const db = await getDatabase();
    await db.runAsync(`UPDATE operations SET status = 'pending' WHERE status = 'syncing'`);
}

export async function listReadyOperations(userId: number, nowIso: string): Promise<OperationRecord[]> {
    const db = await getDatabase();
    const rows = await db.getAllAsync<OperationRow>(
        `${OPERATION_SELECT}
         WHERE o.user_id = ?
           AND o.status = 'pending'
           AND (o.next_attempt_at IS NULL OR o.next_attempt_at <= ?)
         ORDER BY o.created_at ASC`,
        userId,
        nowIso,
    );

    return rows.map(mapOperation);
}

export async function markSyncing(clientOperationId: string): Promise<void> {
    const db = await getDatabase();
    await db.runAsync(
        `UPDATE operations SET status = 'syncing' WHERE client_operation_id = ? AND status = 'pending'`,
        clientOperationId,
    );
}

export async function markApplied(clientOperationId: string, result: SyncResult): Promise<void> {
    const db = await getDatabase();
    await db.runAsync(
        `UPDATE operations
         SET status = 'applied', result_json = ?, last_error = NULL, next_attempt_at = NULL
         WHERE client_operation_id = ?`,
        JSON.stringify(result),
        clientOperationId,
    );
}

export async function markConflict(clientOperationId: string, message: string): Promise<void> {
    const db = await getDatabase();
    await db.runAsync(
        `UPDATE operations
         SET status = 'conflict', last_error = ?, attempts = attempts + 1, next_attempt_at = NULL
         WHERE client_operation_id = ?`,
        message,
        clientOperationId,
    );
}

export async function markRetry(clientOperationId: string, message: string, nextAttemptAt: string): Promise<void> {
    const db = await getDatabase();
    await db.runAsync(
        `UPDATE operations
         SET status = 'pending', last_error = ?, attempts = attempts + 1, next_attempt_at = ?
         WHERE client_operation_id = ?`,
        message,
        nextAttemptAt,
        clientOperationId,
    );
}

export async function requeueOperation(clientOperationId: string): Promise<void> {
    const db = await getDatabase();
    await db.runAsync(
        `UPDATE operations
         SET status = 'pending', next_attempt_at = NULL
         WHERE client_operation_id = ? AND status IN ('pending', 'conflict')`,
        clientOperationId,
    );
}
