import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, Text, View } from 'react-native';

import { Button, Screen } from '@/components/ui';
import { CameraCapture } from '@/evidence/camera-capture';
import { useEvidenceDraft } from '@/evidence/draft';

export default function CameraScreen() {
    const photoUri = useEvidenceDraft((state) => state.photoUri);
    const setPhotoUri = useEvidenceDraft((state) => state.setPhotoUri);
    const [pending, setPending] = useState<string | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const preview = previewOpen ? (pending ?? photoUri) : null;
    const canPreview = pending != null || photoUri != null;

    return (
        <Screen
            compact
            title={preview ? 'Photo preview' : 'Live photo'}
            subtitle={
                preview
                    ? pending
                        ? 'Check the photo. Use it to keep this shot.'
                        : 'This is the photo already saved for this release.'
                    : 'Point the camera, then take the photo.'
            }
            onBack={() => {
                if (previewOpen) {
                    setPreviewOpen(false);

                    return;
                }

                router.back();
            }}
            action={
                !previewOpen && canPreview ? (
                    <Pressable
                        accessibilityRole="button"
                        onPress={() => setPreviewOpen(true)}
                        className="min-h-11 justify-center active:opacity-70"
                    >
                        <Text className="text-base font-semibold text-brand">Preview</Text>
                    </Pressable>
                ) : undefined
            }
            footer={
                preview ? (
                    <View className="gap-3">
                        {pending ? (
                            <Button
                                label="Use this photo"
                                onPress={() => {
                                    setPhotoUri(pending);
                                    router.back();
                                }}
                            />
                        ) : null}
                        <Button
                            label={pending ? 'Retake' : 'Back to camera'}
                            variant="secondary"
                            onPress={() => {
                                setPending(null);
                                setPreviewOpen(false);
                            }}
                        />
                    </View>
                ) : undefined
            }
        >
            {preview ? (
                <Image
                    source={{ uri: preview }}
                    contentFit="contain"
                    accessibilityLabel="Photo preview"
                    style={{ flex: 1, backgroundColor: '#1C1917' }}
                />
            ) : (
                <View className="flex-1 px-6 pb-4">
                    <CameraCapture
                        onCapture={(uri) => {
                            setPending(uri);
                            setPreviewOpen(true);
                        }}
                    />
                </View>
            )}
        </Screen>
    );
}
