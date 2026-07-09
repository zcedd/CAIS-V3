import { Button } from '@/components/ui/button';
import { readAll as markAllNotificationsRead } from '@/routes/user/notifications';
import type { DepartmentSummary } from '@/types';
import { router } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type NotificationMarkAllReadButtonProps = {
    department: DepartmentSummary;
    unreadCount: number;
};

export function NotificationMarkAllReadButton({
    department,
    unreadCount,
}: NotificationMarkAllReadButtonProps) {
    const [processing, setProcessing] = useState(false);

    if (unreadCount === 0) {
        return null;
    }

    function handleMarkAllAsRead() {
        setProcessing(true);

        router.patch(
            markAllNotificationsRead.url({
                department: department.slug,
            }),
            {},
            {
                only: ['notifications', 'unreadNotificationsCount'],
                reset: ['notifications'],
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('All notifications marked as read.');
                },
                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    }

    return (
        <Button
            type="button"
            variant="outline"
            disabled={processing}
            onClick={handleMarkAllAsRead}
        >
            <CheckCheck className="size-4" />
            {processing ? 'Marking all...' : 'Mark all as read'}
        </Button>
    );
}
