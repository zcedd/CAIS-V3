import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    formatTimestamp,
    isInternalUrl,
    notificationCategoryLabel,
} from '@/lib/notification-utils';
import { NotificationMarkReadButton } from '@/pages/user/notifications/notification-mark-read-button';
import { NotificationMessage } from '@/pages/user/notifications/notification-message';
import {
    index as departmentNotificationsIndex,
    show as departmentNotificationShow,
} from '@/routes/user/notifications';
import type { BreadcrumbItem, DepartmentSummary, NotificationEntry } from '@/types';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight } from 'lucide-react';
import { useEffect } from 'react';

type UserNotificationShowProps = {
    department: DepartmentSummary;
    notification: NotificationEntry;
};

export default function UserNotificationShow({
    department,
    notification,
}: UserNotificationShowProps) {
    useEffect(() => {
        const notificationsHref = departmentNotificationsIndex.url(department.slug);

        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Notifications',
                    href: notificationsHref,
                },
                {
                    title: notification.title,
                    href: departmentNotificationShow.url({
                        department: department.slug,
                        notification: notification.id,
                    }),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug, notification.id, notification.title]);

    const isRead = notification.read_at !== null;

    return (
        <>
            <Head title={`${notification.title} — ${department.name}`} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <Button variant="ghost" size="sm" asChild className="w-fit px-0">
                            <Link
                                href={departmentNotificationsIndex.url(department.slug)}
                            >
                                <ArrowLeft className="size-4" />
                                Back to notifications
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {notification.title}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Received at {formatTimestamp(notification.created_at)}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">
                            {notificationCategoryLabel(notification.category)}
                        </Badge>
                        <Badge variant={isRead ? 'secondary' : 'default'}>
                            {isRead ? 'Read' : 'Unread'}
                        </Badge>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Message</CardTitle>
                        <CardDescription>
                            Full notification content
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <NotificationMessage message={notification.message} />
                        {notification.read_at ? (
                            <p className="text-muted-foreground">
                                Read at {formatTimestamp(notification.read_at)}
                            </p>
                        ) : null}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-2">
                    <NotificationMarkReadButton
                        department={department}
                        notification={notification}
                    />
                    {notification.url ? (
                        isInternalUrl(notification.url) ? (
                            <Button asChild>
                                <Link href={notification.url}>
                                    <ArrowUpRight className="size-4" />
                                    Open attached link
                                </Link>
                            </Button>
                        ) : (
                            <Button asChild>
                                <a
                                    href={notification.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ArrowUpRight className="size-4" />
                                    Open attached link
                                </a>
                            </Button>
                        )
                    ) : null}
                </div>
            </div>
        </>
    );
}
