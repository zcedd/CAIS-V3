import { cn } from '@/lib/utils';
import { stripHtmlTags, summarizeMessage } from '@/lib/notification-utils';

type NotificationMessageProps = {
    message: string;
    className?: string;
    variant?: 'full' | 'summary';
};

export function NotificationMessage({
    message,
    className,
    variant = 'full',
}: NotificationMessageProps) {
    const text = stripHtmlTags(message).trim();

    if (text === '') {
        return (
            <p className={cn('text-sm text-muted-foreground', className)}>
                No message provided.
            </p>
        );
    }

    if (variant === 'summary') {
        return (
            <p className={cn('text-sm text-muted-foreground', className)}>
                {summarizeMessage(message)}
            </p>
        );
    }

    return (
        <p className={cn('whitespace-pre-wrap text-sm text-foreground', className)}>
            {text}
        </p>
    );
}
