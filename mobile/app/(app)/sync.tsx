import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Text, View } from 'react-native';

import { isReleaseSyncResult } from '@/api/types';
import { Body, Button, Card, EmptyState, Notice, Screen } from '@/components/ui';
import type { OperationRecord } from '@/db/records';
import { listUserOperations, requeueOperation } from '@/db/repositories';
import { formatWhen, shortReference } from '@/domain/text';
import { shareOfficialReceipt } from '@/evidence/receipt';
import { useSessionStore } from '@/session/store';
import { syncNow } from '@/sync/engine';
import { useSyncStore } from '@/sync/store';

const STATUS_COPY: Record<OperationRecord['status'], string> = {
    pending: 'Waiting',
    syncing: 'Uploading',
    applied: 'Synced',
    conflict: 'Needs attention',
};

export default function SyncScreen() {
    const user = useSessionStore((state) => state.user);
    const online = useSyncStore((state) => state.online);
    const draining = useSyncStore((state) => state.draining);
    const [operations, setOperations] = useState<OperationRecord[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [receiptError, setReceiptError] = useState<string | null>(null);

    const load = useCallback(async () => {
        if (!user) {
            return;
        }

        setOperations(await listUserOperations(user.id));
    }, [user]);

    useFocusEffect(
        useCallback(() => {
            void load();
        }, [load]),
    );

    const waiting = operations.filter((operation) => operation.status === 'pending' || operation.status === 'syncing').length;
    const needsAttention = operations.filter((operation) => operation.status === 'conflict').length;

    return (
        <Screen
            title="Sync queue"
            subtitle={online === false ? 'Offline. Releases stay on this device.' : draining ? 'Uploading one release at a time.' : 'Pending releases upload in the order they were saved.'}
            onBack={() => router.back()}
            footer={
                <Button
                    label={draining ? 'Syncing…' : 'Sync now'}
                    disabled={draining}
                    onPress={() => {
                        void syncNow().finally(load);
                    }}
                />
            }
        >
            <Body>
                {operations.length > 0 ? (
                    <View className="gap-1">
                        <Text className="text-3xl font-semibold text-ink">{waiting}</Text>
                        <Text className="text-lg text-ink/60">waiting to upload</Text>
                        {needsAttention > 0 ? <Text className="text-base font-semibold text-red-800">{needsAttention} need attention</Text> : null}
                    </View>
                ) : null}
                {error ? <Notice tone="error">{error}</Notice> : null}
                {receiptError ? <Notice tone="error">{receiptError}</Notice> : null}
                {operations.length === 0 ? (
                    <EmptyState title="Nothing in the queue" body="Releases and absences you save offline appear here until the server accepts them." />
                ) : (
                    <View className="gap-3">
                        {operations.map((operation) => {
                            const beneficiary = operation.itemName ?? ('beneficiary_name' in operation.payload ? operation.payload.beneficiary_name : 'Beneficiary');
                            const statusInk =
                                operation.status === 'conflict' ? 'text-red-800' : operation.status === 'applied' ? 'text-emerald-800' : 'text-ink/70';

                            return (
                                <Card key={operation.clientOperationId} tone={operation.status === 'applied' ? 'success' : 'default'}>
                                    <View className="gap-1">
                                        <Text className={`text-base font-semibold ${statusInk}`}>
                                            {STATUS_COPY[operation.status]} · {operation.type === 'absent' ? 'Absent' : 'Release'}
                                        </Text>
                                        <Text className="text-xl font-semibold text-ink">{beneficiary}</Text>
                                    </View>
                                    <Text className="text-base text-ink/60">
                                        {operation.batchCode ?? 'Batch'} · Ref {shortReference(operation.clientOperationId)}
                                    </Text>
                                    <Text className="text-base text-ink/60">{formatWhen(operation.capturedAt)}</Text>
                                    {operation.attempts > 0 ? <Text className="text-base text-ink/60">Attempts: {operation.attempts}</Text> : null}
                                    {operation.lastError ? <Notice tone={operation.status === 'conflict' ? 'error' : 'warning'}>{operation.lastError}</Notice> : null}
                                    {isReleaseSyncResult(operation.result) ? (
                                        <Text selectable className="text-lg text-ink">
                                            Transaction {operation.result.transaction_number}
                                        </Text>
                                    ) : null}
                                    {isReleaseSyncResult(operation.result) && operation.result.receipt_number ? (
                                        <View className="gap-1">
                                            <Text selectable className="text-3xl font-semibold text-ink">
                                                {operation.result.receipt_number}
                                            </Text>
                                            <Text className="text-base text-ink/60">Official receipt</Text>
                                        </View>
                                    ) : null}
                                    {operation.result && 'distribution_release_id' in operation.result ? (
                                        <View className="gap-1">
                                            <Text selectable className="text-3xl font-semibold text-ink">
                                                {operation.result.distribution_release_id}
                                            </Text>
                                            <Text className="text-base text-ink/60">Absence record</Text>
                                        </View>
                                    ) : null}
                                    {operation.status === 'conflict' || (operation.status === 'pending' && operation.lastError) ? (
                                        <Button
                                            label="Retry"
                                            variant="secondary"
                                            onPress={() => {
                                                void (async () => {
                                                    setError(null);
                                                    await requeueOperation(operation.clientOperationId);
                                                    await syncNow();
                                                    await load();
                                                })();
                                            }}
                                        />
                                    ) : null}
                                    {isReleaseSyncResult(operation.result) ? (
                                        <Button
                                            label="Open official receipt"
                                            variant="secondary"
                                            onPress={() => {
                                                const result = operation.result;

                                                if (!isReleaseSyncResult(result)) {
                                                    return;
                                                }

                                                void (async () => {
                                                    setReceiptError(null);

                                                    try {
                                                        await shareOfficialReceipt(result.receipt_pdf_url, result.receipt_number);
                                                    } catch (caught) {
                                                        setReceiptError(caught instanceof Error ? caught.message : 'The receipt could not be opened.');
                                                    }
                                                })();
                                            }}
                                        />
                                    ) : null}
                                </Card>
                            );
                        })}
                    </View>
                )}
            </Body>
        </Screen>
    );
}
