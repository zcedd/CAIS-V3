import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function MetricRow({
    label,
    value,
    hint,
    className,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex items-baseline justify-between gap-3', className)}>
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-right font-mono text-xs font-medium tabular-nums">
                {value}
                {hint ? (
                    <span className="ml-1 font-sans text-[10px] font-normal text-muted-foreground">
                        {hint}
                    </span>
                ) : null}
            </span>
        </div>
    );
}
