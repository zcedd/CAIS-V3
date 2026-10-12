import { router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { summarizeMessage } from '@/lib/notification-utils';
import { cn } from '@/lib/utils';
import {
    index as notificationsIndex,
    readAll as markAllNotificationsRead,
    show as notificationShow,
} from '@/routes/user/notifications';
import type { Auth } from '@/types/auth';
import type { NotificationEntry } from '@/types/notification';

type HeaderNotificationsProps = {
    auth: Auth;
    unreadNotificationsCount: number;
    recentNotifications?: NotificationEntry[];
};

function relativeTime(timestamp: string | null): string {
    if (!timestamp) {
        return '';
    }

    const date = new Date(timestamp);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const divisions: [number, Intl.RelativeTimeFormatUnit][] = [
        [60, 'second'],
        [60, 'minute'],
        [24, 'hour'],
        [7, 'day'],
        [4.34524, 'week'],
        [12, 'month'],
        [Number.POSITIVE_INFINITY, 'year'],
    ];

    let duration = (date.getTime() - Date.now()) / 1000;
    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });

    for (const [amount, unit] of divisions) {
        if (Math.abs(duration) < amount) {
            return formatter.format(Math.round(duration), unit);
        }

        duration /= amount;
    }

    return '';
}

function unreadLabel(count: number): string {
    if (count > 99) {
        return '99+';
    }

    return String(count);
}

export function HeaderNotifications() {
    const { auth, unreadNotificationsCount, recentNotifications } =
        usePage<HeaderNotificationsProps>().props;
    const slug = auth.user?.department?.slug;
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [markingAll, setMarkingAll] = useState(false);

    if (!slug) {
        return null;
    }

    const notifications = recentNotifications ?? [];

    function handleOpenChange(next: boolean) {
        setOpen(next);

        if (!next) {
            return;
        }

        setLoading(true);

        router.reload({
            only: ['recentNotifications', 'unreadNotificationsCount'],
            onFinish: () => {
                setLoading(false);
            },
        });
    }

    function handleMarkAllAsRead() {
        if (!slug) {
            return;
        }

        setMarkingAll(true);

        router.patch(
            markAllNotificationsRead.url(slug),
            {},
            {
                only: ['unreadNotificationsCount', 'recentNotifications'],
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('All notifications marked as read.');
                },
                onFinish: () => {
                    setMarkingAll(false);
                },
            },
        );
    }

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="relative"
                            aria-label={
                                unreadNotificationsCount > 0
                                    ? `Notifications, ${unreadNotificationsCount} unread`
                                    : 'Notifications'
                            }
                            data-test="header-notifications"
                        >
                            <Bell />
                            <span className="sr-only">Notifications</span>
                            {unreadNotificationsCount > 0 && (
                                <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-medium text-primary-foreground">
                                    {unreadLabel(unreadNotificationsCount)}
                                </span>
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>Notifications</TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between gap-2 border-b px-3 py-2">
                    <p className="text-sm font-medium">Notifications</p>
                    {unreadNotificationsCount > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={markingAll}
                            onClick={handleMarkAllAsRead}
                        >
                            <CheckCheck />
                            {markingAll ? 'Marking...' : 'Mark all read'}
                        </Button>
                    )}
                </div>

                <div className="max-h-80 overflow-y-auto">
                    {loading ? (
                        <div className="space-y-2 p-3">
                            {Array.from({ length: 3 }).map((_, index) => (
                                <div
                                    key={index}
                                    className="h-10 animate-pulse rounded-md bg-muted"
                                />
                            ))}
                        </div>
                    ) : notifications.length === 0 ? (
                        <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                            You're all caught up
                        </p>
                    ) : (
                        notifications.map((notification) => {
                            const unread = notification.read_at === null;

                            return (
                                <DropdownMenuItem
                                    key={notification.id}
                                    asChild
                                    className="items-start rounded-none px-3 py-2"
                                >
                                    <a
                                        href={notificationShow.url({
                                            department: slug,
                                            notification: notification.id,
                                        })}
                                        onClick={(event) => {
                                            event.preventDefault();
                                            router.visit(
                                                notificationShow.url({
                                                    department: slug,
                                                    notification:
                                                        notification.id,
                                                }),
                                            );
                                        }}
                                    >
                                        <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                            <span className="flex items-center gap-2">
                                                <span
                                                    className={cn(
                                                        'size-1.5 shrink-0 rounded-full',
                                                        unread
                                                            ? 'bg-primary'
                                                            : 'bg-transparent',
                                                    )}
                                                />
                                                <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                                    {notification.title}
                                                </span>
                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                    {relativeTime(
                                                        notification.created_at,
                                                    )}
                                                </span>
                                            </span>
                                            <span className="truncate pl-3.5 text-xs text-muted-foreground">
                                                {summarizeMessage(
                                                    notification.message,
                                                    80,
                                                )}
                                            </span>
                                        </span>
                                    </a>
                                </DropdownMenuItem>
                            );
                        })
                    )}
                </div>

                <div className="border-t px-3 py-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-full"
                        onClick={() => {
                            router.visit(notificationsIndex.url(slug));
                        }}
                    >
                        View all notifications
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
