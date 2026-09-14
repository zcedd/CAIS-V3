import type { NotificationEntry } from '@/types';
import { EMPTY_CELL } from '@/lib/empty-cell';

export function formatTimestamp(timestamp: string | null): string {
    if (!timestamp) {
        return EMPTY_CELL;
    }

    const date = new Date(timestamp);

    if (Number.isNaN(date.getTime())) {
        return EMPTY_CELL;
    }

    return date.toLocaleString();
}

export function summarizeMessage(message: string, maxLength = 160): string {
    const text = stripHtmlTags(message).trim();

    if (text === '') {
        return 'No message provided.';
    }

    if (text.length <= maxLength) {
        return text;
    }

    return `${text.slice(0, maxLength).trim()}…`;
}

export function stripHtmlTags(value: string): string {
    return value.replace(/<[^>]*>/g, '');
}

export function isInternalUrl(url: string): boolean {
    return url.startsWith('/') && !url.startsWith('//');
}

export function formatNotificationData(data: Record<string, unknown>): string {
    return JSON.stringify(data, null, 2);
}

export function notificationCategoryLabel(
    category: NotificationEntry['category'],
): string {
    return category === 'system' ? 'System' : 'Personal';
}
