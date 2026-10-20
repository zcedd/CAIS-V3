import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Pressable, Text, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { fetchDistributions, fetchPackage } from '@/api/mobile';
import type { RemoteBatch } from '@/api/types';
import { Body, Button, Card, EmptyState, Notice, Screen } from '@/components/ui';
import type { BatchRecord } from '@/db/records';
import { listBatches, savePackage } from '@/db/repositories';
import { formatWhen } from '@/domain/text';
import { useSessionStore } from '@/session/store';
import { syncNow } from '@/sync/engine';
import { useSyncStore } from '@/sync/store';

function connectionLabel(online: boolean | null, draining: boolean): string {
    if (draining) {
        return 'Syncing';
    }

    if (online === null) {
        return 'Checking connection';
    }

    return online ? 'Online' : 'Offline';
}

export default function BatchListScreen() {
    const user = useSessionStore((state) => state.user);
    const online = useSyncStore((state) => state.online);
    const draining = useSyncStore((state) => state.draining);
    const pendingCount = useSyncStore((state) => state.pendingCount);
    const conflictCount = useSyncStore((state) => state.conflictCount);
    const [downloaded, setDownloaded] = useState<BatchRecord[]>([]);
    const [remote, setRemote] = useState<RemoteBatch[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const load = useCallback(async () => {
        if (!user) {
            return;
        }

        const local = await listBatches(user.id);
        setDownloaded(local);

        if (online === false) {
            setRemote([]);
            setError(null);

            return;
        }

        try {
            setRemote(await fetchDistributions());
            setError(null);
        } catch (caught) {
            setRemote([]);
            setError(caught instanceof ApiError ? caught.message : 'The batch list could not be downloaded.');
        }
    }, [online, user]);

    useFocusEffect(
        useCallback(() => {
            void load();
        }, [load]),
    );

    const downloadedIds = new Set(downloaded.map((batch) => batch.id));
    const available = remote.filter((batch) => !downloadedIds.has(batch.id));

    async function download(batchId: number): Promise<void> {
        if (!user) {
            return;
        }

        setBusyId(batchId);
        setError(null);

        try {
            const offlinePackage = await fetchPackage(batchId);
            await savePackage(user.id, offlinePackage);
            await load();
        } catch (caught) {
            setError(caught instanceof ApiError ? caught.message : 'The batch could not be saved on this device.');
        } finally {
            setBusyId(null);
        }
    }

    return (
        <Screen title="Distributions" subtitle={user ? user.name : 'Field officer'}>
            <Body>
                <Pressable accessibilityRole="button" className="rounded-2xl bg-stone-900 px-4 py-4" onPress={() => router.push('/sync')}>
                    <Text className="text-lg font-semibold text-white">
                        {connectionLabel(online, draining)}
                        {pendingCount > 0 ? ` · ${pendingCount} to sync` : ''}
                        {conflictCount > 0 ? ` · ${conflictCount} need attention` : ''}
                    </Text>
                    <Text className="mt-1 text-base text-stone-300">Open the sync queue</Text>
                </Pressable>
                <View className="flex-row gap-3">
                    <View className="flex-1">
                        <Button label="Sync now" onPress={() => void syncNow().then(load)} />
                    </View>
                    <View className="flex-1">
                        <Button label="Settings" variant="secondary" onPress={() => router.push('/settings')} />
                    </View>
                </View>
                {error ? <Notice tone="error">{error}</Notice> : null}
                <Text className="text-xl font-semibold text-stone-900">On this device</Text>
                {downloaded.length === 0 ? (
                    <EmptyState title="No batches downloaded" body="Download a batch while you are online. You can record releases after the signal drops." />
                ) : (
                    downloaded.map((batch) => (
                        <Card key={batch.id}>
                            <Text className="text-xl font-semibold text-stone-900">{batch.title}</Text>
                            <Text className="text-base text-stone-600">
                                {batch.batchCode}
                                {batch.venue ? ` · ${batch.venue}` : ''}
                            </Text>
                            <Text className="text-base text-stone-700">
                                {batch.statusLabel} · {batch.itemCount} beneficiaries
                                {batch.pendingCount > 0 ? ` · ${batch.pendingCount} waiting to sync` : ''}
                            </Text>
                            <Button label="Open roster" onPress={() => router.push(`/batches/${batch.id}`)} />
                            <Button
                                label={busyId === batch.id ? 'Refreshing…' : 'Refresh from server'}
                                variant="secondary"
                                disabled={busyId !== null || online === false}
                                onPress={() => void download(batch.id)}
                            />
                        </Card>
                    ))
                )}
                <Text className="text-xl font-semibold text-stone-900">Available to download</Text>
                {online === false ? (
                    <EmptyState title="You are offline" body="Downloaded batches above are still available. Connect to see batches you have not saved yet." />
                ) : available.length === 0 ? (
                    <EmptyState title="Nothing new to download" body="Assigned scheduled and in-progress batches will show up here." />
                ) : (
                    available.map((batch) => (
                        <Card key={batch.id}>
                            <Text className="text-xl font-semibold text-stone-900">{batch.title}</Text>
                            <Text className="text-base text-stone-600">
                                {batch.batch_code} · {batch.status_label}
                                {batch.venue ? ` · ${batch.venue}` : ''}
                            </Text>
                            {batch.program ? <Text className="text-base text-stone-700">{batch.program}</Text> : null}
                            {batch.scheduled_at ? <Text className="text-base text-stone-700">{formatWhen(batch.scheduled_at)}</Text> : null}
                            <Button
                                label={busyId === batch.id ? 'Downloading…' : 'Download for offline use'}
                                disabled={busyId !== null}
                                onPress={() => void download(batch.id)}
                            />
                        </Card>
                    ))
                )}
            </Body>
        </Screen>
    );
}
