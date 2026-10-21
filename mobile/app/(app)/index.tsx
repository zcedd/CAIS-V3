import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Pressable, Text, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { fetchDistributions, fetchPackage } from '@/api/mobile';
import type { RemoteBatch } from '@/api/types';
import { Body, Button, Card, EmptyState, Notice, Screen, SectionTitle } from '@/components/ui';
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
        <Screen
            title="Distributions"
            subtitle={user ? user.name : 'Field officer'}
            action={
                <Pressable accessibilityRole="button" onPress={() => router.push('/settings')} className="min-h-11 justify-center active:opacity-70">
                    <Text className="text-base font-semibold text-brand">Settings</Text>
                </Pressable>
            }
        >
            <Body>
                <View className="gap-3">
                    <Pressable
                        accessibilityRole="button"
                        android_ripple={{ color: 'rgba(67, 56, 202, 0.08)' }}
                        onPress={() => router.push('/sync')}
                        className={`flex-row items-center gap-4 rounded-3xl px-5 py-4 active:opacity-90 ${conflictCount > 0 ? 'bg-red-50' : 'bg-white'}`}
                        style={{
                            borderCurve: 'continuous',
                            shadowColor: '#1C1917',
                            shadowOffset: { width: 0, height: 6 },
                            shadowOpacity: 0.05,
                            shadowRadius: 12,
                            elevation: 1,
                        }}
                    >
                        <View
                            className={`h-3 w-3 rounded-full ${
                                conflictCount > 0 ? 'bg-red-600' : draining || online === null ? 'bg-amber-500' : online ? 'bg-emerald-600' : 'bg-stone-400'
                            }`}
                        />
                        <View className="flex-1 gap-1">
                            <Text className="text-lg font-semibold text-ink">{connectionLabel(online, draining)}</Text>
                            <Text className={`text-base ${conflictCount > 0 ? 'font-semibold text-red-800' : pendingCount > 0 ? 'text-amber-800' : 'text-ink/60'}`}>
                                {conflictCount > 0
                                    ? `${conflictCount} need attention`
                                    : pendingCount > 0
                                      ? `${pendingCount} waiting to sync`
                                      : 'Sync queue is clear'}
                            </Text>
                        </View>
                        <Text className="text-base font-semibold text-brand">Open</Text>
                    </Pressable>
                    <Button
                        label={draining ? 'Syncing…' : 'Sync now'}
                        variant="secondary"
                        disabled={draining}
                        onPress={() => void syncNow().then(load)}
                    />
                </View>
                {error ? <Notice tone="error">{error}</Notice> : null}
                <View className="gap-3">
                    <SectionTitle>On this device</SectionTitle>
                    {downloaded.length === 0 ? (
                        <EmptyState title="No batches downloaded" body="Download a batch while you are online. You can record releases after the signal drops." />
                    ) : (
                        downloaded.map((batch) => (
                            <Card key={batch.id}>
                                <View className="flex-row items-start justify-between gap-4">
                                    <View className="flex-1 gap-1">
                                        <Text className="text-xl font-semibold text-ink">{batch.title}</Text>
                                        <Text className="text-base text-ink/60">
                                            {batch.batchCode}
                                            {batch.venue ? ` · ${batch.venue}` : ''}
                                        </Text>
                                    </View>
                                    <View className="items-end gap-1">
                                        <Text className="text-3xl font-semibold text-ink">{batch.itemCount}</Text>
                                        <Text className="text-base text-ink/60">on roster</Text>
                                    </View>
                                </View>
                                <Text className="text-lg text-ink/80">{batch.statusLabel}</Text>
                                {batch.program ? <Text className="text-base text-ink/60">{batch.program}</Text> : null}
                                {batch.pendingCount > 0 ? (
                                    <Text className="text-base font-semibold text-amber-800">{batch.pendingCount} waiting to sync</Text>
                                ) : null}
                                {batch.conflictCount > 0 ? (
                                    <Text className="text-base font-semibold text-red-800">{batch.conflictCount} need attention</Text>
                                ) : null}
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
                </View>
                <View className="gap-3">
                    <SectionTitle>Available to download</SectionTitle>
                    {online === false ? (
                        <EmptyState title="You are offline" body="Downloaded batches above are still available. Connect to see batches you have not saved yet." />
                    ) : available.length === 0 ? (
                        <EmptyState title="Nothing new to download" body="Assigned scheduled and in-progress batches will show up here." />
                    ) : (
                        available.map((batch) => (
                            <Card key={batch.id}>
                                <View className="gap-1">
                                    <Text className="text-xl font-semibold text-ink">{batch.title}</Text>
                                    <Text className="text-base text-ink/60">
                                        {batch.batch_code} · {batch.status_label}
                                        {batch.venue ? ` · ${batch.venue}` : ''}
                                    </Text>
                                </View>
                                {batch.program ? <Text className="text-base text-ink/60">{batch.program}</Text> : null}
                                {batch.scheduled_at ? <Text className="text-lg text-ink">{formatWhen(batch.scheduled_at)}</Text> : null}
                                <Button
                                    label={busyId === batch.id ? 'Downloading…' : 'Download for offline use'}
                                    disabled={busyId !== null}
                                    onPress={() => void download(batch.id)}
                                />
                            </Card>
                        ))
                    )}
                </View>
            </Body>
        </Screen>
    );
}
