import { Redirect, Stack } from 'expo-router';

import { useSessionStore } from '@/session/store';

export default function AppLayout() {
    const token = useSessionStore((state) => state.token);

    if (!token) {
        return <Redirect href="/login" />;
    }

    return <Stack screenOptions={{ headerShown: false }} />;
}
