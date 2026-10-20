import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Text } from 'react-native';

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

    return (
        <Screen title="Sync queue" subtitle={online === false ? 'Offline. Releases stay on this device.' : draining ? 'Uploading one release at a time.' : 'Pending releases upload in the order they were saved.'} onBack={() => router.back()}>
            <Body>
                <Button
                    label={draining ? 'Syncing…' : 'Sync now'}
                    disabled={draining}
                    onPress={() => {
                        void syncNow().finally(load);
                    }}
                />
                {error ? <Notice tone="error">{error}</Notice> : null}
                {receiptError ? <Notice tone="error">{receiptError}</Notice> : null}
                {operations.length === 0 ? (
                    <EmptyState title="Nothing in the queue" body="Releases and absences you save offline appear here until the server accepts them." />
                ) : (
                    operations.map((operation) => {
                        const beneficiary = operation.itemName ?? ('beneficiary_name' in operation.payload ? operation.payload.beneficiary_name : 'Beneficiary');

                        return (
                            <Card key={operation.clientOperationId}>
                                <Text className="text-lg font-semibold text-stone-900">
                                    {STATUS_COPY[operation.status]} · {operation.type === 'absent' ? 'Absent' : 'Release'}
                                </Text>
                                <Text className="text-base text-stone-800">{beneficiary}</Text>
                                <Text className="text-base text-stone-600">
                                    {operation.batchCode ?? 'Batch'} · Ref {shortReference(operation.clientOperationId)}
                                </Text>
                                <Text className="text-base text-stone-600">{formatWhen(operation.capturedAt)}</Text>
                                {operation.attempts > 0 ? (
                                    <Text className="text-base text-stone-600">Attempts: {operation.attempts}</Text>
                                ) : null}
                                {operation.lastError ? <Notice tone={operation.status === 'conflict' ? 'error' : 'warning'}>{operation.lastError}</Notice> : null}
                                {isReleaseSyncResult(operation.result) ? (
                                    <Text className="text-base text-stone-800">Transaction {operation.result.transaction_number}</Text>
                                ) : null}
                                {isReleaseSyncResult(operation.result) && operation.result.receipt_number ? (
                                    <Text className="text-base text-stone-800">Receipt {operation.result.receipt_number}</Text>
                                ) : null}
                                {operation.result && 'distribution_release_id' in operation.result ? (
                                    <Text className="text-base text-stone-800">Absence record {operation.result.distribution_release_id}</Text>
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
                    })
                )}
            </Body>
        </Screen>
    );
}
