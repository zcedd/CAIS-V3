import { Redirect, Stack } from 'expo-router';

import { useSessionStore } from '@/session/store';

export default function AuthLayout() {
    const token = useSessionStore((state) => state.token);

    if (token) {
        return <Redirect href="/" />;
    }

    return <Stack screenOptions={{ headerShown: false }} />;
}
