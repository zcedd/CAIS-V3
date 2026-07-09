import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    formatTimestamp,
    isInternalUrl,
    notificationCategoryLabel,
} from '@/lib/notification-utils';
import { NotificationMessage } from '@/pages/user/notifications/notification-message';
import { show as departmentNotificationShow } from '@/routes/user/notifications';
import type { DepartmentSummary, NotificationEntry } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, Eye } from 'lucide-react';

type NotificationCardProps = {
    department: DepartmentSummary;
    notification: NotificationEntry;
};

export function NotificationCard({
    department,
    notification,
}: NotificationCardProps) {
    const isRead = notification.read_at !== null;

    return (
        <Card size="sm" className="border border-border/60">
            <CardHeader className="gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle>{notification.title}</CardTitle>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">
                            {notificationCategoryLabel(notification.category)}
                        </Badge>
                        <Badge variant={isRead ? 'secondary' : 'default'}>
                            {isRead ? 'Read' : 'Unread'}
                        </Badge>
                    </div>
                </div>
                <CardDescription>
                    Received at {formatTimestamp(notification.created_at)}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm text-muted-foreground">
                <NotificationMessage
                    message={notification.message}
                    variant="summary"
                />
                {notification.read_at ? (
                    <p className="text-xs">
                        Read at {formatTimestamp(notification.read_at)}
                    </p>
                ) : null}
            </CardContent>
            <CardFooter className="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" asChild>
                    <Link
                        href={departmentNotificationShow.url({
                            department: department.slug,
                            notification: notification.id,
                        })}
                    >
                        <Eye className="size-4" />
                        View
                    </Link>
                </Button>
                {notification.url ? (
                    isInternalUrl(notification.url) ? (
                        <Button size="sm" asChild>
                            <Link href={notification.url}>
                                <ArrowUpRight className="size-4" />
                                Open link
                            </Link>
                        </Button>
                    ) : (
                        <Button size="sm" asChild>
                            <a
                                href={notification.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ArrowUpRight className="size-4" />
                                Open link
                            </a>
                        </Button>
                    )
                ) : null}
            </CardFooter>
        </Card>
    );
}
