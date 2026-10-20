import NetInfo from '@react-native-community/netinfo';
import { AppState } from 'react-native';

import { ApiError, formatApiError, isNetworkError } from '@/api/errors';
import { fetchPackage, postAbsent, postRelease } from '@/api/mobile';
import { countQueue, listReadyOperations, markApplied, markConflict, markRetry, markSyncing, resetInterruptedSync, savePackage } from '@/db/repositories';
import { useSessionStore } from '@/session/store';
import { useSyncStore } from '@/sync/store';

function backoffDelay(attempts: number): number {
    return Math.min(60_000, 1000 * 2 ** Math.min(attempts, 6));
}

async function refreshCounts(): Promise<void> {
    const userId = useSessionStore.getState().user?.id;

    if (!userId) {
        useSyncStore.getState().setCounts(0, 0);

        return;
    }

    const counts = await countQueue(userId);
    useSyncStore.getState().setCounts(counts.pending, counts.conflict);
}

async function reconcile(userId: number, batchIds: number[]): Promise<void> {
    for (const batchId of batchIds) {
        try {
            const offlinePackage = await fetchPackage(batchId);
            await savePackage(userId, offlinePackage);
        } catch {
            // Applied receipts stay on the device. The next sync refreshes the roster.
        }
    }
}

async function runDrain(manual: boolean): Promise<void> {
    const sync = useSyncStore.getState();

    if (!manual && sync.online === false) {
        return;
    }

    const userId = useSessionStore.getState().user?.id;

    if (!userId || !useSessionStore.getState().token) {
        return;
    }

    sync.setDraining(true);
    const touched: number[] = [];

    try {
        await resetInterruptedSync();
        let stopForNetwork = false;

        while (!stopForNetwork && useSessionStore.getState().token) {
            const ready = await listReadyOperations(userId, new Date().toISOString());

            if (ready.length === 0) {
                break;
            }

            for (const operation of ready) {
                if (!useSessionStore.getState().token) {
                    return;
                }

                await markSyncing(operation.clientOperationId);

                try {
                    const result = operation.type === 'absent' ? await postAbsent(operation) : await postRelease(operation);
                    await markApplied(operation.clientOperationId, result);

                    if (!touched.includes(operation.batchId)) {
                        touched.push(operation.batchId);
                    }
                } catch (error) {
                    if (error instanceof ApiError && error.status === 422) {
                        await markConflict(operation.clientOperationId, formatApiError(error));
                    } else if (error instanceof ApiError && error.status === 401) {
                        return;
                    } else {
                        const attempts = operation.attempts + 1;
                        const message = error instanceof ApiError
                            ? formatApiError(error)
                            : error instanceof Error
                              ? error.message
                              : 'The upload failed.';
                        const nextAttemptAt = new Date(Date.now() + backoffDelay(attempts)).toISOString();
                        await markRetry(operation.clientOperationId, message, nextAttemptAt);

                        if (isNetworkError(error)) {
                            stopForNetwork = true;
                            break;
                        }
                    }
                }
            }
        }

        await reconcile(userId, touched);
    } finally {
        useSyncStore.getState().setDraining(false);
        await refreshCounts();
    }
}

let chain: Promise<void> = Promise.resolve();

export function drain(manual = false): Promise<void> {
    const run = chain.then(() => runDrain(manual));
    chain = run.then(
        () => undefined,
        () => undefined,
    );

    return run;
}

export function syncNow(): Promise<void> {
    return drain(true);
}

async function publishConnectivity(connected: boolean | null): Promise<void> {
    if (connected === null) {
        return;
    }

    useSyncStore.getState().setOnline(connected);

    if (connected) {
        await drain(false);
    }
}

export function startSyncEngine(): () => void {
    void refreshCounts();
    const unsubscribe = NetInfo.addEventListener((state) => {
        void publishConnectivity(state.isConnected);
    });
    const subscription = AppState.addEventListener('change', (next) => {
        if (next !== 'active') {
            return;
        }

        void NetInfo.fetch().then((state) => publishConnectivity(state.isConnected));
    });
    void NetInfo.fetch().then((state) => publishConnectivity(state.isConnected));

    return () => {
        unsubscribe();
        subscription.remove();
    };
}
