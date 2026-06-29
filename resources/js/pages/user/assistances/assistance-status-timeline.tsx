import { Badge } from '@/components/ui/badge';
import {
    Timeline,
    TimelineBody,
    TimelineHeader,
    TimelineIcon,
    TimelineItem,
    TimelineSeparator,
} from '@/components/ui/timeline';
import { cn } from '@/lib/utils';
import { Circle, CircleDot } from 'lucide-react';

export type AssistanceStatusTimelineEntry = {
    id: number;
    name: string;
    parent_status: string | null;
    remark: string | null;
    recorded_at: string;
};

function formatTimelineTimestamp(iso: string): { date: string; time: string } {
    const recorded = new Date(iso);

    if (Number.isNaN(recorded.getTime())) {
        return { date: iso, time: '' };
    }

    return {
        date: recorded.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }),
        time: recorded.toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        }),
    };
}

function sortEntriesChronologically(
    entries: AssistanceStatusTimelineEntry[],
): AssistanceStatusTimelineEntry[] {
    return [...entries].sort(
        (left, right) =>
            new Date(left.recorded_at).getTime() -
            new Date(right.recorded_at).getTime(),
    );
}

type AssistanceStatusTimelineProps = {
    entries: AssistanceStatusTimelineEntry[];
    className?: string;
};

export function AssistanceStatusTimeline({
    entries,
    className,
}: AssistanceStatusTimelineProps) {
    const timeline = sortEntriesChronologically(entries);

    if (timeline.length === 0) {
        return (
            <div
                className={cn(
                    'flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border px-6 py-10 text-center',
                    className,
                )}
            >
                <Circle className="h-8 w-8 text-muted-foreground/50" />
                <p className="text-sm font-medium text-foreground">
                    No status updates yet
                </p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    Sub-status changes for this assistance will appear here as
                    they are recorded.
                </p>
            </div>
        );
    }

    return (
        <Timeline className={className}>
            {timeline.map((entry, index) => {
                const isLatest = index === timeline.length - 1;
                const isFirst = index === 0;
                const { date, time } = formatTimelineTimestamp(
                    entry.recorded_at,
                );

                return (
                    <TimelineItem key={entry.id}>
                        <TimelineHeader>
                            {isLatest ? (
                                <TimelineIcon color="primary">
                                    <CircleDot strokeWidth={2.5} />
                                </TimelineIcon>
                            ) : (
                                <TimelineIcon />
                            )}
                            {isFirst && timeline.length > 1 ? (
                                <span className="mt-1 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                                    Start
                                </span>
                            ) : null}
                            {isLatest && timeline.length > 1 ? (
                                <span className="mt-1 text-[10px] font-medium tracking-wide text-primary uppercase">
                                    Latest
                                </span>
                            ) : null}
                            <TimelineSeparator />
                        </TimelineHeader>

                        <TimelineBody>
                            <div
                                className={cn(
                                    'rounded-lg border px-4 py-3',
                                    isLatest
                                        ? 'border-primary/30 bg-primary/5'
                                        : 'border-border bg-muted/20',
                                )}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <p className="font-medium leading-snug">
                                            {entry.name}
                                        </p>
                                        {entry.parent_status ? (
                                            <Badge
                                                variant={
                                                    isLatest
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className="w-fit font-normal"
                                            >
                                                {entry.parent_status}
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <time
                                        dateTime={entry.recorded_at}
                                        className="shrink-0 text-right text-xs text-muted-foreground"
                                    >
                                        <span className="block tabular-nums">
                                            {date}
                                        </span>
                                        {time ? (
                                            <span className="block tabular-nums">
                                                {time}
                                            </span>
                                        ) : null}
                                    </time>
                                </div>
                                {entry.remark?.trim() ? (
                                    <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                        {entry.remark}
                                    </p>
                                ) : null}
                            </div>
                        </TimelineBody>
                    </TimelineItem>
                );
            })}
        </Timeline>
    );
}
