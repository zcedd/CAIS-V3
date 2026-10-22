import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { Pressable, ScrollView, Text, TextInput, View } from 'react-native';

import { Body, Chip, EmptyState, Meter, Screen, StatusPill } from '@/components/ui';
import type { BatchRecord, ItemRecord, OperationRecord } from '@/db/records';
import { getBatch, listBatchOperations, listItems } from '@/db/repositories';
import { effectiveStatus } from '@/domain/roster';
import type { ItemStatus } from '@/domain/status';

type RosterFilter = 'all' | ItemStatus;
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
    const [filter, setFilter] = useState<RosterFilter>('all');
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

    const activeFilter: RosterFilter = filter !== 'all' && counts[filter as ItemStatus] === 0 ? 'all' : filter;
    const visible = rows.filter((row) => activeFilter === 'all' || row.status === activeFilter);
    const recorded = Math.max(0, items.length - counts.pending - counts.conflict);
    const filterOptions: { id: RosterFilter; label: string }[] = [
        { id: 'all', label: 'All' },
        { id: 'pending', label: `${counts.pending} not started` },
        { id: 'pending_sync', label: `${counts.pending_sync} to sync` },
        { id: 'served', label: `${counts.served} served` },
        { id: 'partial', label: `${counts.partial} partial` },
        { id: 'absent', label: `${counts.absent} absent` },
        { id: 'excluded', label: `${counts.excluded} excluded` },
        { id: 'conflict', label: `${counts.conflict} need attention` },
    ];
    const filters = filterOptions.filter((item) => {
        if (item.id === 'all') {
            return true;
        }

        return counts[item.id] > 0;
    });
    const caption = [
        `${counts.served} served`,
        `${counts.pending} not started`,
        `${counts.pending_sync} to sync`,
        counts.partial > 0 ? `${counts.partial} partial` : null,
        counts.absent > 0 ? `${counts.absent} absent` : null,
        counts.excluded > 0 ? `${counts.excluded} excluded` : null,
        counts.conflict > 0 ? `${counts.conflict} need attention` : null,
        batch?.venue ? batch.venue : null,
    ]
        .filter((part) => part != null && part !== '')
        .join(' · ');

    return (
        <Screen
            title={batch?.title ?? 'Roster'}
            subtitle={batch ? `${batch.batchCode} · ${batch.statusLabel}` : 'Downloaded batch'}
            onBack={() => router.back()}
        >
            <Body>
                {missing ? (
                    <EmptyState title="Batch not on this device" body="Go back and download the batch while you are online." />
                ) : items.length === 0 ? (
                    <EmptyState title="No beneficiaries on this roster" body="This downloaded batch has no one to release to." />
                ) : (
                    <>
                        <Meter value={recorded} total={items.length} caption={caption} />
                        <View className="gap-3">
                            <TextInput
                                value={query}
                                onChangeText={setQuery}
                                placeholder="Search name, CAIS number, or request ID"
                                placeholderTextColor="#78716c"
                                autoCapitalize="none"
                                autoCorrect={false}
                                className="min-h-14 rounded-2xl border border-black/10 bg-white px-4 text-lg text-ink"
                                style={{ borderCurve: 'continuous' }}
                            />
                            <ScrollView horizontal nestedScrollEnabled showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
                                {filters.map((item) => (
                                    <Chip
                                        key={item.id}
                                        label={item.label}
                                        selected={activeFilter === item.id}
                                        onPress={() => setFilter(activeFilter === item.id ? 'all' : item.id)}
                                    />
                                ))}
                            </ScrollView>
                            {visible.length === 0 ? (
                                <EmptyState
                                    title="No matching beneficiaries"
                                    body={
                                        activeFilter === 'all'
                                            ? 'Try another name, CAIS number, or assistance request ID.'
                                            : 'No one in this view matches. Try another filter or clear the search.'
                                    }
                                />
                            ) : (
                                <View className="overflow-hidden rounded-3xl bg-white" style={{ borderCurve: 'continuous' }}>
                                    {visible.map(({ item, status }, index) => (
                                        <Pressable
                                            key={item.id}
                                            accessibilityRole="button"
                                            android_ripple={{ color: 'rgba(67, 56, 202, 0.08)' }}
                                            onPress={() => router.push(`/batches/${batchId}/items/${item.id}`)}
                                            className={`min-h-20 justify-center px-5 py-4 active:bg-brand/10 ${index > 0 ? 'border-t border-black/5' : ''}`}
                                        >
                                            <View className="flex-row items-center gap-3">
                                                <View className="flex-1 gap-1">
                                                    <Text className="text-xl font-semibold text-ink">{item.name}</Text>
                                                    <Text className="text-base text-ink/60">
                                                        {item.caisNumber ? `CAIS ${item.caisNumber} · ` : ''}Request {item.assistanceId}
                                                    </Text>
                                                </View>
                                                <StatusPill status={status} />
                                            </View>
                                        </Pressable>
                                    ))}
                                </View>
                            )}
                        </View>
                    </>
                )}
            </Body>
        </Screen>
    );
}
