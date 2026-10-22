export const ITEM_STATUSES = [
    'pending',
    'pending_sync',
    'served',
    'partial',
    'absent',
    'excluded',
    'conflict',
] as const;

export type ItemStatus = (typeof ITEM_STATUSES)[number];

const LABELS: Record<ItemStatus, string> = {
    pending: 'Not started',
    pending_sync: 'Waiting to sync',
    served: 'Served',
    partial: 'Partial',
    absent: 'Absent',
    excluded: 'Excluded',
    conflict: 'Needs attention',
};

export function statusLabel(status: ItemStatus): string {
    return LABELS[status];
}

export function serverStatus(approvalStatus: string, releaseStatus: string): ItemStatus {
    const release = releaseStatus.trim().toLowerCase();

    if (
        release === 'served' ||
        release === 'partial' ||
        release === 'absent' ||
        release === 'excluded'
    ) {
        return release;
    }

    const approval = approvalStatus.trim().toLowerCase();

    if (approval !== '' && approval !== 'approved') {
        return 'excluded';
    }

    return 'pending';
}

export function isLockedStatus(status: ItemStatus): boolean {
    return status === 'served' || status === 'absent' || status === 'excluded';
}
