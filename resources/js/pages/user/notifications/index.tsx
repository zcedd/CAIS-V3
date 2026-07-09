import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { NotificationCard } from '@/pages/user/notifications/notification-card';
import {
    index as departmentNotificationsIndex,
} from '@/routes/user/notifications';
import type {
    BreadcrumbItem,
    DepartmentSummary,
    NotificationPagination,
} from '@/types';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect } from 'react';

type UserNotificationsIndexProps = {
    department: DepartmentSummary;
    notifications: NotificationPagination;
};

export default function UserNotificationsIndex({
    department,
    notifications,
}: UserNotificationsIndexProps) {
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
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Notifications
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Database notifications for {department.name}.
                    </p>
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
                    <div className="space-y-3">
                        {notifications.data.map((notification) => (
                            <NotificationCard
                                key={notification.id}
                                department={department}
                                notification={notification}
                            />
                        ))}
                    </div>
                )}

                {notifications.links.length > 3 ? (
                    <div className="flex flex-wrap gap-2">
                        {notifications.links.map((link) =>
                            link.url ? (
                                <Button
                                    key={`${link.label}-${link.url}`}
                                    variant={link.active ? 'default' : 'outline'}
                                    asChild
                                    size="sm"
                                >
                                    <Link href={link.url} preserveScroll>
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    key={`${link.label}-disabled`}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                >
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ),
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}
