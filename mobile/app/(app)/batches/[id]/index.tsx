import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { Pressable, Text, TextInput, View } from 'react-native';

import { Body, EmptyState, Screen, StatusPill } from '@/components/ui';
import type { BatchRecord, ItemRecord, OperationRecord } from '@/db/records';
import { getBatch, listBatchOperations, listItems } from '@/db/repositories';
import { effectiveStatus } from '@/domain/roster';
import type { ItemStatus } from '@/domain/status';
import { useSessionStore } from '@/session/store';

function routeParam(value: string | string[] | undefined): string {
    return Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
}

function matches(item: ItemRecord, query: string): boolean {
    if (query === '') {
        return true;
    }

    const haystack = `${item.name} ${item.caisNumber ?? ''} ${item.assistanceId}`.toLowerCase();

    return haystack.includes(query);
}

export default function RosterScreen() {
    const params = useLocalSearchParams<{ id: string }>();
    const batchId = Number(routeParam(params.id));
    const user = useSessionStore((state) => state.user);
    const [batch, setBatch] = useState<BatchRecord | null>(null);
    const [items, setItems] = useState<ItemRecord[]>([]);
    const [operations, setOperations] = useState<OperationRecord[]>([]);
    const [query, setQuery] = useState('');
    const [missing, setMissing] = useState(false);

    const load = useCallback(async () => {
        if (!user || !Number.isFinite(batchId)) {
            setMissing(true);

            return;
        }

        const [nextBatch, nextItems, nextOperations] = await Promise.all([
            getBatch(user.id, batchId),
            listItems(batchId),
            listBatchOperations(batchId),
        ]);
        setBatch(nextBatch);
        setItems(nextItems);
        setOperations(nextOperations);
        setMissing(!nextBatch);
    }, [batchId, user]);

    useFocusEffect(
        useCallback(() => {
            void load();
        }, [load]),
    );

    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return items
            .map((item) => ({
                item,
                status: effectiveStatus(
                    item,
                    operations.filter((operation) => operation.itemId === item.id),
                ),
            }))
            .filter((row) => matches(row.item, needle));
    }, [items, operations, query]);

    const counts = useMemo(() => {
        const tally = {
            pending: 0,
            pending_sync: 0,
            served: 0,
            partial: 0,
            absent: 0,
            excluded: 0,
            conflict: 0,
        } satisfies Record<ItemStatus, number>;

        for (const item of items) {
            const status = effectiveStatus(
                item,
                operations.filter((operation) => operation.itemId === item.id),
            );
            tally[status] += 1;
        }

        return tally;
    }, [items, operations]);

    return (
        <Screen
            title={batch?.title ?? 'Roster'}
            subtitle={batch ? `${batch.batchCode} · ${batch.statusLabel}` : 'Downloaded batch'}
            onBack={() => router.back()}
        >
            <Body>
                {missing ? (
                    <EmptyState title="Batch not on this device" body="Go back and download the batch while you are online." />
                ) : (
                    <>
                        <View className="rounded-2xl bg-white p-4">
                            <Text className="text-lg font-semibold text-stone-900">
                                {counts.served} served · {counts.partial} partial · {counts.pending} not started
                            </Text>
                            <Text className="mt-1 text-base text-stone-600">
                                {counts.pending_sync} waiting to sync · {counts.absent} absent · {counts.conflict} need attention · {counts.excluded} excluded
                            </Text>
                            {batch?.venue ? <Text className="mt-2 text-base text-stone-700">{batch.venue}</Text> : null}
                        </View>
                        <TextInput
                            value={query}
                            onChangeText={setQuery}
                            placeholder="Search name, CAIS number, or request ID"
                            placeholderTextColor="#78716c"
                            autoCapitalize="none"
                            autoCorrect={false}
                            className="min-h-14 rounded-xl border border-stone-300 bg-white px-4 text-lg text-stone-900"
                        />
                        {rows.length === 0 ? (
                            <EmptyState title="No matching beneficiaries" body="Try another name, CAIS number, or assistance request ID." />
                        ) : (
                            rows.map(({ item, status }) => (
                                <Pressable
                                    key={item.id}
                                    accessibilityRole="button"
                                    onPress={() => router.push(`/batches/${batchId}/items/${item.id}`)}
                                    className="min-h-20 justify-center gap-2 rounded-2xl bg-white p-4"
                                >
                                    <View className="flex-row items-center justify-between gap-3">
                                        <Text className="flex-1 text-xl font-semibold text-stone-900">{item.name}</Text>
                                        <StatusPill status={status} />
                                    </View>
                                    <Text className="text-base text-stone-600">
                                        {item.caisNumber ? `CAIS ${item.caisNumber} · ` : ''}Request {item.assistanceId}
                                    </Text>
                                </Pressable>
                            ))
                        )}
                    </>
                )}
            </Body>
        </Screen>
    );
}
