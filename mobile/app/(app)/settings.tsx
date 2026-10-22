import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Text, View } from 'react-native';

import { Body, Button, Card, EmptyState, Field, Notice, Screen, SectionTitle } from '@/components/ui';
import type { BatchRecord } from '@/db/records';
import { countUnsynced, listBatches, removeBatch } from '@/db/repositories';
import { DEFAULT_API_URL, getApiBaseUrl, setApiBaseUrl } from '@/db/settings';
import { deleteEvidence } from '@/evidence/files';
import { useSessionStore } from '@/session/store';

export default function SettingsScreen() {
    const user = useSessionStore((state) => state.user);
    const department = useSessionStore((state) => state.department);
    const signOut = useSessionStore((state) => state.signOut);
    const [apiUrl, setApiUrl] = useState(DEFAULT_API_URL);
    const [savedUrl, setSavedUrl] = useState(DEFAULT_API_URL);
    const [batches, setBatches] = useState<BatchRecord[]>([]);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        const current = await getApiBaseUrl();
        setApiUrl(current);
        setSavedUrl(current);

        if (user) {
            setBatches(await listBatches(user.id));
        }
    }, [user]);

    useFocusEffect(
        useCallback(() => {
            void load();
        }, [load]),
    );

    function confirmSignOut(): void {
        void (async () => {
            const unsynced = user ? await countUnsynced(user.id) : 0;

            if (unsynced === 0) {
                await signOut();
                router.replace('/login');

                return;
            }

            Alert.alert(
                'Releases are still on this device',
                `${unsynced} release${unsynced === 1 ? '' : 's'} have not been accepted by the server. They stay on this device and upload only after this same account signs in again.`,
                [
                    { text: 'Stay signed in', style: 'cancel' },
                    {
                        text: 'Sign out',
                        style: 'destructive',
                        onPress: () => {
                            void signOut().then(() => router.replace('/login'));
                        },
                    },
                ],
            );
        })();
    }

    function confirmRemove(batch: BatchRecord): void {
        Alert.alert('Remove downloaded batch', `Remove ${batch.title} from this device?`, [
            { text: 'Keep', style: 'cancel' },
            {
                text: 'Remove',
                style: 'destructive',
                onPress: () => {
                    void (async () => {
                        const result = await removeBatch(batch.id);

                        if (!result.ok) {
                            setError(result.reason);

                            return;
                        }

                        for (const file of result.files) {
                            deleteEvidence(file.live_photo_path);
                            deleteEvidence(file.signature_path);
                        }

                        setError(null);
                        setMessage(`${batch.batchCode} was removed from this device.`);
                        await load();
                    })();
                },
            },
        ]);
    }

    return (
        <Screen title="Settings" subtitle="Account and this device" onBack={() => router.back()}>
            <Body>
                <Card>
                    <View className="gap-1">
                        <Text className="text-xl font-semibold text-ink">{user?.name ?? 'Officer'}</Text>
                        <Text className="text-lg text-ink/80">{user?.email}</Text>
                        <Text className="text-base text-ink/60">{department?.name ?? 'Department not loaded yet'}</Text>
                    </View>
                </Card>
                <View className="gap-4">
                    <SectionTitle>Server</SectionTitle>
                    <Field
                        label="API base URL"
                        value={apiUrl}
                        onChangeText={setApiUrl}
                        autoCapitalize="none"
                        placeholder="http://10.0.2.2:8000"
                    />
                    <Text className="text-base text-ink/60">
                        Current: {savedUrl}. Android emulator reaches the computer at 10.0.2.2. Leave this as the example value to use EXPO_PUBLIC_API_URL.
                    </Text>
                    <Button
                        label="Save server address"
                        onPress={() => {
                            void (async () => {
                                await setApiBaseUrl(apiUrl);
                                const current = await getApiBaseUrl();
                                setSavedUrl(current);
                                setApiUrl(current);
                                setMessage('Server address saved on this device.');
                                setError(null);
                            })();
                        }}
                    />
                </View>
                {message ? <Notice>{message}</Notice> : null}
                {error ? <Notice tone="error">{error}</Notice> : null}
                <View className="gap-3">
                    <SectionTitle>Downloaded batches</SectionTitle>
                    {batches.length === 0 ? (
                        <EmptyState title="No downloaded batches" body="Batches you download appear here so you can remove them from the device." />
                    ) : (
                        batches.map((batch) => (
                            <Card key={batch.id}>
                                <View className="gap-1">
                                    <Text className="text-xl font-semibold text-ink">{batch.title}</Text>
                                    <Text className="text-base text-ink/60">{batch.batchCode}</Text>
                                </View>
                                <Button label="Remove from device" variant="danger" onPress={() => confirmRemove(batch)} />
                            </Card>
                        ))
                    )}
                </View>
                <Button label="Sign out" variant="secondary" onPress={confirmSignOut} />
            </Body>
        </Screen>
    );
}
