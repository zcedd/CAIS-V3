import { cn } from '@/lib/utils';

export type StatusPill = {
    value: string;
    label: string;
    count?: number;
    dotClassName?: string;
};

export function StatusPillTabs({
    pills,
    value,
    onChange,
}: {
    pills: StatusPill[];
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {pills.map((pill) => {
                const selected = pill.value === value;

                return (
                    <button
                        key={pill.value}
                        type="button"
                        onClick={() => onChange(pill.value)}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                            selected
                                ? 'border-foreground/20 bg-foreground text-background'
                                : 'border-border bg-card text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                        )}
                    >
                        {pill.dotClassName ? (
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    pill.dotClassName,
                                )}
                            />
                        ) : null}
                        <span>{pill.label}</span>
                        {pill.count !== undefined ? (
                            <span
                                className={cn(
                                    'tabular-nums',
                                    selected
                                        ? 'text-background/80'
                                        : 'text-muted-foreground',
                                )}
                            >
                                ({pill.count})
                            </span>
                        ) : null}
                    </button>
                );
            })}
        </div>
    );
}
