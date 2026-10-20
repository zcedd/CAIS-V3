import { getDatabase } from '@/db/database';

export const DEFAULT_API_URL =
    process.env.EXPO_PUBLIC_API_URL?.trim().replace(/\/+$/, '') || 'http://localhost:8000';

export function normalizeApiUrl(value: string): string {
    return value.trim().replace(/\/+$/, '');
}

export async function getSetting(key: string): Promise<string | null> {
    const db = await getDatabase();
    const row = await db.getFirstAsync<{ value: string }>('SELECT value FROM settings WHERE key = ?', key);

    return row?.value ?? null;
}

export async function setSetting(key: string, value: string): Promise<void> {
    const db = await getDatabase();
    await db.runAsync(
        'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
        key,
        value,
    );
}

export async function deleteSetting(key: string): Promise<void> {
    const db = await getDatabase();
    await db.runAsync('DELETE FROM settings WHERE key = ?', key);
}

export async function getApiBaseUrl(): Promise<string> {
    const stored = await getSetting('api_base_url');

    if (stored && stored.trim() !== '') {
        return normalizeApiUrl(stored);
    }

    return DEFAULT_API_URL;
}

export async function setApiBaseUrl(value: string): Promise<void> {
    const normalized = normalizeApiUrl(value);

    if (normalized === '' || normalized === DEFAULT_API_URL) {
        await deleteSetting('api_base_url');

        return;
    }

    await setSetting('api_base_url', normalized);
}
