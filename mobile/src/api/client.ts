import { ApiError } from '@/api/errors';
import { readErrorBody } from '@/api/parse';
import { getApiBaseUrl } from '@/db/settings';

const TOKEN_KEY = 'cais.mobile.token';

let tokenReader: (() => Promise<string | null>) | null = null;
let unauthorizedHandler: (() => void) | null = null;

export function setTokenReader(reader: () => Promise<string | null>): void {
    tokenReader = reader;
}

export function setUnauthorizedHandler(handler: () => void): void {
    unauthorizedHandler = handler;
}

export function mobilePath(path: string): string {
    return path.startsWith('/') ? path : `/${path}`;
}

async function readBody(response: Response): Promise<unknown> {
    const text = await response.text();

    if (text.trim() === '') {
        return null;
    }

    try {
        return JSON.parse(text) as unknown;
    } catch {
        return { message: text };
    }
}

export async function apiRequest(path: string, init: RequestInit = {}): Promise<unknown> {
    const baseUrl = await getApiBaseUrl();
    const token = tokenReader ? await tokenReader() : null;
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');

    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    let response: Response;

    try {
        response = await fetch(`${baseUrl}/api/v1/mobile${mobilePath(path)}`, {
            ...init,
            headers,
        });
    } catch (error) {
        const message = error instanceof Error ? error.message : 'The network request failed.';

        throw new ApiError(0, message);
    }

    if (response.status === 401 && token) {
        unauthorizedHandler?.();
        throw new ApiError(401, 'Your session has ended. Sign in again.');
    }

    if (response.status === 204) {
        return null;
    }

    const body = await readBody(response);

    if (response.status === 422) {
        const parsed = readErrorBody(body);

        throw new ApiError(422, parsed.message, parsed.errors);
    }

    if (!response.ok) {
        const parsed = readErrorBody(body);

        throw new ApiError(response.status, parsed.message, parsed.errors);
    }

    return body;
}

export async function readAccessToken(): Promise<string | null> {
    return tokenReader ? tokenReader() : null;
}

export function notifyUnauthorized(): void {
    unauthorizedHandler?.();
}

export async function apiJson<TBody>(path: string, body: TBody, method = 'POST'): Promise<unknown> {
    return apiRequest(path, {
        method,
        body: JSON.stringify(body),
    });
}

export { TOKEN_KEY };
