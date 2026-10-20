import * as Crypto from 'expo-crypto';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useRef, useState } from 'react';
import { Text, View, type ScrollViewInstance } from 'react-native';

import { isReleaseSyncResult, type PackageLine, type ReleasePayload } from '@/api/types';
import { Body, Button, Card, CheckRow, Choice, EmptyState, Field, Notice, Screen, StatusPill } from '@/components/ui';
import type { BatchRecord, ItemRecord, OfficerRecord, OperationRecord } from '@/db/records';
import { getBatch, getItem, insertOperation, listItemOperations, listOfficers } from '@/db/repositories';
import { formatDecimal } from '@/domain/amounts';
import { effectiveStatus, releaseBlockReason } from '@/domain/roster';
import { formatWhen, shortReference } from '@/domain/text';
import { CameraCapture } from '@/evidence/camera-capture';
import { deleteEvidence, persistPhoto, persistSignature, sha256OfFile } from '@/evidence/files';
import { shareOfficialReceipt } from '@/evidence/receipt';
import { SignaturePad } from '@/evidence/signature-pad';
import {
    collectLines,
    defaultEntries,
    validateAbsent,
    validateRelease,
    type FieldErrors,
    type LineEntry,
} from '@/features/release/validation';
import { useSessionStore } from '@/session/store';
import { syncNow } from '@/sync/engine';

type Step = 'identity' | 'lines' | 'photo' | 'signature' | 'witness' | 'review';

const STEPS: Step[] = ['identity', 'lines', 'photo', 'signature', 'witness', 'review'];

const STEP_TITLE: Record<Step, string> = {
    identity: 'Who is receiving',
    lines: 'What to release',
    photo: 'Live photo',
    signature: 'Acknowledgment',
    witness: 'Witness and notes',
    review: 'Confirm and save',
};

function routeParam(value: string | string[] | undefined): string {
    return Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
}

function stepFor(errors: FieldErrors): Step {
    if (
        errors.received_by_name ||
        errors.verification_method ||
        errors.identity_verified ||
        errors.privacy_acknowledged ||
        errors.representative_relationship ||
        errors.representative_verification
    ) {
        return 'identity';
    }

    if (errors.lines) {
        return 'lines';
    }

    if (errors.live_photo || errors.camera_waiver_reason) {
        return 'photo';
    }

    if (errors.acknowledgment_mode || errors.alternative_reason) {
        return 'signature';
    }

    return 'witness';
}

function lineSummary(line: { name: string; actual_quantity: string | null; actual_amount: string | null }): string {
    const parts = [line.name];

    if (line.actual_quantity) {
        parts.push(`qty ${line.actual_quantity}`);
    }

    if (line.actual_amount) {
        parts.push(`amount ${line.actual_amount}`);
    }

    return parts.join(' · ');
}

export default function ReleaseScreen() {
    const params = useLocalSearchParams<{ id: string; itemId: string }>();
    const batchId = Number(routeParam(params.id));
    const itemId = Number(routeParam(params.itemId));
    const user = useSessionStore((state) => state.user);
    const [batch, setBatch] = useState<BatchRecord | null>(null);
    const [item, setItem] = useState<ItemRecord | null>(null);
    const [officers, setOfficers] = useState<OfficerRecord[]>([]);
    const [operations, setOperations] = useState<OperationRecord[]>([]);
    const [missing, setMissing] = useState(false);
    const [mode, setMode] = useState<'release' | 'absent'>('release');
    const [step, setStep] = useState<Step>('identity');
    const [receivedByType, setReceivedByType] = useState<'beneficiary' | 'representative'>('beneficiary');
    const [receivedByName, setReceivedByName] = useState('');
    const [relationship, setRelationship] = useState('');
    const [verificationDetails, setVerificationDetails] = useState('');
    const [verificationMethod, setVerificationMethod] = useState<'control_number' | 'request_reference' | 'officer_attestation'>('control_number');
    const [identityVerified, setIdentityVerified] = useState(false);
    const [privacyAcknowledged, setPrivacyAcknowledged] = useState(false);
    const [acknowledgmentMode, setAcknowledgmentMode] = useState<'signature' | 'alternative'>('signature');
    const [alternativeReason, setAlternativeReason] = useState('');
    const [cameraWaiverReason, setCameraWaiverReason] = useState('');
    const [notes, setNotes] = useState('');
    const [remarks, setRemarks] = useState('');
    const [witnessedBy, setWitnessedBy] = useState('');
    const [entries, setEntries] = useState<Record<number, LineEntry>>({});
    const [photoUri, setPhotoUri] = useState<string | null>(null);
    const [signatureData, setSignatureData] = useState<string | null>(null);
    const bodyRef = useRef<ScrollViewInstance>(null);
    const [errors, setErrors] = useState<FieldErrors>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [receiptError, setReceiptError] = useState<string | null>(null);
    const [preparedFor, setPreparedFor] = useState<number | null>(null);

    const load = useCallback(async () => {
        if (!user || !Number.isFinite(batchId) || !Number.isFinite(itemId)) {
            setMissing(true);

            return;
        }

        const [nextBatch, nextItem, nextOfficers, nextOperations] = await Promise.all([
            getBatch(user.id, batchId),
            getItem(batchId, itemId),
            listOfficers(batchId),
            listItemOperations(batchId, itemId),
        ]);
        setBatch(nextBatch);
        setItem(nextItem);
        setOfficers(nextOfficers);
        setOperations(nextOperations);
        setMissing(!nextBatch || !nextItem);
    }, [batchId, itemId, user]);

    useFocusEffect(
        useCallback(() => {
            void load();
        }, [load]),
    );

    if (item && preparedFor !== item.id) {
        setPreparedFor(item.id);
        setEntries(defaultEntries(item.lines));
        setReceivedByName(item.name);
    }

    const status = item ? effectiveStatus(item, operations) : 'pending';
    const blockReason = batch && item ? releaseBlockReason(status, batch.canExecute) : null;
    const stepIndex = STEPS.indexOf(step);

    function draft() {
        return {
            receivedByType,
            receivedByName,
            representativeRelationship: relationship,
            representativeVerification: verificationDetails,
            verificationMethod,
            identityVerified,
            acknowledgmentMode,
            alternativeReason,
            privacyAcknowledged,
            cameraWaiverReason,
            notes,
            witnessedBy,
            officerIds: officers.map((officer) => officer.id),
            hasPhoto: photoUri != null,
            hasSignature: signatureData != null,
            canWaive: batch?.canWaive ?? false,
            lines: item?.lines ?? [],
            entries,
        };
    }

    function updateEntry(lineId: number, key: 'quantity' | 'amount', value: string): void {
        setEntries((current) => ({
            ...current,
            [lineId]: {
                quantity: current[lineId]?.quantity ?? '',
                amount: current[lineId]?.amount ?? '',
                [key]: value,
            },
        }));
    }

    async function saveRelease(): Promise<void> {
        if (!user || !item || !batch) {
            return;
        }

        const nextErrors = validateRelease(draft());

        if (Object.keys(nextErrors).length > 0) {
            setErrors(nextErrors);
            setStep(stepFor(nextErrors));

            return;
        }

        if (!batch.canIssueReceipt) {
            setFormError('You cannot issue a receipt for this batch.');

            return;
        }

        const collected = collectLines(item.lines, entries);

        if ('error' in collected) {
            setErrors({ lines: collected.error });
            setStep('lines');

            return;
        }

        const latest = await listItemOperations(batch.id, item.id);
        const latestStatus = effectiveStatus(item, latest);
        const blocked = releaseBlockReason(latestStatus, batch.canExecute);

        if (blocked) {
            setOperations(latest);
            setFormError(blocked);

            return;
        }

        const clientOperationId = Crypto.randomUUID();
        const capturedAt = new Date().toISOString();
        let photoPath: string | null = null;
        let signaturePath: string | null = null;
        setBusy(true);
        setFormError(null);

        try {
            let photoSha: string | null = null;

            if (photoUri) {
                photoPath = await persistPhoto(photoUri, clientOperationId);
                photoSha = await sha256OfFile(photoPath);
            }

            if (acknowledgmentMode === 'signature' && signatureData) {
                signaturePath = await persistSignature(signatureData, clientOperationId);
            }

            const payload: ReleasePayload = {
                received_by_type: receivedByType,
                received_by_name: receivedByName.trim(),
                representative_relationship: relationship.trim(),
                representative_verification: verificationDetails.trim(),
                verification_method: verificationMethod,
                acknowledgment_mode: acknowledgmentMode,
                alternative_reason: alternativeReason.trim(),
                camera_waiver_reason: cameraWaiverReason.trim(),
                notes: notes.trim(),
                witnessed_by: witnessedBy.trim(),
                lines: collected.lines,
                photo_sha256: photoSha,
                partial: collected.partial,
                beneficiary_name: item.name,
            };
            await insertOperation({
                clientOperationId,
                idempotencyKey: Crypto.randomUUID(),
                userId: user.id,
                batchId: batch.id,
                itemId: item.id,
                type: 'release',
                payload,
                livePhotoPath: photoPath,
                signaturePath,
                capturedAt,
            });
            await syncNow();
            await load();
        } catch (caught) {
            deleteEvidence(photoPath);
            deleteEvidence(signaturePath);
            setFormError(caught instanceof Error ? caught.message : 'The release could not be saved on this device.');
        } finally {
            setBusy(false);
        }
    }

    async function saveAbsent(): Promise<void> {
        if (!user || !item || !batch) {
            return;
        }

        const nextErrors = validateAbsent(remarks);

        if (Object.keys(nextErrors).length > 0) {
            setErrors(nextErrors);

            return;
        }

        const latest = await listItemOperations(batch.id, item.id);
        const blocked = releaseBlockReason(effectiveStatus(item, latest), batch.canExecute);

        if (blocked) {
            setOperations(latest);
            setFormError(blocked);

            return;
        }

        setBusy(true);
        setFormError(null);

        try {
            await insertOperation({
                clientOperationId: Crypto.randomUUID(),
                idempotencyKey: Crypto.randomUUID(),
                userId: user.id,
                batchId: batch.id,
                itemId: item.id,
                type: 'absent',
                payload: { remarks: remarks.trim(), beneficiary_name: item.name },
                livePhotoPath: null,
                signaturePath: null,
                capturedAt: new Date().toISOString(),
            });
            await syncNow();
            await load();
        } catch (caught) {
            setFormError(caught instanceof Error ? caught.message : 'The absence could not be saved on this device.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <Screen
            title={item?.name ?? 'Beneficiary'}
            subtitle={item ? `${item.caisNumber ? `CAIS ${item.caisNumber} · ` : ''}Request ${item.assistanceId}` : 'Release'}
            onBack={() => router.back()}
        >
            <Body ref={bodyRef}>
                {missing ? (
                    <EmptyState title="Beneficiary not on this device" body="Go back to the roster and confirm the batch is downloaded." />
                ) : item && batch ? (
                    <>
                        <View className="flex-row items-center justify-between">
                            <StatusPill status={status} />
                            <Text className="text-base text-stone-600">{batch.batchCode}</Text>
                        </View>
                        {operations.map((operation) => (
                            <Acknowledgment
                                key={operation.clientOperationId}
                                operation={operation}
                                onReceiptError={setReceiptError}
                            />
                        ))}
                        {receiptError ? <Notice tone="error">{receiptError}</Notice> : null}
                        {blockReason ? (
                            <Notice tone={status === 'conflict' ? 'error' : 'info'}>{blockReason}</Notice>
                        ) : (
                            <Card>
                                <View className="flex-row gap-3">
                                    <View className="flex-1">
                                        <Button label="Release" variant={mode === 'release' ? 'primary' : 'secondary'} onPress={() => setMode('release')} />
                                    </View>
                                    <View className="flex-1">
                                        <Button label="Mark absent" variant={mode === 'absent' ? 'primary' : 'secondary'} onPress={() => setMode('absent')} />
                                    </View>
                                </View>
                                {mode === 'absent' ? (
                                    <>
                                        <Field
                                            label="Why is the beneficiary absent?"
                                            value={remarks}
                                            onChangeText={setRemarks}
                                            multiline
                                            error={errors.remarks}
                                        />
                                        {formError ? <Notice tone="error">{formError}</Notice> : null}
                                        <Button label={busy ? 'Saving…' : 'Save absence on this device'} disabled={busy} onPress={() => void saveAbsent()} />
                                    </>
                                ) : (
                                    <>
                                        <Text className="text-base font-semibold text-stone-600">
                                            Step {stepIndex + 1} of {STEPS.length}: {STEP_TITLE[step]}
                                        </Text>
                                        {step === 'identity' ? (
                                            <IdentityStep
                                                batch={batch}
                                                receivedByType={receivedByType}
                                                setReceivedByType={setReceivedByType}
                                                receivedByName={receivedByName}
                                                setReceivedByName={setReceivedByName}
                                                relationship={relationship}
                                                setRelationship={setRelationship}
                                                verificationDetails={verificationDetails}
                                                setVerificationDetails={setVerificationDetails}
                                                verificationMethod={verificationMethod}
                                                setVerificationMethod={setVerificationMethod}
                                                identityVerified={identityVerified}
                                                setIdentityVerified={setIdentityVerified}
                                                privacyAcknowledged={privacyAcknowledged}
                                                setPrivacyAcknowledged={setPrivacyAcknowledged}
                                                errors={errors}
                                            />
                                        ) : null}
                                        {step === 'lines' ? (
                                            <LinesStep lines={item.lines} entries={entries} onChange={updateEntry} error={errors.lines} />
                                        ) : null}
                                        {step === 'photo' ? (
                                            <PhotoStep
                                                canWaive={batch.canWaive}
                                                photoUri={photoUri}
                                                waiver={cameraWaiverReason}
                                                onWaiver={setCameraWaiverReason}
                                                onPhoto={setPhotoUri}
                                                errors={errors}
                                            />
                                        ) : null}
                                        {step === 'signature' ? (
                                            <SignatureStep
                                                mode={acknowledgmentMode}
                                                setMode={setAcknowledgmentMode}
                                                reason={alternativeReason}
                                                setReason={setAlternativeReason}
                                                signature={signatureData}
                                                onSignature={setSignatureData}
                                                onClear={() => setSignatureData(null)}
                                                onDrawingChange={(drawing) => {
                                                    bodyRef.current?.setNativeProps({ scrollEnabled: !drawing });
                                                }}
                                                errors={errors}
                                            />
                                        ) : null}
                                        {step === 'witness' ? (
                                            <WitnessStep
                                                officers={officers}
                                                witnessedBy={witnessedBy}
                                                setWitnessedBy={setWitnessedBy}
                                                notes={notes}
                                                setNotes={setNotes}
                                                partial={(() => {
                                                    const collected = collectLines(item.lines, entries);

                                                    return !('error' in collected) && collected.partial;
                                                })()}
                                                errors={errors}
                                            />
                                        ) : null}
                                        {step === 'review' ? (
                                            <ReviewStep
                                                name={receivedByName}
                                                lines={item.lines}
                                                entries={entries}
                                                photo={photoUri != null}
                                                signature={acknowledgmentMode === 'signature' ? signatureData != null : alternativeReason.trim() !== ''}
                                            />
                                        ) : null}
                                        {formError ? <Notice tone="error">{formError}</Notice> : null}
                                        {!batch.canIssueReceipt ? (
                                            <Notice tone="warning">You can review this beneficiary, but your account cannot issue the receipt.</Notice>
                                        ) : null}
                                        <View className="flex-row gap-3">
                                            {stepIndex > 0 ? (
                                                <View className="flex-1">
                                                    <Button label="Back" variant="secondary" onPress={() => setStep(STEPS[stepIndex - 1] ?? 'identity')} />
                                                </View>
                                            ) : null}
                                            <View className="flex-1">
                                                {step === 'review' ? (
                                                    <Button
                                                        label={busy ? 'Saving…' : 'Save on this device'}
                                                        disabled={busy || !batch.canIssueReceipt}
                                                        onPress={() => void saveRelease()}
                                                    />
                                                ) : (
                                                    <Button label="Next" onPress={() => setStep(STEPS[stepIndex + 1] ?? 'review')} />
                                                )}
                                            </View>
                                        </View>
                                    </>
                                )}
                            </Card>
                        )}
                        {status === 'conflict' || status === 'pending_sync' ? (
                            <Button label="Open sync queue" variant="secondary" onPress={() => router.push('/sync')} />
                        ) : null}
                    </>
                ) : null}
            </Body>
        </Screen>
    );
}

function Acknowledgment({
    operation,
    onReceiptError,
}: {
    operation: OperationRecord;
    onReceiptError: (message: string | null) => void;
}) {
    const applied = operation.status === 'applied';
    const payload = operation.payload;

    return (
        <Card>
            <Text className="text-lg font-semibold text-stone-900">
                {applied
                    ? operation.type === 'absent'
                        ? 'Synced absence'
                        : 'Synced release'
                    : 'Provisional acknowledgment'}
            </Text>
            <Text className="text-base text-stone-800">Reference {shortReference(operation.clientOperationId)}</Text>
            <Text className="text-base text-stone-800">
                {'beneficiary_name' in payload ? payload.beneficiary_name : operation.itemName}
            </Text>
            {'lines' in payload
                ? payload.lines.map((line) => (
                      <Text key={line.id} className="text-base text-stone-700">
                          {lineSummary(line)}
                      </Text>
                  ))
                : null}
            {'remarks' in payload && !('lines' in payload) ? <Text className="text-base text-stone-700">{payload.remarks}</Text> : null}
            <Text className="text-base text-stone-600">Captured {formatWhen(operation.capturedAt)}</Text>
            {'photo_sha256' in payload && payload.photo_sha256 ? (
                <Text className="text-base text-stone-600">Photo SHA-256 {payload.photo_sha256}</Text>
            ) : null}
            {applied && isReleaseSyncResult(operation.result) ? (
                <Text className="text-base font-semibold text-stone-900">Transaction {operation.result.transaction_number}</Text>
            ) : null}
            {applied && isReleaseSyncResult(operation.result) && operation.result.receipt_number ? (
                <Text className="text-base font-semibold text-stone-900">Receipt {operation.result.receipt_number}</Text>
            ) : null}
            {applied && operation.result && 'distribution_release_id' in operation.result ? (
                <Text className="text-base font-semibold text-stone-900">Absence record {operation.result.distribution_release_id}</Text>
            ) : null}
            {!applied && operation.type === 'release' ? (
                <Text className="text-base text-stone-700">
                    The official receipt number is issued when this device syncs.
                </Text>
            ) : null}
            {!applied && operation.type === 'absent' ? (
                <Text className="text-base text-stone-700">This absence stays on the device until it syncs.</Text>
            ) : null}
            {isReleaseSyncResult(operation.result) ? (
                <Button
                    label="Open official receipt"
                    variant="secondary"
                    onPress={() => {
                        const result = operation.result;

                        if (!isReleaseSyncResult(result)) {
                            return;
                        }

                        void (async () => {
                            try {
                                await shareOfficialReceipt(result.receipt_pdf_url, result.receipt_number);
                                onReceiptError(null);
                            } catch (caught) {
                                onReceiptError(caught instanceof Error ? caught.message : 'The receipt could not be opened.');
                            }
                        })();
                    }}
                />
            ) : null}
        </Card>
    );
}

function IdentityStep(props: {
    batch: BatchRecord;
    receivedByType: 'beneficiary' | 'representative';
    setReceivedByType: (value: 'beneficiary' | 'representative') => void;
    receivedByName: string;
    setReceivedByName: (value: string) => void;
    relationship: string;
    setRelationship: (value: string) => void;
    verificationDetails: string;
    setVerificationDetails: (value: string) => void;
    verificationMethod: 'control_number' | 'request_reference' | 'officer_attestation';
    setVerificationMethod: (value: 'control_number' | 'request_reference' | 'officer_attestation') => void;
    identityVerified: boolean;
    setIdentityVerified: (value: boolean) => void;
    privacyAcknowledged: boolean;
    setPrivacyAcknowledged: (value: boolean) => void;
    errors: FieldErrors;
}) {
    return (
        <View className="gap-3">
            {props.batch.privacyNotice ? <Text className="text-base text-stone-800">{props.batch.privacyNotice}</Text> : null}
            {props.batch.retentionNotice ? <Text className="text-base text-stone-600">{props.batch.retentionNotice}</Text> : null}
            <Choice label="Beneficiary" selected={props.receivedByType === 'beneficiary'} onPress={() => props.setReceivedByType('beneficiary')} />
            <Choice label="Authorized representative" selected={props.receivedByType === 'representative'} onPress={() => props.setReceivedByType('representative')} />
            <Field label="Name of person receiving" value={props.receivedByName} onChangeText={props.setReceivedByName} error={props.errors.received_by_name} />
            {props.receivedByType === 'representative' ? (
                <>
                    <Field label="Relationship to the beneficiary" value={props.relationship} onChangeText={props.setRelationship} error={props.errors.representative_relationship} />
                    <Field label="How was the authorization checked?" value={props.verificationDetails} onChangeText={props.setVerificationDetails} multiline error={props.errors.representative_verification} />
                </>
            ) : null}
            <Text className="text-base font-semibold text-stone-800">How identity was checked</Text>
            <Choice label="Control number" selected={props.verificationMethod === 'control_number'} onPress={() => props.setVerificationMethod('control_number')} />
            <Choice label="Request reference" selected={props.verificationMethod === 'request_reference'} onPress={() => props.setVerificationMethod('request_reference')} />
            <Choice label="Officer attestation" selected={props.verificationMethod === 'officer_attestation'} onPress={() => props.setVerificationMethod('officer_attestation')} />
            {props.errors.verification_method ? <Text className="text-base text-red-700">{props.errors.verification_method}</Text> : null}
            <CheckRow label="I confirmed the recipient." checked={props.identityVerified} onPress={() => props.setIdentityVerified(!props.identityVerified)} error={props.errors.identity_verified} />
            <CheckRow label="The privacy notice was acknowledged." checked={props.privacyAcknowledged} onPress={() => props.setPrivacyAcknowledged(!props.privacyAcknowledged)} error={props.errors.privacy_acknowledged} />
        </View>
    );
}

function LinesStep({
    lines,
    entries,
    onChange,
    error,
}: {
    lines: PackageLine[];
    entries: Record<number, LineEntry>;
    onChange: (lineId: number, key: 'quantity' | 'amount', value: string) => void;
    error?: string;
}) {
    return (
        <View className="gap-4">
            <Text className="text-base text-stone-700">Use whole numbers. Leave a line blank to release it later. A smaller amount needs a note on the next steps.</Text>
            {error ? <Notice tone="error">{error}</Notice> : null}
            {lines.map((line) => {
                const entry = entries[line.id] ?? { quantity: '', amount: '' };
                const showQuantity = line.remaining_quantity != null && Number(line.remaining_quantity) > 0;
                const showAmount = line.remaining_amount != null && Number(line.remaining_amount) > 0;

                return (
                    <View key={line.id} className="gap-2 rounded-xl bg-stone-50 p-3">
                        <Text className="text-lg font-semibold text-stone-900">{line.name}</Text>
                        <Text className="text-base text-stone-600">
                            {line.kind}
                            {line.unit ? ` · ${line.unit}` : ''} · remaining qty {formatDecimal(line.remaining_quantity)} · remaining amount {formatDecimal(line.remaining_amount)}
                        </Text>
                        {showQuantity ? (
                            <Field label="Quantity" value={entry.quantity} onChangeText={(value) => onChange(line.id, 'quantity', value)} keyboardType="number-pad" />
                        ) : null}
                        {showAmount ? (
                            <Field label="Amount" value={entry.amount} onChangeText={(value) => onChange(line.id, 'amount', value)} keyboardType="number-pad" />
                        ) : null}
                        {!showQuantity && !showAmount ? <Text className="text-base text-stone-600">Nothing remaining on this line.</Text> : null}
                    </View>
                );
            })}
        </View>
    );
}

function PhotoStep({
    canWaive,
    photoUri,
    waiver,
    onWaiver,
    onPhoto,
    errors,
}: {
    canWaive: boolean;
    photoUri: string | null;
    waiver: string;
    onWaiver: (value: string) => void;
    onPhoto: (uri: string | null) => void;
    errors: FieldErrors;
}) {
    return (
        <View className="gap-3">
            <Text className="text-base text-stone-700">Take the photo with this device camera. A photo from the gallery cannot be used.</Text>
            {photoUri ? <Notice>Live photo saved for this release.</Notice> : <CameraCapture onCapture={onPhoto} />}
            {photoUri ? <Button label="Retake photo" variant="secondary" onPress={() => onPhoto(null)} /> : null}
            {errors.live_photo ? <Notice tone="error">{errors.live_photo}</Notice> : null}
            {canWaive ? (
                <Field label="Camera waiver reason" value={waiver} onChangeText={onWaiver} multiline error={errors.camera_waiver_reason} placeholder="Use only when the camera cannot take the photo" />
            ) : (
                <Text className="text-base text-stone-600">A live photo is required. Your account cannot waive it.</Text>
            )}
        </View>
    );
}

function SignatureStep({
    mode,
    setMode,
    reason,
    setReason,
    signature,
    onSignature,
    onClear,
    onDrawingChange,
    errors,
}: {
    mode: 'signature' | 'alternative';
    setMode: (mode: 'signature' | 'alternative') => void;
    reason: string;
    setReason: (value: string) => void;
    signature: string | null;
    onSignature: (dataUrl: string) => void;
    onClear: () => void;
    onDrawingChange: (drawing: boolean) => void;
    errors: FieldErrors;
}) {
    return (
        <View className="gap-3">
            <Choice
                label="Signature"
                selected={mode === 'signature'}
                onPress={() => {
                    onDrawingChange(false);
                    setMode('signature');
                }}
            />
            <Choice
                label="Alternative acknowledgment"
                selected={mode === 'alternative'}
                onPress={() => {
                    onDrawingChange(false);
                    setMode('alternative');
                }}
            />
            {mode === 'signature' ? (
                <>
                    {signature ? <Notice>Signature saved for this release.</Notice> : null}
                    <SignaturePad value={signature} onSave={onSignature} onClear={onClear} onDrawingChange={onDrawingChange} />
                    {errors.acknowledgment_mode ? <Notice tone="error">{errors.acknowledgment_mode}</Notice> : null}
                </>
            ) : (
                <Field
                    label="Why the recipient cannot sign, and what was used instead"
                    value={reason}
                    onChangeText={setReason}
                    multiline
                    error={errors.alternative_reason}
                />
            )}
        </View>
    );
}

function WitnessStep({
    officers,
    witnessedBy,
    setWitnessedBy,
    notes,
    setNotes,
    partial,
    errors,
}: {
    officers: OfficerRecord[];
    witnessedBy: string;
    setWitnessedBy: (value: string) => void;
    notes: string;
    setNotes: (value: string) => void;
    partial: boolean;
    errors: FieldErrors;
}) {
    return (
        <View className="gap-3">
            <Text className="text-base font-semibold text-stone-800">Witness</Text>
            <Choice label="No witness" selected={witnessedBy === ''} onPress={() => setWitnessedBy('')} />
            {officers.map((officer) => (
                <Choice
                    key={officer.id}
                    label={officer.name}
                    selected={witnessedBy === String(officer.id)}
                    onPress={() => setWitnessedBy(String(officer.id))}
                />
            ))}
            {errors.witnessed_by ? <Text className="text-base text-red-700">{errors.witnessed_by}</Text> : null}
            {partial ? <Notice tone="warning">This is a partial release. Write the reason below before you continue.</Notice> : null}
            <Field label="Notes" value={notes} onChangeText={setNotes} multiline error={errors.notes} />
        </View>
    );
}

function ReviewStep({
    name,
    lines,
    entries,
    photo,
    signature,
}: {
    name: string;
    lines: PackageLine[];
    entries: Record<number, LineEntry>;
    photo: boolean;
    signature: boolean;
}) {
    const collected = collectLines(lines, entries);

    return (
        <View className="gap-2">
            <Text className="text-lg text-stone-900">Recipient: {name.trim() || 'Not entered'}</Text>
            <Text className="text-base text-stone-700">{photo ? 'Live photo attached.' : 'No live photo yet.'}</Text>
            <Text className="text-base text-stone-700">{signature ? 'Acknowledgment recorded.' : 'Acknowledgment missing.'}</Text>
            {'lines' in collected
                ? collected.lines.map((line) => (
                      <Text key={line.id} className="text-base text-stone-800">
                          {lineSummary(line)}
                      </Text>
                  ))
                : <Text className="text-base text-red-700">{collected.error}</Text>}
            <Text className="text-base text-stone-700">
                Saving keeps this release on the device. The official receipt number is issued when the device syncs.
            </Text>
        </View>
    );
}
