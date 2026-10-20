import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect, useState } from 'react';
import { Text, View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { useSessionStore } from '@/session/store';
import { startSyncEngine } from '@/sync/engine';
import '../global.css';

SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
    const ready = useSessionStore((state) => state.ready);
    const token = useSessionStore((state) => state.token);
    const hydrate = useSessionStore((state) => state.hydrate);
    const [booted, setBooted] = useState(false);

    useEffect(() => {
        void hydrate().finally(() => setBooted(true));
    }, [hydrate]);

    useEffect(() => {
        if (!booted || !token) {
            return;
        }

        return startSyncEngine();
    }, [booted, token]);

    useEffect(() => {
        if (booted) {
            void SplashScreen.hideAsync();
        }
    }, [booted]);

    if (!booted || !ready) {
        return (
            <View className="flex-1 items-center justify-center bg-stone-100">
                <Text className="text-lg text-stone-700">Opening CAIS Field…</Text>
            </View>
        );
    }

    return (
        <GestureHandlerRootView style={{ flex: 1 }}>
            <SafeAreaProvider>
                <Stack screenOptions={{ headerShown: false }} />
            </SafeAreaProvider>
        </GestureHandlerRootView>
    );
}
