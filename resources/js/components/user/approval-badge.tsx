import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const approvalStyles: Record<string, { label: string; className: string }> = {
    approved: {
        label: 'Approved & Active',
        className:
            'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    },
    awaiting_governor: {
        label: 'Awaiting Governor Approval',
        className:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    },
    draft: {
        label: 'Awaiting Dept Review',
        className:
            'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-400',
    },
    returned: {
        label: 'Returned / Shelved',
        className: 'border-border bg-muted text-muted-foreground',
    },
};

export function ApprovalBadge({
    status,
    label,
    className,
}: {
    status: string | null | undefined;
    label?: string | null;
    className?: string;
}) {
    const style = approvalStyles[status ?? ''] ?? {
        label: label ?? 'Unspecified',
        className: 'border-border bg-muted text-muted-foreground',
    };

    return (
        <Badge variant="outline" className={cn(style.className, className)}>
            <span className="size-1.5 rounded-full bg-current" />
            {style.label}
        </Badge>
    );
}
