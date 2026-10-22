export type MobileUser = {
    id: number;
    name: string;
    email: string;
    department_id: number | null;
};

export type Department = {
    id: number;
    name: string;
    slug: string;
};

export type AuthSession = {
    token: string;
    tokenType: string;
    user: MobileUser;
};

export type TwoFactorChallenge = {
    twoFactor: true;
    challengeToken: string;
};

export type LoginResult = AuthSession | TwoFactorChallenge;

export type AccountProfile = {
    user: MobileUser;
    department: Department | null;
    permissions: string[];
};

export type LineKind = 'goods' | 'cash' | 'service';

export type PackageLine = {
    id: number;
    name: string;
    kind: LineKind;
    unit: string | null;
    approved_quantity: string | null;
    approved_amount: string | null;
    released_quantity: string | null;
    released_amount: string | null;
    remaining_quantity: string | null;
    remaining_amount: string | null;
};

export type ReleaseStatus = 'pending' | 'partial' | 'served' | 'absent' | 'excluded';

export type PackageItem = {
    id: number;
    assistance_id: number;
    name: string;
    cais_number: string | null;
    approval_status: string;
    release_status: ReleaseStatus;
    lines: PackageLine[];
};

export type Officer = {
    id: number;
    name: string;
};

export type BatchPermissions = {
    execute: boolean;
    waive: boolean;
    issueReceipt: boolean;
};

export type RemoteBatch = {
    id: number;
    batch_code: string;
    title: string;
    status: string;
    status_label: string;
    program: string | null;
    venue: string | null;
    venue_details: string | null;
    scheduled_at: string | null;
};

export type PackageBatch = RemoteBatch & {
    department_id: number | null;
};

export type OfflinePackage = {
    batch: PackageBatch;
    items: PackageItem[];
    officers: Officer[];
    can: BatchPermissions;
    privacy_notice: string;
    retention_notice: string;
    server_time: string | null;
};

export type ReleaseLinePayload = {
    id: number;
    name: string;
    actual_quantity: string | null;
    actual_amount: string | null;
};

export type ReleasePayload = {
    received_by_type: 'beneficiary' | 'representative';
    received_by_name: string;
    representative_relationship: string;
    representative_verification: string;
    verification_method: 'control_number' | 'request_reference' | 'officer_attestation';
    acknowledgment_mode: 'signature' | 'alternative';
    alternative_reason: string;
    camera_waiver_reason: string;
    notes: string;
    witnessed_by: string;
    lines: ReleaseLinePayload[];
    photo_sha256: string | null;
    partial: boolean;
    beneficiary_name: string;
};

export type AbsentPayload = {
    remarks: string;
    beneficiary_name: string;
};

export type SyncStatus = 'applied' | 'already_applied';

export type ReleaseSyncResult = {
    status: SyncStatus;
    transaction_number: string;
    receipt_number: string | null;
    receipt_pdf_url: string;
};

export type AbsentSyncResult = {
    status: SyncStatus;
    distribution_release_id: number;
};

export type SyncResult = ReleaseSyncResult | AbsentSyncResult;

export function isReleaseSyncResult(result: SyncResult | null | undefined): result is ReleaseSyncResult {
    return result != null && 'transaction_number' in result;
}

export type OperationType = 'release' | 'absent';

export type OperationStatus = 'pending' | 'syncing' | 'applied' | 'conflict';
