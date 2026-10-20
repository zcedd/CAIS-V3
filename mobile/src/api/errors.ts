export class ApiError extends Error {
    readonly status: number;

    readonly errors: Record<string, string[]>;

    constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

export function formatApiError(error: ApiError): string {
    const details = Object.entries(error.errors)
        .flatMap(([field, messages]) => messages.map((message) => `${field}: ${message}`))
        .join('\n');

    if (details === '') {
        return error.message;
    }

    return `${error.message}\n${details}`;
}

export function isNetworkError(error: unknown): boolean {
    return error instanceof TypeError || (error instanceof ApiError && error.status === 0);
}
