import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';
import SignatureCanvas from 'react-native-signature-canvas';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Button } from '@/components/ui';

const WEB_STYLE = `
  .m-signature-pad {
    display: flex;
    flex-direction: column;
    box-shadow: none;
    border: none;
    margin: 0;
    height: 100%;
    background: #fff;
  }
  .m-signature-pad--body {
    flex: 1 1 auto;
    height: auto;
    min-height: 0;
    border: none;
  }
  .m-signature-pad--footer {
    flex: 0 0 64px;
    height: 64px;
    margin: 0;
    padding: 8px 12px;
  }
  .m-signature-pad--footer .description { display: none; }
  .m-signature-pad--footer .button {
    height: 48px;
    line-height: 48px;
    font-size: 16px;
  }
  body, html { height: 100%; background: #fff; }
`;

function Canvas({
    value,
    onSave,
    onClear,
    onEmpty,
    onDrawingChange,
    height,
}: {
    value: string | null;
    onSave: (dataUrl: string) => void;
    onClear: () => void;
    onEmpty: () => void;
    onDrawingChange: (drawing: boolean) => void;
    height?: number;
}) {
    return (
        <SignatureCanvas
            dataURL={value ?? ''}
            onOK={onSave}
            onEmpty={onEmpty}
            onClear={onClear}
            onBegin={() => onDrawingChange(true)}
            onEnd={() => onDrawingChange(false)}
            descriptionText=""
            clearText="Clear"
            confirmText="Save signature"
            imageType="image/png"
            penColor="#1c1917"
            backgroundColor="rgb(255, 255, 255)"
            webStyle={WEB_STYLE}
            nestedScrollEnabled
            androidHardwareAccelerationDisabled
            androidLayerType="software"
            style={height == null ? { flex: 1 } : { height, flex: 0 }}
        />
    );
}

export function SignaturePad({
    value,
    onSave,
    onClear,
    onDrawingChange,
}: {
    value: string | null;
    onSave: (dataUrl: string) => void;
    onClear: () => void;
    onDrawingChange: (drawing: boolean) => void;
}) {
    const [fullScreen, setFullScreen] = useState(false);
    const [empty, setEmpty] = useState(false);

    function openFullScreen(): void {
        onDrawingChange(false);
        setEmpty(false);
        setFullScreen(true);
    }

    function closeFullScreen(): void {
        onDrawingChange(false);
        setFullScreen(false);
    }

    return (
        <View className="gap-2">
            <Text className="text-base text-stone-700">Sign in the box, then tap Save signature.</Text>
            <Button label="Sign full screen" variant="secondary" onPress={openFullScreen} />
            {fullScreen ? (
                <Modal animationType="slide" presentationStyle="fullScreen" statusBarTranslucent visible onRequestClose={closeFullScreen}>
                    <SafeAreaView className="flex-1 bg-white">
                        <View className="flex-row items-center justify-between border-b border-stone-200 px-4 py-3">
                            <View className="flex-1 pr-3">
                                <Text className="text-lg font-semibold text-stone-900">Sign</Text>
                                <Text className="text-base text-stone-600">Tap Save signature before you close.</Text>
                            </View>
                            <Pressable accessibilityRole="button" className="min-h-12 justify-center px-2" onPress={closeFullScreen}>
                                <Text className="text-base font-semibold text-emerald-800">Close</Text>
                            </Pressable>
                        </View>
                        {empty ? <Text className="px-4 pt-3 text-base text-red-700">Sign in the box before you save.</Text> : null}
                        <View
                            className="flex-1"
                            onTouchStart={() => onDrawingChange(true)}
                            onTouchEnd={() => onDrawingChange(false)}
                            onTouchCancel={() => onDrawingChange(false)}
                        >
                            <Canvas
                                value={value}
                                onSave={(dataUrl) => {
                                    setEmpty(false);
                                    onSave(dataUrl);
                                    closeFullScreen();
                                }}
                                onClear={() => {
                                    setEmpty(false);
                                    onClear();
                                }}
                                onEmpty={() => setEmpty(true)}
                                onDrawingChange={onDrawingChange}
                            />
                        </View>
                    </SafeAreaView>
                </Modal>
            ) : (
                <View
                    className="overflow-hidden rounded-2xl border border-stone-300 bg-white"
                    style={{ height: 320 }}
                    onTouchStart={() => onDrawingChange(true)}
                    onTouchEnd={() => onDrawingChange(false)}
                    onTouchCancel={() => onDrawingChange(false)}
                >
                    <Canvas
                        value={value}
                        height={320}
                        onSave={(dataUrl) => {
                            setEmpty(false);
                            onSave(dataUrl);
                        }}
                        onClear={onClear}
                        onEmpty={() => setEmpty(true)}
                        onDrawingChange={onDrawingChange}
                    />
                </View>
            )}
            {empty && !fullScreen ? <Text className="text-base text-red-700">Sign in the box before you save.</Text> : null}
        </View>
    );
}
