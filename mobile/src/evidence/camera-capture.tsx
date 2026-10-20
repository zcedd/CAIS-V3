import { CameraView, useCameraPermissions } from 'expo-camera';
import { useRef, useState } from 'react';
import { Text, View } from 'react-native';

import { Button, Notice } from '@/components/ui';

export function CameraCapture({ onCapture }: { onCapture: (uri: string) => void }) {
    const camera = useRef<CameraView>(null);
    const [permission, requestPermission] = useCameraPermissions();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!permission) {
        return <Text className="text-base text-stone-700">Checking the camera…</Text>;
    }

    if (!permission.granted) {
        return (
            <View className="gap-3">
                <Notice>A live photo has to come from the camera. Gallery photos are not accepted.</Notice>
                <Button label="Allow camera" onPress={() => void requestPermission()} />
            </View>
        );
    }

    return (
        <View className="gap-3">
            <View className="h-80 overflow-hidden rounded-2xl bg-black">
                <CameraView ref={camera} facing="back" style={{ flex: 1 }} />
            </View>
            {error ? <Notice tone="error">{error}</Notice> : null}
            <Button
                label={busy ? 'Taking photo…' : 'Take live photo'}
                disabled={busy}
                onPress={() => {
                    void (async () => {
                        setBusy(true);
                        setError(null);

                        try {
                            const picture = await camera.current?.takePictureAsync({ quality: 0.7 });

                            if (!picture?.uri) {
                                setError('The camera did not return a photo. Try again.');

                                return;
                            }

                            onCapture(picture.uri);
                        } catch {
                            setError('The camera could not take a photo.');
                        } finally {
                            setBusy(false);
                        }
                    })();
                }}
            />
        </View>
    );
}
