import type { ItemRecord, OperationRecord } from '@/db/records';
import { serverStatus, type ItemStatus } from '@/domain/status';

export function effectiveStatus(item: ItemRecord, operations: OperationRecord[]): ItemStatus {
    const server = serverStatus(item.approvalStatus, item.releaseStatus);

    if (operations.some((operation) => operation.status === 'pending' || operation.status === 'syncing')) {
        return 'pending_sync';
    }

    if (operations.some((operation) => operation.status === 'conflict')) {
        return 'conflict';
    }

    if (server !== 'pending') {
        return server;
    }

    const applied = [...operations].reverse().find((operation) => operation.status === 'applied');

    if (!applied) {
        return server;
    }

    if (applied.type === 'absent') {
        return 'absent';
    }

    if ('partial' in applied.payload && applied.payload.partial) {
        return 'partial';
    }

    return 'served';
}

export function releaseBlockReason(status: ItemStatus, canExecute: boolean): string | null {
    if (!canExecute) {
        return 'You cannot record releases for this batch.';
    }

    if (status === 'pending_sync') {
        return 'This beneficiary already has a release waiting to sync.';
    }

    if (status === 'conflict') {
        return 'Resolve the sync conflict before recording another release.';
    }

    if (status === 'served') {
        return 'This beneficiary is already served.';
    }

    if (status === 'absent') {
        return 'This beneficiary is already marked absent.';
    }

    if (status === 'excluded') {
        return 'This beneficiary is excluded from the release.';
    }

    return null;
}
