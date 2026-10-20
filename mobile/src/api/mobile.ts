import * as Device from 'expo-device';
import { File } from 'expo-file-system';
import { Platform } from 'react-native';

import { apiJson, apiRequest, notifyUnauthorized, readAccessToken } from '@/api/client';
import { readAbsentSyncResult, readBatchList, readLogin, readPackage, readProfile, readReleaseSyncResult } from '@/api/parse';
import type { AbsentSyncResult, AccountProfile, LoginResult, OfflinePackage, ReleasePayload, ReleaseSyncResult, RemoteBatch } from '@/api/types';
import type { OperationRecord } from '@/db/records';
import { getApiBaseUrl } from '@/db/settings';

function appendFile(form: FormData, field: string, uri: string): void {
    // Expo fetch reads a filesystem File (name, type, and bytes). A { uri, name, type } part is rejected.
    form.append(field, new File(uri));
}

export function deviceName(): string {
    const model = Device.modelName ?? Platform.OS;

    return `CAIS Field ${model}`.slice(0, 255);
}

export async function login(email: string, password: string): Promise<LoginResult> {
    const body = await apiJson('/login', {
        email,
        password,
        device_name: deviceName(),
    });

    return readLogin(body);
}

export async function verifyTwoFactor(challengeToken: string, code: string): Promise<LoginResult> {
    const body = await apiJson('/two-factor', {
        challenge_token: challengeToken,
        code,
        device_name: deviceName(),
    });

    return readLogin(body);
}

export async function logout(): Promise<void> {
    await apiRequest('/logout', { method: 'POST' });
}

export async function fetchMe(): Promise<AccountProfile> {
    return readProfile(await apiRequest('/me'));
}

export async function fetchDistributions(): Promise<RemoteBatch[]> {
    return readBatchList(await apiRequest('/distributions'));
}

export async function fetchPackage(batchId: number): Promise<OfflinePackage> {
    return readPackage(await apiRequest(`/distributions/${batchId}/package`));
}

export async function postRelease(operation: OperationRecord): Promise<ReleaseSyncResult> {
    const payload = operation.payload as ReleasePayload;
    const form = new FormData();
    form.append('client_operation_id', operation.clientOperationId);
    form.append('idempotency_key', operation.idempotencyKey);
    form.append('captured_at', operation.capturedAt);
    form.append('received_by_type', payload.received_by_type);
    form.append('received_by_name', payload.received_by_name);
    form.append('representative_relationship', payload.representative_relationship);
    form.append('representative_verification', payload.representative_verification);
    form.append('verification_method', payload.verification_method);
    form.append('identity_verified', '1');
    form.append('acknowledgment_mode', payload.acknowledgment_mode);
    form.append('alternative_reason', payload.alternative_reason);
    form.append('privacy_acknowledged', '1');
    form.append('camera_waiver_reason', payload.camera_waiver_reason);
    form.append('notes', payload.notes);
    form.append('witnessed_by', payload.witnessed_by);
    payload.lines.forEach((line, index) => {
        form.append(`lines[${index}][id]`, String(line.id));

        if (line.actual_quantity) {
            form.append(`lines[${index}][actual_quantity]`, line.actual_quantity);
        }

        if (line.actual_amount) {
            form.append(`lines[${index}][actual_amount]`, line.actual_amount);
        }
    });

    if (operation.livePhotoPath) {
        appendFile(form, 'live_photo', operation.livePhotoPath);
    }

    if (operation.signaturePath) {
        appendFile(form, 'signature', operation.signaturePath);
    }

    const body = await apiRequest(
        `/distributions/${operation.batchId}/items/${operation.itemId}/releases`,
        { method: 'POST', body: form },
    );

    return readReleaseSyncResult(body);
}

export async function postAbsent(operation: OperationRecord): Promise<AbsentSyncResult> {
    const remarks = 'remarks' in operation.payload ? operation.payload.remarks : '';
    const body = await apiJson(`/distributions/${operation.batchId}/items/${operation.itemId}/absent`, {
        client_operation_id: operation.clientOperationId,
        remarks,
        captured_at: operation.capturedAt,
    });

    return readAbsentSyncResult(body);
}

function receiptRequestUrl(url: string, baseUrl: string): string {
    if (!url.startsWith('http')) {
        return `${baseUrl}${url.startsWith('/') ? '' : '/'}${url}`;
    }

    try {
        const parsed = new URL(url);

        if (!parsed.pathname.includes('/api/v1/mobile/')) {
            return url;
        }

        const base = new URL(baseUrl);

        return `${base.origin}${parsed.pathname}${parsed.search}`;
    } catch {
        return url;
    }
}

export async function downloadReceipt(url: string): Promise<Uint8Array> {
    const token = await readAccessToken();
    const baseUrl = await getApiBaseUrl();
    const absolute = receiptRequestUrl(url, baseUrl);
    const headers = new Headers({ Accept: 'application/pdf' });

    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    let response: Response;

    try {
        response = await fetch(absolute, { headers });
    } catch (error) {
        const message = error instanceof Error ? error.message : 'The receipt could not be downloaded.';

        throw new Error(message);
    }

    if (response.status === 401 && token) {
        notifyUnauthorized();
        throw new Error('Your session has ended. Sign in again.');
    }

    if (!response.ok) {
        throw new Error(`The official receipt could not be downloaded (${response.status}).`);
    }

    return new Uint8Array(await response.arrayBuffer());
}
