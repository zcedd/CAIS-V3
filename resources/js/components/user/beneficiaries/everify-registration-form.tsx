import InputError from '@/components/input-error';
import { NameSuffixSelect } from '@/components/name-suffix-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { captureEverifyFingerprint } from '@/lib/everify-fingerprint';
import { startEverifyLiveness } from '@/lib/everify-liveness';
import type {
    EverifyBiometricMethod,
    EverifyFingerprintConfig,
    EverifyIdentityMethod,
    EverifyIntakeValues,
    IndividualFormData,
    NameSuffixOption,
} from '@/types/beneficiary';
import { verifyIndividual } from '@/actions/App/Http/Controllers/User/BeneficiaryController';
import { useHttp } from '@inertiajs/react';
import {
    Fingerprint,
    IdCard,
    Keyboard,
    ScanLine,
    ShieldCheck,
} from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';
import { toast } from 'sonner';

const QrScanner = lazy(() =>
    import('@yudiel/react-qr-scanner').then((module) => ({
        default: module.Scanner,
    })),
);

type WizardStep = 'identity-method' | 'identity' | 'biometric';

type VerifyResponse = {
    verified: boolean;
    result_grade: number | null;
    verification_token: string;
};

function isEverifyBiometricMethod(
    value: string,
): value is EverifyBiometricMethod {
    return value === 'face' || value === 'fingerprint';
}

function configuredBiometrics(
    methods: string[] | undefined,
): EverifyBiometricMethod[] {
    const available = (methods ?? []).filter(isEverifyBiometricMethod);

    return available.length > 0 ? available : ['face', 'fingerprint'];
}

function defaultBiometricMethod(
    available: EverifyBiometricMethod[],
): EverifyBiometricMethod {
    if (available.includes('face')) {
        return 'face';
    }

    return available[0] ?? 'face';
}

const emptyIntake = (
    deviceId: string | null = null,
    biometricMethod: EverifyBiometricMethod = 'face',
): EverifyIntakeValues => ({
    identityMethod: 'query',
    biometricMethod,
    qrValue: '',
    faceSessionId: '',
    fingerprint: null,
    fingerprintDeviceId: deviceId ?? '',
});

function identityIsReady(
    method: EverifyIdentityMethod,
    values: EverifyIntakeValues,
    individual: IndividualFormData,
): boolean {
    if (method === 'query') {
        return (
            individual.first_name.trim() !== '' &&
            individual.last_name.trim() !== '' &&
            individual.birthday !== ''
        );
    }

    return values.qrValue.trim() !== '';
}

function biometricIsReady(values: EverifyIntakeValues): boolean {
    return values.biometricMethod === 'face'
        ? values.faceSessionId !== ''
        : values.fingerprint !== null;
}

export function EverifyRegistrationForm({
    departmentSlug,
    individual,
    onIndividualChange,
    publicKey,
    livenessSdkUrl,
    fingerprintConfig,
    availableBiometrics: availableBiometricsProp,
    suffixOptions,
    onVerified,
}: {
    departmentSlug: string;
    individual: IndividualFormData;
    onIndividualChange: (
        updater: (current: IndividualFormData) => IndividualFormData,
    ) => void;
    publicKey: string | null;
    livenessSdkUrl: string;
    fingerprintConfig: EverifyFingerprintConfig;
    availableBiometrics?: EverifyBiometricMethod[];
    suffixOptions: NameSuffixOption[];
    onVerified: (token: string) => void;
}) {
    const availableBiometrics = configuredBiometrics(availableBiometricsProp);
    const [step, setStep] = useState<WizardStep>('identity-method');
    const [values, setValues] = useState<EverifyIntakeValues>(() =>
        emptyIntake(
            fingerprintConfig.device_id,
            defaultBiometricMethod(availableBiometrics),
        ),
    );
    const valuesRef = useRef(values);
    const individualRef = useRef(individual);
    const [scanningQr, setScanningQr] = useState(false);
    const [checkingFace, setCheckingFace] = useState(false);
    const [capturingFingerprint, setCapturingFingerprint] = useState(false);
    const { post, processing, errors, transform } = useHttp<
        Record<string, unknown>,
        VerifyResponse
    >({});

    valuesRef.current = values;
    individualRef.current = individual;

    const chooseIdentityMethod = (identityMethod: EverifyIdentityMethod) => {
        setScanningQr(false);
        setValues((current) => ({
            ...current,
            identityMethod,
            qrValue: identityMethod === 'query' ? '' : current.qrValue,
        }));
        setStep('identity');
    };

    const startFaceCheck = async () => {
        if (!publicKey) {
            toast.error(
                'Face check is not configured. Ask an administrator to set the eVerify public key.',
            );
            return;
        }

        setCheckingFace(true);

        try {
            const sessionId = await startEverifyLiveness(
                publicKey,
                livenessSdkUrl,
            );
            setValues((current) => ({
                ...current,
                faceSessionId: sessionId,
            }));
            toast.success('Face check completed.');
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'The face check did not finish.',
            );
        } finally {
            setCheckingFace(false);
        }
    };

    const startFingerprintCapture = async () => {
        setCapturingFingerprint(true);

        try {
            const fingerprint = await captureEverifyFingerprint(
                fingerprintConfig,
                values.fingerprintDeviceId,
            );
            setValues((current) => ({
                ...current,
                fingerprint,
            }));
            toast.success('Fingerprint captured.');
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Could not capture the fingerprint.',
            );
        } finally {
            setCapturingFingerprint(false);
        }
    };

    const submitVerification = () => {
        const current = valuesRef.current;
        const person = individualRef.current;

        transform(() => ({
            everify_identity_method: current.identityMethod,
            everify_biometric_method: current.biometricMethod,
            everify_qr_value:
                current.identityMethod === 'query'
                    ? undefined
                    : current.qrValue,
            face_liveness_session_id:
                current.biometricMethod === 'face'
                    ? current.faceSessionId
                    : undefined,
            everify_fingerprint:
                current.biometricMethod === 'fingerprint'
                    ? current.fingerprint
                    : undefined,
            first_name: person.first_name,
            middle_name: person.middle_name,
            last_name: person.last_name,
            suffix: person.suffix,
            birthday: person.birthday,
        }));

        void post(verifyIndividual.url(departmentSlug), {
            onSuccess: (response) => {
                toast.success('PhilSys verified this person.');
                onVerified(response.verification_token);
            },
            onError: (submitErrors) => {
                const message =
                    submitErrors.intake_method ??
                    submitErrors.everify_identity_method ??
                    submitErrors.everify_biometric_method ??
                    submitErrors.face_liveness_session_id ??
                    submitErrors.everify_fingerprint ??
                    submitErrors.everify_qr_value ??
                    submitErrors.birthday ??
                    submitErrors.first_name ??
                    submitErrors.last_name;

                toast.error(
                    typeof message === 'string'
                        ? message
                        : 'PhilSys did not verify this person.',
                );
            },
        });
    };

    return (
        <div className="grid gap-4">
            <Alert>
                <ShieldCheck />
                <AlertTitle>PhilSys eVerify</AlertTitle>
                <AlertDescription>
                    Identify the person, complete a biometric check, then verify
                    with PhilSys. The record is saved only after verification
                    succeeds.
                </AlertDescription>
            </Alert>

            <p className="text-sm text-muted-foreground">
                {step === 'identity-method'
                    ? 'Step 1 of 3 · Choose how to identify'
                    : step === 'identity'
                      ? 'Step 2 of 3 · Enter or scan identity'
                      : 'Step 3 of 3 · Biometrics and verify'}
            </p>

            {step === 'identity-method' ? (
                <div className="grid gap-2">
                    <Label>How will you identify this person?</Label>
                    <ToggleGroup
                        type="single"
                        value=""
                        onValueChange={(value) => {
                            if (
                                value === 'query' ||
                                value === 'qr' ||
                                value === 'pcn'
                            ) {
                                chooseIdentityMethod(value);
                            }
                        }}
                        variant="outline"
                        orientation="vertical"
                        spacing={2}
                        className="w-full"
                    >
                        <ToggleGroupItem
                            value="query"
                            className="h-auto w-full justify-start gap-3 px-3 py-3 text-left"
                        >
                            <Keyboard />
                            <span>
                                <span className="block font-medium">
                                    Name and birthday
                                </span>
                                <span className="block text-muted-foreground">
                                    Type the person&apos;s name and date of
                                    birth
                                </span>
                            </span>
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="qr"
                            className="h-auto w-full justify-start gap-3 px-3 py-3 text-left"
                        >
                            <ScanLine />
                            <span>
                                <span className="block font-medium">
                                    Scan QR
                                </span>
                                <span className="block text-muted-foreground">
                                    Scan or paste the PhilID, ePhilID, or
                                    Digital National ID
                                </span>
                            </span>
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="pcn"
                            className="h-auto w-full justify-start gap-3 px-3 py-3 text-left"
                        >
                            <IdCard />
                            <span>
                                <span className="block font-medium">
                                    PCN / Digital ID
                                </span>
                                <span className="block text-muted-foreground">
                                    Type the PCN or Digital ID number
                                </span>
                            </span>
                        </ToggleGroupItem>
                    </ToggleGroup>
                    <InputError message={errors.everify_identity_method} />
                </div>
            ) : null}

            {step === 'identity' ? (
                <div className="grid gap-4">
                    {values.identityMethod === 'query' ? (
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="grid gap-2">
                                <Label htmlFor="everify_first_name">
                                    First name
                                </Label>
                                <Input
                                    id="everify_first_name"
                                    value={individual.first_name}
                                    onChange={(event) =>
                                        onIndividualChange((current) => ({
                                            ...current,
                                            first_name: event.target.value,
                                        }))
                                    }
                                />
                                <InputError message={errors.first_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="everify_middle_name">
                                    Middle name
                                </Label>
                                <Input
                                    id="everify_middle_name"
                                    value={individual.middle_name}
                                    onChange={(event) =>
                                        onIndividualChange((current) => ({
                                            ...current,
                                            middle_name: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="everify_last_name">
                                    Last name
                                </Label>
                                <Input
                                    id="everify_last_name"
                                    value={individual.last_name}
                                    onChange={(event) =>
                                        onIndividualChange((current) => ({
                                            ...current,
                                            last_name: event.target.value,
                                        }))
                                    }
                                />
                                <InputError message={errors.last_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="everify_suffix">Suffix</Label>
                                <NameSuffixSelect
                                    id="everify_suffix"
                                    value={individual.suffix}
                                    options={suffixOptions}
                                    onValueChange={(suffix) =>
                                        onIndividualChange((current) => ({
                                            ...current,
                                            suffix,
                                        }))
                                    }
                                />
                                <InputError message={errors.suffix} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="everify_birthday">
                                    Birthday
                                </Label>
                                <Input
                                    id="everify_birthday"
                                    type="date"
                                    value={individual.birthday}
                                    onChange={(event) =>
                                        onIndividualChange((current) => ({
                                            ...current,
                                            birthday: event.target.value,
                                        }))
                                    }
                                />
                                <InputError message={errors.birthday} />
                            </div>
                        </div>
                    ) : values.identityMethod === 'qr' ? (
                        <div className="grid gap-2">
                            <Label htmlFor="everify_qr_value">
                                National ID QR
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                Scan the PhilID, ePhilID, or Digital National
                                ID, or paste the QR value.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setScanningQr(true)}
                                >
                                    <ScanLine data-icon="inline-start" />
                                    {values.qrValue
                                        ? 'Scan again'
                                        : 'Scan QR'}
                                </Button>
                            </div>
                            <Dialog
                                open={scanningQr}
                                onOpenChange={setScanningQr}
                            >
                                <DialogContent className="sm:max-w-lg">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Scan National ID QR
                                        </DialogTitle>
                                        <DialogDescription>
                                            Point the camera at the PhilID,
                                            ePhilID, or Digital National ID.
                                        </DialogDescription>
                                    </DialogHeader>
                                    {scanningQr ? (
                                        <div className="overflow-hidden rounded-md">
                                            <Suspense
                                                fallback={
                                                    <Skeleton className="aspect-video w-full" />
                                                }
                                            >
                                                <QrScanner
                                                    formats={['qr_code']}
                                                    sound={false}
                                                    constraints={{
                                                        facingMode: {
                                                            ideal: 'environment',
                                                        },
                                                    }}
                                                    onScan={(
                                                        detectedCodes,
                                                    ) => {
                                                        const value =
                                                            detectedCodes[0]?.rawValue?.trim();

                                                        if (!value) {
                                                            return;
                                                        }

                                                        setValues(
                                                            (current) => ({
                                                                ...current,
                                                                qrValue: value,
                                                            }),
                                                        );
                                                        setScanningQr(false);
                                                        toast.success(
                                                            'QR code read.',
                                                        );
                                                    }}
                                                    onError={(error) => {
                                                        if (
                                                            error.kind ===
                                                                'aborted' ||
                                                            error.kind ===
                                                                'unknown'
                                                        ) {
                                                            return;
                                                        }

                                                        toast.error(
                                                            error.message ||
                                                                'Could not start the camera.',
                                                        );
                                                        setScanningQr(false);
                                                    }}
                                                    classNames={{
                                                        container: 'w-full',
                                                    }}
                                                />
                                            </Suspense>
                                        </div>
                                    ) : null}
                                    <DialogFooter showCloseButton />
                                </DialogContent>
                            </Dialog>
                            <Textarea
                                id="everify_qr_value"
                                value={values.qrValue}
                                onChange={(event) =>
                                    setValues((current) => ({
                                        ...current,
                                        qrValue: event.target.value,
                                    }))
                                }
                                rows={3}
                            />
                            <InputError message={errors.everify_qr_value} />
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor="everify_pcn_value">
                                PCN or Digital ID
                            </Label>
                            <Input
                                id="everify_pcn_value"
                                value={values.qrValue}
                                onChange={(event) =>
                                    setValues((current) => ({
                                        ...current,
                                        qrValue: event.target.value,
                                    }))
                                }
                            />
                            <InputError message={errors.everify_qr_value} />
                        </div>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setScanningQr(false);
                                setStep('identity-method');
                            }}
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                !identityIsReady(
                                    values.identityMethod,
                                    values,
                                    individual,
                                )
                            }
                            onClick={() => {
                                setScanningQr(false);
                                setStep('biometric');
                            }}
                        >
                            Continue
                        </Button>
                    </div>
                </div>
            ) : null}

            {step === 'biometric' ? (
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        {availableBiometrics.length > 1 ? (
                            <>
                                <Label>Verify with</Label>
                                <ToggleGroup
                                    type="single"
                                    value={values.biometricMethod}
                                    onValueChange={(value) => {
                                        if (
                                            isEverifyBiometricMethod(value) &&
                                            availableBiometrics.includes(value)
                                        ) {
                                            setValues((current) => ({
                                                ...current,
                                                biometricMethod: value,
                                                faceSessionId:
                                                    value === 'face'
                                                        ? current.faceSessionId
                                                        : '',
                                                fingerprint:
                                                    value === 'fingerprint'
                                                        ? current.fingerprint
                                                        : null,
                                            }));
                                        }
                                    }}
                                    variant="outline"
                                    spacing={0}
                                    className="flex w-full flex-wrap"
                                >
                                    {availableBiometrics.includes('face') ? (
                                        <ToggleGroupItem value="face">
                                            Face check
                                        </ToggleGroupItem>
                                    ) : null}
                                    {availableBiometrics.includes(
                                        'fingerprint',
                                    ) ? (
                                        <ToggleGroupItem value="fingerprint">
                                            Fingerprint
                                        </ToggleGroupItem>
                                    ) : null}
                                </ToggleGroup>
                            </>
                        ) : null}
                        <InputError message={errors.everify_biometric_method} />
                    </div>

                    {values.biometricMethod === 'face' &&
                    availableBiometrics.includes('face') ? (
                        <div className="grid gap-2">
                            <p className="text-sm text-muted-foreground">
                                The person must complete the eVerify liveness
                                check on this camera. CAIS stores only the
                                session id, not the photo.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void startFaceCheck()}
                                disabled={checkingFace || !publicKey}
                            >
                                <ShieldCheck data-icon="inline-start" />
                                {checkingFace
                                    ? 'Starting face check...'
                                    : values.faceSessionId !== ''
                                      ? 'Face check done. Run again'
                                      : 'Start face check'}
                            </Button>
                            {!publicKey ? (
                                <p className="text-sm text-muted-foreground">
                                    Set EVERIFY_PUBLIC_KEY to enable the face
                                    check.
                                </p>
                            ) : null}
                            <InputError
                                message={errors.face_liveness_session_id}
                            />
                        </div>
                    ) : values.biometricMethod === 'fingerprint' &&
                      availableBiometrics.includes('fingerprint') ? (
                        <div className="grid gap-2">
                            <Label htmlFor="fingerprint_device_id">
                                Scanner serial number
                            </Label>
                            <Input
                                id="fingerprint_device_id"
                                value={values.fingerprintDeviceId}
                                onChange={(event) =>
                                    setValues((current) => ({
                                        ...current,
                                        fingerprintDeviceId: event.target.value,
                                    }))
                                }
                            />
                            <p className="text-sm text-muted-foreground">
                                Capture runs on this computer at 127.0.0.1 ports{' '}
                                {fingerprintConfig.ports?.join(' or ') ||
                                    '4301 or 4302'}
                                . The fingerprint is sent to PhilSys and is not
                                saved in CAIS.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void startFingerprintCapture()}
                                disabled={capturingFingerprint}
                            >
                                <Fingerprint data-icon="inline-start" />
                                {capturingFingerprint
                                    ? 'Waiting for scanner...'
                                    : values.fingerprint
                                      ? 'Fingerprint captured. Capture again'
                                      : 'Capture fingerprint'}
                            </Button>
                            <InputError message={errors.everify_fingerprint} />
                        </div>
                    ) : null}

                    <InputError message={errors.intake_method} />

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep('identity')}
                            disabled={processing}
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            disabled={processing || !biometricIsReady(values)}
                            onClick={submitVerification}
                        >
                            {processing ? 'Verifying...' : 'Verify'}
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
