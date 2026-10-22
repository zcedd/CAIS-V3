import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, Text, View } from 'react-native';

import { Button, Screen } from '@/components/ui';
import { useEvidenceDraft } from '@/evidence/draft';
import { SignaturePad } from '@/evidence/signature-pad';

export default function SignatureScreen() {
    const signatureData = useEvidenceDraft((state) => state.signatureData);
    const setSignatureData = useEvidenceDraft((state) => state.setSignatureData);
    const [pending, setPending] = useState<string | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const preview = previewOpen ? (pending ?? signatureData) : null;
    const canPreview = pending != null || signatureData != null;

    return (
        <Screen
            compact
            title={preview ? 'Signature preview' : 'Sign'}
            subtitle={
                preview
                    ? pending
                        ? 'Check the signature. Use it to keep this one.'
                        : 'This is the signature already saved for this release.'
                    : 'Sign in the box, then tap Save signature.'
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
                                label="Use this signature"
                                onPress={() => {
                                    setSignatureData(pending);
                                    router.back();
                                }}
                            />
                        ) : null}
                        <Button
                            label={pending ? 'Sign again' : 'Back to signature'}
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
                    accessibilityLabel="Signature preview"
                    style={{ flex: 1, backgroundColor: '#ffffff' }}
                />
            ) : (
                <View className="flex-1 bg-white">
                    <SignaturePad
                        value={signatureData}
                        onSave={(dataUrl) => {
                            setPending(dataUrl);
                            setPreviewOpen(true);
                        }}
                        onClear={() => {
                            setPending(null);
                            setSignatureData(null);
                        }}
                    />
                </View>
            )}
        </Screen>
    );
}
