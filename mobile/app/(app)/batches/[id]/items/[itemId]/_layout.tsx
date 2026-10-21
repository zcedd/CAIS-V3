import { Stack, useLocalSearchParams } from 'expo-router';
import { useEffect } from 'react';

import { useEvidenceDraft } from '@/evidence/draft';

function routeParam(value: string | string[] | undefined): string {
    return Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
}

export default function ItemLayout() {
    const params = useLocalSearchParams<{ itemId: string }>();
    const itemId = routeParam(params.itemId);

    useEffect(() => {
        useEvidenceDraft.getState().begin(itemId);

        return () => {
            useEvidenceDraft.getState().end(itemId);
        };
    }, [itemId]);

    return <Stack screenOptions={{ headerShown: false }} />;
}
