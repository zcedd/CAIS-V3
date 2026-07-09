import { cn } from '@/lib/utils';
import { summarizeMessage } from '@/lib/notification-utils';

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
    if (message.trim() === '') {
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
        <div
            className={cn(
                'text-sm text-foreground [&_a]:text-primary [&_a]:underline [&_li]:ml-4 [&_ol]:list-decimal [&_p+p]:mt-2 [&_ul]:list-disc',
                className,
            )}
            dangerouslySetInnerHTML={{ __html: message }}
        />
    );
}
