import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { NotificationCard } from '@/pages/user/notifications/notification-card';
import { NotificationMarkAllReadButton } from '@/pages/user/notifications/notification-mark-all-read-button';
import {
    index as departmentNotificationsIndex,
} from '@/routes/user/notifications';
import type {
    BreadcrumbItem,
    DepartmentSummary,
    NotificationEntry,
} from '@/types';
import { Head, InfiniteScroll, setLayoutProps, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect } from 'react';

type PaginatedNotifications = {
    data: NotificationEntry[];
};

type UserNotificationsIndexProps = {
    department: DepartmentSummary;
    notifications: PaginatedNotifications;
    unreadNotificationsCount: number;
};

function NotificationCardSkeleton() {
    return (
        <Card size="sm" className="border border-border/60">
            <CardHeader className="gap-3">
                <Skeleton className="h-5 w-48" />
                <Skeleton className="h-4 w-36" />
            </CardHeader>
            <CardContent className="space-y-2">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-3/4" />
            </CardContent>
            <CardFooter className="gap-2">
                <Skeleton className="h-8 w-20" />
                <Skeleton className="h-8 w-24" />
            </CardFooter>
        </Card>
    );
}

export default function UserNotificationsIndex({
    department,
    notifications,
}: UserNotificationsIndexProps) {
    const { unreadNotificationsCount } = usePage<UserNotificationsIndexProps>().props;
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Notifications',
                    href: departmentNotificationsIndex(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    return (
        <>
            <Head title={`Notifications — ${department.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Notifications
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Database notifications for {department.name}.
                        </p>
                    </div>
                    <NotificationMarkAllReadButton
                        department={department}
                        unreadCount={unreadNotificationsCount}
                    />
                </div>

                {notifications.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-2 py-10 text-center">
                            <Bell className="size-5 text-muted-foreground" />
                            <p className="font-medium">No notifications yet</p>
                            <p className="text-sm text-muted-foreground">
                                New updates will appear here.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <InfiniteScroll
                        data="notifications"
                        onlyNext
                        next={({ loading }) =>
                            loading ? (
                                <div className="mt-3 space-y-3">
                                    <NotificationCardSkeleton />
                                    <NotificationCardSkeleton />
                                </div>
                            ) : null
                        }
                    >
                        <div className="space-y-3">
                            {notifications.data.map((notification) => (
                                <NotificationCard
                                    key={notification.id}
                                    department={department}
                                    notification={notification}
                                />
                            ))}
                        </div>
                    </InfiniteScroll>
                )}
            </div>
        </>
    );
}
