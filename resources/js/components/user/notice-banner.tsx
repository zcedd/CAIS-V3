import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const tones = {
    blue: 'border-blue-500/30 bg-blue-500/10',
    amber: 'border-amber-500/30 bg-amber-500/10',
    emerald: 'border-emerald-500/30 bg-emerald-500/10',
} as const;

const iconTones = {
    blue: 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
    amber: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    emerald: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
} as const;

export function NoticeBanner({
    tone = 'blue',
    icon: Icon,
    title,
    description,
    action,
    className,
}: {
    tone?: keyof typeof tones;
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
                tones[tone],
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                {Icon ? (
                    <span
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            iconTones[tone],
                        )}
                    >
                        <Icon className="size-4" />
                    </span>
                ) : null}
                <div className="min-w-0">
                    <p className="text-sm font-medium">{title}</p>
                    {description ? (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
            </div>
            {action ? <div className="shrink-0">{action}</div> : null}
        </div>
    );
}
