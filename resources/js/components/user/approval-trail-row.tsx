import { Check, Clock3, Undo2 } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatProgramDate } from '@/lib/format-program-period';
import { cn } from '@/lib/utils';

export type ApprovalTrailEvent = {
    action: 'submitted' | 'endorsed' | 'approved' | 'returned';
    label: string;
    done_label: string;
    remark: string | null;
    happened_at: string;
};

export function ApprovalTrail({
    events,
    className,
}: {
    events: ApprovalTrailEvent[];
    className?: string;
}) {
    const hasSubmission = events.some((event) => event.action === 'submitted');
    const hasApproval = events.some((event) => event.action === 'approved');

    return (
        <div className="space-y-2">
            {hasSubmission ? null : (
                <ApprovalTrailRow
                    className={className}
                    label="Department endorsement"
                    done={false}
                    doneLabel="Signed & endorsed"
                    when={null}
                />
            )}
            {events.map((event, index) => (
                <ApprovalTrailRow
                    key={`${event.action}-${event.happened_at}-${index}`}
                    className={className}
                    label={event.label}
                    done
                    doneLabel={event.done_label}
                    when={event.happened_at}
                    remark={event.remark}
                    returned={event.action === 'returned'}
                />
            ))}
            {hasApproval ? null : (
                <ApprovalTrailRow
                    className={className}
                    label="Governor sign-off"
                    done={false}
                    doneLabel="Governor approved"
                    when={null}
                />
            )}
        </div>
    );
}

export function ApprovalTrailRow({
    label,
    done,
    doneLabel,
    when,
    remark,
    returned = false,
    className,
}: {
    label: string;
    done: boolean;
    doneLabel: string;
    when: string | null;
    remark?: string | null;
    returned?: boolean;
    className?: string;
}) {
    return (
        <div className="space-y-1">
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
                            ? 'font-medium'
                            : 'text-muted-foreground',
                        done && returned && 'text-amber-700 dark:text-amber-400',
                        done && !returned && 'text-emerald-700 dark:text-emerald-400',
                    )}
                >
                    {done && when ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span className="inline-flex cursor-default items-center gap-1">
                                    {returned ? (
                                        <Undo2 className="size-3" />
                                    ) : (
                                        <Check className="size-3" />
                                    )}
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
            {remark ? (
                <p className="text-[11px] leading-snug font-normal whitespace-pre-wrap text-muted-foreground/60">
                    {remark}
                </p>
            ) : null}
        </div>
    );
}
