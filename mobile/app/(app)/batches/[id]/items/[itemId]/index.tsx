import * as Crypto from 'expo-crypto';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useState } from 'react';
import { Image, Text, View } from 'react-native';

import { isReleaseSyncResult, type PackageLine, type ReleasePayload } from '@/api/types';
import { Body, Button, Card, CheckRow, Choice, EmptyState, Field, Notice, Screen, Segmented, StatusPill, StepTrack } from '@/components/ui';
import type { BatchRecord, ItemRecord, OfficerRecord, OperationRecord } from '@/db/records';
import { getBatch, getItem, insertOperation, listItemOperations, listOfficers } from '@/db/repositories';
import { formatDecimal } from '@/domain/amounts';
import { effectiveStatus, releaseBlockReason } from '@/domain/roster';
import { formatWhen, shortReference } from '@/domain/text';
import { useEvidenceDraft } from '@/evidence/draft';
import { deleteEvidence, persistPhoto, persistSignature, sha256OfFile } from '@/evidence/files';
import { shareOfficialReceipt } from '@/evidence/receipt';
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
    const photoUri = useEvidenceDraft((state) => state.photoUri);
    const signatureData = useEvidenceDraft((state) => state.signatureData);
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

    const releaseFooter =
        item && batch && !missing && !blockReason && mode === 'release' ? (
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
        ) : null;
    const blockedFooter =
        item && batch && !missing && blockReason && (status === 'conflict' || status === 'pending_sync') ? (
            <Button label="Open sync queue" variant="secondary" onPress={() => router.push('/sync')} />
        ) : null;

    return (
        <Screen
            title={item?.name ?? 'Beneficiary'}
            subtitle={item ? `${item.caisNumber ? `CAIS ${item.caisNumber} · ` : ''}Request ${item.assistanceId}` : 'Release'}
            onBack={() => router.back()}
            footer={releaseFooter ?? blockedFooter}
        >
            <Body>
                {missing ? (
                    <EmptyState title="Beneficiary not on this device" body="Go back to the roster and confirm the batch is downloaded." />
                ) : item && batch ? (
                    <>
                        <View className="flex-row items-center justify-between">
                            <StatusPill status={status} />
                            <Text className="text-base text-ink/60">{batch.batchCode}</Text>
                        </View>
                        {operations.length > 0 ? (
                            <View className="gap-3">
                                {operations.map((operation) => (
                                    <Acknowledgment
                                        key={operation.clientOperationId}
                                        operation={operation}
                                        onReceiptError={setReceiptError}
                                    />
                                ))}
                            </View>
                        ) : null}
                        {receiptError ? <Notice tone="error">{receiptError}</Notice> : null}
                        {blockReason ? (
                            <Notice tone={status === 'conflict' ? 'error' : 'info'}>{blockReason}</Notice>
                        ) : (
                            <View className="gap-4">
                                <Segmented
                                    value={mode}
                                    options={[
                                        { value: 'release', label: 'Release' },
                                        { value: 'absent', label: 'Mark absent' },
                                    ]}
                                    onChange={setMode}
                                />
                                {mode === 'absent' ? (
                                    <View className="gap-4">
                                        <Field
                                            label="Why is the beneficiary absent?"
                                            value={remarks}
                                            onChangeText={setRemarks}
                                            multiline
                                            error={errors.remarks}
                                        />
                                        {formError ? <Notice tone="error">{formError}</Notice> : null}
                                        <Button label={busy ? 'Saving…' : 'Save absence on this device'} disabled={busy} onPress={() => void saveAbsent()} />
                                    </View>
                                ) : (
                                    <View className="gap-4">
                                        <StepTrack step={stepIndex} total={STEPS.length} label={STEP_TITLE[step]} />
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
                                                onOpenCamera={() => router.push(`/batches/${batchId}/items/${itemId}/camera`)}
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
                                                onOpenSignature={() => router.push(`/batches/${batchId}/items/${itemId}/signature`)}
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
                                    </View>
                                )}
                            </View>
                        )}
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
    const releaseResult = isReleaseSyncResult(operation.result) ? operation.result : null;
    const absenceId = operation.result && 'distribution_release_id' in operation.result ? operation.result.distribution_release_id : null;
    const hero = releaseResult?.receipt_number ?? releaseResult?.transaction_number ?? (absenceId != null ? String(absenceId) : null);
    const heroLabel = releaseResult?.receipt_number
        ? 'Official receipt'
        : releaseResult
          ? 'Transaction'
          : absenceId != null
            ? 'Absence record'
            : null;

    return (
        <Card tone={applied ? 'success' : 'default'}>
            <View className="gap-1">
                <Text className="text-xl font-semibold text-ink">
                    {applied
                        ? operation.type === 'absent'
                            ? 'Absence recorded'
                            : 'Release recorded'
                        : operation.type === 'absent'
                          ? 'Absence saved on this device'
                          : 'Saved on this device'}
                </Text>
                <Text className="text-base text-ink/60">
                    {applied
                        ? 'The server accepted this record.'
                        : operation.type === 'release'
                          ? 'The official receipt number is issued when this device syncs.'
                          : 'This absence stays on the device until it syncs.'}
                </Text>
            </View>
            {hero && heroLabel ? (
                <View className="gap-1">
                    <Text selectable className="text-3xl font-semibold text-ink">
                        {hero}
                    </Text>
                    <Text className="text-base text-ink/60">{heroLabel}</Text>
                </View>
            ) : (
                <Text selectable className="text-lg text-ink/80">
                    Reference {shortReference(operation.clientOperationId)}
                </Text>
            )}
            {hero ? (
                <Text selectable className="text-base text-ink/70">
                    Reference {shortReference(operation.clientOperationId)}
                </Text>
            ) : null}
            {releaseResult?.receipt_number ? (
                <Text selectable className="text-lg text-ink">
                    Transaction {releaseResult.transaction_number}
                </Text>
            ) : null}
            <Text className="text-lg text-ink">{'beneficiary_name' in payload ? payload.beneficiary_name : operation.itemName}</Text>
            {'lines' in payload
                ? payload.lines.map((line) => (
                      <Text key={line.id} className="text-base text-ink/80">
                          {lineSummary(line)}
                      </Text>
                  ))
                : null}
            {'remarks' in payload && !('lines' in payload) ? <Text className="text-base text-ink/80">{payload.remarks}</Text> : null}
            <Text className="text-base text-ink/60">Captured {formatWhen(operation.capturedAt)}</Text>
            {'photo_sha256' in payload && payload.photo_sha256 ? (
                <Text selectable className="text-base text-ink/60">
                    Photo SHA-256 {payload.photo_sha256}
                </Text>
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
            {props.batch.privacyNotice ? <Text className="text-base text-ink/80">{props.batch.privacyNotice}</Text> : null}
            {props.batch.retentionNotice ? <Text className="text-base text-ink/60">{props.batch.retentionNotice}</Text> : null}
            <Choice label="Beneficiary" selected={props.receivedByType === 'beneficiary'} onPress={() => props.setReceivedByType('beneficiary')} />
            <Choice label="Authorized representative" selected={props.receivedByType === 'representative'} onPress={() => props.setReceivedByType('representative')} />
            <Field label="Name of person receiving" value={props.receivedByName} onChangeText={props.setReceivedByName} error={props.errors.received_by_name} />
            {props.receivedByType === 'representative' ? (
                <>
                    <Field label="Relationship to the beneficiary" value={props.relationship} onChangeText={props.setRelationship} error={props.errors.representative_relationship} />
                    <Field label="How was the authorization checked?" value={props.verificationDetails} onChangeText={props.setVerificationDetails} multiline error={props.errors.representative_verification} />
                </>
            ) : null}
            <Text className="text-lg font-semibold text-ink">How identity was checked</Text>
            <Choice label="Control number" selected={props.verificationMethod === 'control_number'} onPress={() => props.setVerificationMethod('control_number')} />
            <Choice label="Request reference" selected={props.verificationMethod === 'request_reference'} onPress={() => props.setVerificationMethod('request_reference')} />
            <Choice label="Officer attestation" selected={props.verificationMethod === 'officer_attestation'} onPress={() => props.setVerificationMethod('officer_attestation')} />
            {props.errors.verification_method ? <Text className="text-base text-red-800">{props.errors.verification_method}</Text> : null}
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
            <Text className="text-base text-ink/70">Use whole numbers. Leave a line blank to release it later. A smaller amount needs a note on the next steps.</Text>
            {error ? <Notice tone="error">{error}</Notice> : null}
            {lines.map((line) => {
                const entry = entries[line.id] ?? { quantity: '', amount: '' };
                const showQuantity = line.remaining_quantity != null && Number(line.remaining_quantity) > 0;
                const showAmount = line.remaining_amount != null && Number(line.remaining_amount) > 0;

                return (
                    <View key={line.id} className="gap-3 rounded-3xl bg-white p-4" style={{ borderCurve: 'continuous' }}>
                        <Text className="text-lg font-semibold text-ink">{line.name}</Text>
                        <Text className="text-base text-ink/60">
                            {line.kind}
                            {line.unit ? ` · ${line.unit}` : ''} · remaining qty {formatDecimal(line.remaining_quantity)} · remaining amount {formatDecimal(line.remaining_amount)}
                        </Text>
                        {showQuantity ? (
                            <Field label="Quantity" value={entry.quantity} onChangeText={(value) => onChange(line.id, 'quantity', value)} keyboardType="number-pad" />
                        ) : null}
                        {showAmount ? (
                            <Field label="Amount" value={entry.amount} onChangeText={(value) => onChange(line.id, 'amount', value)} keyboardType="number-pad" />
                        ) : null}
                        {!showQuantity && !showAmount ? <Text className="text-base text-ink/60">Nothing remaining on this line.</Text> : null}
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
    onOpenCamera,
    errors,
}: {
    canWaive: boolean;
    photoUri: string | null;
    waiver: string;
    onWaiver: (value: string) => void;
    onOpenCamera: () => void;
    errors: FieldErrors;
}) {
    return (
        <View className="gap-3">
            <Text className="text-base text-ink/70">Take the photo with this device camera. A photo from the gallery cannot be used.</Text>
            {photoUri ? (
                <>
                    <Image source={{ uri: photoUri }} resizeMode="cover" style={{ width: '100%', height: 220, borderRadius: 24 }} />
                    <Notice>Live photo saved for this release.</Notice>
                    <Button label="Retake photo" variant="secondary" onPress={onOpenCamera} />
                </>
            ) : (
                <Button label="Open camera" onPress={onOpenCamera} />
            )}
            {errors.live_photo ? <Notice tone="error">{errors.live_photo}</Notice> : null}
            {canWaive ? (
                <Field label="Camera waiver reason" value={waiver} onChangeText={onWaiver} multiline error={errors.camera_waiver_reason} placeholder="Use only when the camera cannot take the photo" />
            ) : (
                <Text className="text-base text-ink/60">A live photo is required. Your account cannot waive it.</Text>
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
    onOpenSignature,
    errors,
}: {
    mode: 'signature' | 'alternative';
    setMode: (mode: 'signature' | 'alternative') => void;
    reason: string;
    setReason: (value: string) => void;
    signature: string | null;
    onOpenSignature: () => void;
    errors: FieldErrors;
}) {
    return (
        <View className="gap-3">
            <Choice label="Signature" selected={mode === 'signature'} onPress={() => setMode('signature')} />
            <Choice label="Alternative acknowledgment" selected={mode === 'alternative'} onPress={() => setMode('alternative')} />
            {mode === 'signature' ? (
                <>
                    {signature ? (
                        <>
                            <Image source={{ uri: signature }} resizeMode="contain" style={{ width: '100%', height: 180, borderRadius: 24, backgroundColor: '#fff' }} />
                            <Notice>Signature saved for this release.</Notice>
                            <Button label="Sign again" variant="secondary" onPress={onOpenSignature} />
                        </>
                    ) : (
                        <Button label="Open signature" onPress={onOpenSignature} />
                    )}
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
            <Text className="text-lg font-semibold text-ink">Witness</Text>
            <Choice label="No witness" selected={witnessedBy === ''} onPress={() => setWitnessedBy('')} />
            {officers.map((officer) => (
                <Choice
                    key={officer.id}
                    label={officer.name}
                    selected={witnessedBy === String(officer.id)}
                    onPress={() => setWitnessedBy(String(officer.id))}
                />
            ))}
            {errors.witnessed_by ? <Text className="text-base text-red-800">{errors.witnessed_by}</Text> : null}
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
        <View className="gap-4">
            <View className="gap-1">
                <Text className="text-3xl font-semibold text-ink">{name.trim() || 'Name missing'}</Text>
                <Text className="text-base text-ink/60">Recipient</Text>
            </View>
            <Text className={`text-lg ${photo ? 'text-ink' : 'text-red-800'}`}>{photo ? 'Live photo attached' : 'Live photo still needed'}</Text>
            <Text className={`text-lg ${signature ? 'text-ink' : 'text-red-800'}`}>
                {signature ? 'Acknowledgment recorded' : 'Acknowledgment still needed'}
            </Text>
            {'lines' in collected ? (
                <View className="gap-2">
                    {collected.lines.map((line) => (
                        <Text key={line.id} className="text-lg text-ink">
                            {lineSummary(line)}
                        </Text>
                    ))}
                </View>
            ) : (
                <Text className="text-base text-red-800">{collected.error}</Text>
            )}
            <Text className="text-base text-ink/70">
                Saving keeps this release on the device. The official receipt number is issued when the device syncs.
            </Text>
        </View>
    );
}
