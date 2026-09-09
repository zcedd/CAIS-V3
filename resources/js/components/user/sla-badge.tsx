import { cn } from '@/lib/utils';

const SLA_LABELS: Record<string, string> = {
    on_time: 'On time',
    due_soon: 'Due soon',
    overdue: 'Overdue',
    paused: 'Paused',
    none: 'No SLA',
};

export function slaLabel(state?: string | null): string {
    return SLA_LABELS[state ?? 'none'] ?? 'No SLA';
}

export function SlaBadge({
    state,
    className,
}: {
    state?: string | null;
    className?: string;
}) {
    const value = state ?? 'none';

    return (
        <span
            className={cn(
                value === 'overdue'
                    ? 'font-medium text-destructive'
                    : value === 'due_soon'
                      ? 'font-medium text-amber-600 dark:text-amber-400'
                      : 'text-muted-foreground',
                className,
            )}
        >
            {slaLabel(value)}
        </span>
    );
}
