import type { PackageLine, ReleaseLinePayload } from '@/api/types';
import { isPositiveWhole, maxWhole, positiveAmount } from '@/domain/amounts';

export type LineEntry = {
    quantity: string;
    amount: string;
};

export type ReleaseDraft = {
    receivedByType: 'beneficiary' | 'representative';
    receivedByName: string;
    representativeRelationship: string;
    representativeVerification: string;
    verificationMethod: 'control_number' | 'request_reference' | 'officer_attestation';
    identityVerified: boolean;
    acknowledgmentMode: 'signature' | 'alternative';
    alternativeReason: string;
    privacyAcknowledged: boolean;
    cameraWaiverReason: string;
    notes: string;
    witnessedBy: string;
    officerIds: number[];
    hasPhoto: boolean;
    hasSignature: boolean;
    canWaive: boolean;
    lines: PackageLine[];
    entries: Record<number, LineEntry>;
};

export type FieldErrors = Record<string, string>;

export type CollectedLines = {
    lines: ReleaseLinePayload[];
    partial: boolean;
};

const VERIFICATION_METHODS = new Set(['control_number', 'request_reference', 'officer_attestation']);

function blank(value: string): boolean {
    return value.trim() === '' || value.trim() === '0';
}

export function collectLines(lines: PackageLine[], entries: Record<number, LineEntry>): CollectedLines | { error: string } {
    const chosen: ReleaseLinePayload[] = [];
    let partial = false;

    for (const line of lines) {
        const entry = entries[line.id] ?? { quantity: '', amount: '' };
        const quantityRemaining = positiveAmount(line.remaining_quantity);
        const amountRemaining = positiveAmount(line.remaining_amount);
        const quantityMax = quantityRemaining == null ? null : maxWhole(quantityRemaining);
        const amountMax = amountRemaining == null ? null : maxWhole(amountRemaining);
        const needsQuantity = quantityRemaining != null;
        const needsAmount = amountRemaining != null;

        if (!needsQuantity && !needsAmount) {
            continue;
        }

        if (needsQuantity && quantityMax === 0) {
            return { error: `${line.name} has less than 1 remaining and cannot be released as a whole number.` };
        }

        if (needsAmount && amountMax === 0) {
            return { error: `${line.name} has less than 1 remaining and cannot be released as a whole number.` };
        }

        const quantity = entry.quantity.trim();
        const amount = entry.amount.trim();
        const releasingQuantity = needsQuantity && !blank(quantity);
        const releasingAmount = needsAmount && !blank(amount);

        if (releasingQuantity && !isPositiveWhole(quantity)) {
            return { error: 'Released quantities and amounts must be whole numbers greater than zero.' };
        }

        if (releasingAmount && !isPositiveWhole(amount)) {
            return { error: 'Released quantities and amounts must be whole numbers greater than zero.' };
        }

        if (releasingQuantity && quantityRemaining != null && Number(quantity) > quantityRemaining) {
            return { error: 'The released quantity exceeds the remaining quantity.' };
        }

        if (releasingAmount && amountRemaining != null && Number(amount) > amountRemaining) {
            return { error: 'The released amount exceeds the remaining amount.' };
        }

        if (needsQuantity && needsAmount && releasingQuantity !== releasingAmount) {
            return { error: 'Enter both the quantity and the amount for each line you release.' };
        }

        if (!releasingQuantity && !releasingAmount) {
            partial = true;
            continue;
        }

        if (
            (needsQuantity && quantityRemaining != null && Number(quantity) < quantityRemaining) ||
            (needsAmount && amountRemaining != null && Number(amount) < amountRemaining)
        ) {
            partial = true;
        }

        chosen.push({
            id: line.id,
            name: line.name,
            actual_quantity: releasingQuantity ? String(Number(quantity)) : null,
            actual_amount: releasingAmount ? String(Number(amount)) : null,
        });
    }

    return { lines: chosen, partial };
}

export function validateRelease(draft: ReleaseDraft): FieldErrors {
    const errors: FieldErrors = {};
    const name = draft.receivedByName.trim();

    if (name === '') {
        errors.received_by_name = 'Enter the name of the person receiving assistance.';
    } else if (name.length > 255) {
        errors.received_by_name = 'Name must be 255 characters or fewer.';
    }

    if (!VERIFICATION_METHODS.has(draft.verificationMethod)) {
        errors.verification_method = 'Choose how identity was checked.';
    }

    if (!draft.identityVerified) {
        errors.identity_verified = 'Confirm the recipient before releasing assistance.';
    }

    if (!draft.privacyAcknowledged) {
        errors.privacy_acknowledged = 'Acknowledge the privacy notice before releasing assistance.';
    }

    if (draft.receivedByType === 'representative') {
        if (draft.representativeRelationship.trim() === '' || draft.representativeVerification.trim() === '') {
            errors.representative_relationship =
                'Record the representative relationship and how the authorization was checked.';
        }
    }

    if (draft.representativeRelationship.length > 255) {
        errors.representative_relationship = 'Relationship must be 255 characters or fewer.';
    }

    if (draft.representativeVerification.length > 2000) {
        errors.representative_verification = 'Verification details must be 2000 characters or fewer.';
    }

    if (draft.acknowledgmentMode === 'signature' && !draft.hasSignature) {
        errors.acknowledgment_mode = 'Capture a signature, or record an alternative acknowledgment.';
    }

    if (draft.acknowledgmentMode === 'alternative' && draft.alternativeReason.trim() === '') {
        errors.alternative_reason = 'Record why the recipient cannot sign and which alternative was used.';
    }

    if (draft.alternativeReason.length > 2000) {
        errors.alternative_reason = 'Alternative acknowledgment must be 2000 characters or fewer.';
    }

    const waiver = draft.cameraWaiverReason.trim();

    if (!draft.hasPhoto && waiver === '') {
        errors.live_photo = 'Take a live photo. If the camera cannot be used, an authorized officer must record the waiver.';
    }

    if (!draft.hasPhoto && waiver !== '' && !draft.canWaive) {
        errors.camera_waiver_reason = 'You are not authorized to waive live photo evidence.';
    }

    if (waiver.length > 2000) {
        errors.camera_waiver_reason = 'Waiver reason must be 2000 characters or fewer.';
    }

    if (draft.witnessedBy.trim() !== '') {
        const witnessId = Number(draft.witnessedBy);

        if (!Number.isInteger(witnessId) || !draft.officerIds.includes(witnessId)) {
            errors.witnessed_by = 'Choose a witness from this department.';
        }
    }

    if (draft.notes.length > 2000) {
        errors.notes = 'Notes must be 2000 characters or fewer.';
    }

    const collected = collectLines(draft.lines, draft.entries);

    if ('error' in collected) {
        errors.lines = collected.error;
    } else if (collected.lines.length === 0) {
        errors.lines = 'Enter the quantity or amount being released.';
    } else if (collected.partial && draft.notes.trim() === '') {
        errors.notes = 'A partial release needs a documented reason.';
    }

    return errors;
}

export function validateAbsent(remarks: string): FieldErrors {
    const trimmed = remarks.trim();

    if (trimmed === '') {
        return { remarks: 'Record why the beneficiary is absent.' };
    }

    if (trimmed.length > 2000) {
        return { remarks: 'Remarks must be 2000 characters or fewer.' };
    }

    return {};
}

export function defaultEntries(lines: PackageLine[]): Record<number, LineEntry> {
    const entries: Record<number, LineEntry> = {};

    for (const line of lines) {
        const quantityRemaining = positiveAmount(line.remaining_quantity);
        const amountRemaining = positiveAmount(line.remaining_amount);
        const quantityMax = quantityRemaining == null ? null : maxWhole(quantityRemaining);
        const amountMax = amountRemaining == null ? null : maxWhole(amountRemaining);

        entries[line.id] = {
            quantity: quantityMax != null && quantityMax > 0 ? String(quantityMax) : '',
            amount: amountMax != null && amountMax > 0 ? String(amountMax) : '',
        };
    }

    return entries;
}
