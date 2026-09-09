export function formatProgramDate(
    value: string | null | undefined,
): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleDateString(undefined, {
        dateStyle: 'medium',
    });
}

export function formatProgramPeriod(
    startAt: string | null | undefined,
    endAt: string | null | undefined,
): string {
    if (!startAt) {
        return '—';
    }

    const start = formatProgramDate(startAt);

    if (!endAt) {
        return start;
    }

    return `${start} to ${formatProgramDate(endAt)}`;
}
