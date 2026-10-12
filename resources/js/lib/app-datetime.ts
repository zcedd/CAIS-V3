const NAIVE_DATE_TIME = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/;

/**
 * Parse a timestamp from the server.
 * Offset strings are absolute instants. A wall-clock value with no offset is
 * Asia/Manila, which is the application timezone.
 */
export function parseAppDateTime(value: string): Date {
    const trimmed = value.trim();

    if (trimmed === '') {
        return new Date(Number.NaN);
    }

    if (/[zZ]$|[+-]\d{2}:\d{2}$/.test(trimmed)) {
        return new Date(trimmed);
    }

    const match = NAIVE_DATE_TIME.exec(trimmed);

    if (match === null) {
        return new Date(trimmed);
    }

    const [, year, month, day, hours, minutes, seconds = '00'] = match;

    return new Date(
        `${year}-${month}-${day}T${hours}:${minutes}:${seconds}+08:00`,
    );
}

/**
 * Send the picked local time with its offset so the server stores that instant.
 */
export function formatDateTimeForSubmit(
    date: Date | undefined,
): string | undefined {
    if (!date || Number.isNaN(date.getTime())) {
        return undefined;
    }

    const pad = (value: number): string => String(value).padStart(2, '0');
    const offsetMinutes = -date.getTimezoneOffset();
    const sign = offsetMinutes >= 0 ? '+' : '-';
    const absoluteOffset = Math.abs(offsetMinutes);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}${sign}${pad(Math.floor(absoluteOffset / 60))}:${pad(absoluteOffset % 60)}`;
}
