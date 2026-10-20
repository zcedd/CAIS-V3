import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Text } from 'react-native';

import { ApiError, formatApiError } from '@/api/errors';
import { login } from '@/api/mobile';
import { Body, Button, Card, Field, Notice, Screen } from '@/components/ui';
import { getApiBaseUrl, setApiBaseUrl } from '@/db/settings';
import { useSessionStore } from '@/session/store';

export default function LoginScreen() {
    const establish = useSessionStore((state) => state.establish);
    const setChallenge = useSessionStore((state) => state.setChallenge);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [apiUrl, setApiUrl] = useState('');
    const [apiReady, setApiReady] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        void getApiBaseUrl().then((value) => {
            setApiUrl(value);
            setApiReady(true);
        });
    }, []);

    return (
        <Screen title="CAIS Field" subtitle="Sign in to download a distribution and record releases offline.">
            <Body>
                <Card>
                    <Field
                        label="Email"
                        value={email}
                        onChangeText={setEmail}
                        autoCapitalize="none"
                        keyboardType="email-address"
                        placeholder="name@agency.gov"
                    />
                    <Field
                        label="Password"
                        value={password}
                        onChangeText={setPassword}
                        secureTextEntry
                        autoCapitalize="none"
                    />
                    <Field
                        label="Server address"
                        value={apiUrl}
                        onChangeText={setApiUrl}
                        autoCapitalize="none"
                        placeholder="http://10.0.2.2:8000"
                    />
                    {error ? <Notice tone="error">{error}</Notice> : null}
                    <Button
                        label={busy ? 'Signing in…' : 'Sign in'}
                        disabled={busy || !apiReady}
                        onPress={() => {
                            void (async () => {
                                setBusy(true);
                                setError(null);

                                try {
                                    await setApiBaseUrl(apiUrl);
                                    const result = await login(email.trim(), password);

                                    if ('twoFactor' in result) {
                                        setChallenge(result.challengeToken);
                                        router.push('/two-factor');

                                        return;
                                    }

                                    await establish(result.token, result.user);
                                    router.replace('/');
                                } catch (caught) {
                                    setError(caught instanceof ApiError ? formatApiError(caught) : 'Sign-in failed. Check the email, password, and server address.');
                                } finally {
                                    setBusy(false);
                                }
                            })();
                        }}
                    />
                </Card>
                <Text className="text-base text-stone-600">
                    Use the same account you use on the web. Two-factor codes are entered on the next screen.
                </Text>
            </Body>
        </Screen>
    );
}
