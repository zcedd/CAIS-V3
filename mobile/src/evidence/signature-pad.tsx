import { useState } from 'react';
import { Text, View } from 'react-native';
import SignatureCanvas from 'react-native-signature-canvas';

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

export function SignaturePad({
    value,
    onSave,
    onClear,
}: {
    value: string | null;
    onSave: (dataUrl: string) => void;
    onClear: () => void;
}) {
    const [empty, setEmpty] = useState(false);
    const [height, setHeight] = useState(0);

    return (
        <View className="flex-1 bg-white">
            {empty ? <Text className="px-6 pt-3 text-base text-red-800">Sign in the box before you save.</Text> : null}
            <View
                className="flex-1"
                onLayout={(event) => {
                    const next = Math.round(event.nativeEvent.layout.height);

                    setHeight((current) => (Math.abs(current - next) < 2 ? current : next));
                }}
            >
                {height > 0 ? (
                    <SignatureCanvas
                        dataURL={value ?? ''}
                        onOK={(dataUrl) => {
                            setEmpty(false);
                            onSave(dataUrl);
                        }}
                        onEmpty={() => setEmpty(true)}
                        onClear={() => {
                            setEmpty(false);
                            onClear();
                        }}
                        descriptionText=""
                        clearText="Clear"
                        confirmText="Save signature"
                        imageType="image/png"
                        penColor="#1c1917"
                        backgroundColor="rgb(255, 255, 255)"
                        webStyle={WEB_STYLE}
                        androidHardwareAccelerationDisabled
                        androidLayerType="software"
                        style={{ height, width: '100%' }}
                    />
                ) : null}
            </View>
        </View>
    );
}
