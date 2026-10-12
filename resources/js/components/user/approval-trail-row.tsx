import { Check, Clock3 } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatProgramDate } from '@/lib/format-program-period';
import { cn } from '@/lib/utils';

export function ApprovalTrailRow({
    label,
    done,
    doneLabel,
    when,
    className,
}: {
    label: string;
    done: boolean;
    doneLabel: string;
    when: string | null;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-start justify-between gap-3 text-sm',
                className,
            )}
        >
            <span className="text-muted-foreground">{label}</span>
            <span
                className={cn(
                    'text-right',
                    done
                        ? 'text-emerald-700 dark:text-emerald-400'
                        : 'text-muted-foreground',
                )}
            >
                {done && when ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="inline-flex cursor-default items-center gap-1">
                                <Check className="size-3" />
                                {doneLabel}
                                <span className="sr-only">
                                    {formatProgramDate(when)}
                                </span>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {formatProgramDate(when)}
                        </TooltipContent>
                    </Tooltip>
                ) : (
                    <span className="inline-flex items-center gap-1">
                        {done ? (
                            <Check className="size-3" />
                        ) : (
                            <Clock3 className="size-3" />
                        )}
                        {done ? doneLabel : 'Pending'}
                    </span>
                )}
            </span>
        </div>
    );
}
