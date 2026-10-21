import { Redirect, router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { ApiError, formatApiError } from '@/api/errors';
import { verifyTwoFactor } from '@/api/mobile';
import { Body, Button, Field, Notice, Screen } from '@/components/ui';
import { useSessionStore } from '@/session/store';

export default function TwoFactorScreen() {
    const challengeToken = useSessionStore((state) => state.challengeToken);
    const establish = useSessionStore((state) => state.establish);
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    if (!challengeToken) {
        return <Redirect href="/login" />;
    }

    return (
        <Screen
            kicker="CAIS Field"
            title="Two-factor code"
            subtitle="Enter the code from your authenticator app, or a recovery code."
            onBack={() => router.back()}
            footer={
                <View className="gap-3">
                    {error ? <Notice tone="error">{error}</Notice> : null}
                    <Button
                        label={busy ? 'Checking…' : 'Continue'}
                        disabled={busy}
                        onPress={() => {
                            void (async () => {
                                setBusy(true);
                                setError(null);

                                try {
                                    const result = await verifyTwoFactor(challengeToken, code.trim());

                                    if ('twoFactor' in result) {
                                        setError('The server asked for another code. Sign in again.');

                                        return;
                                    }

                                    await establish(result.token, result.user);
                                    router.replace('/');
                                } catch (caught) {
                                    setError(caught instanceof ApiError ? formatApiError(caught) : 'That code was not accepted.');
                                } finally {
                                    setBusy(false);
                                }
                            })();
                        }}
                    />
                </View>
            }
        >
            <Body>
                <Field label="Code" value={code} onChangeText={setCode} autoCapitalize="none" placeholder="123456" />
            </Body>
        </Screen>
    );
}
