export function positiveAmount(value: string | null | undefined): number | null {
    if (value == null) {
        return null;
    }

    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const amount = Number(trimmed);

    if (!Number.isFinite(amount) || amount <= 0) {
        return null;
    }

    return amount;
}

export function maxWhole(remaining: number): number {
    return Math.floor(remaining + 1e-9);
}

export function isPositiveWhole(value: string): boolean {
    return /^[1-9]\d*$/.test(value.trim());
}

export function formatDecimal(value: string | null | undefined): string {
    if (value == null || value.trim() === '') {
        return '—';
    }

    return value;
}
