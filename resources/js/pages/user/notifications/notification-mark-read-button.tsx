import { Button } from '@/components/ui/button';
import { read as markNotificationRead } from '@/routes/user/notifications';
import type { DepartmentSummary, NotificationEntry } from '@/types';
import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type NotificationMarkReadButtonProps = {
    department: DepartmentSummary;
    notification: NotificationEntry;
    size?: 'default' | 'sm' | 'lg' | 'icon';
    variant?: 'default' | 'outline' | 'secondary' | 'ghost';
};

export function NotificationMarkReadButton({
    department,
    notification,
    size = 'sm',
    variant = 'outline',
}: NotificationMarkReadButtonProps) {
    const [processing, setProcessing] = useState(false);

    if (notification.read_at !== null) {
        return null;
    }

    function handleMarkAsRead() {
        setProcessing(true);

        router.patch(
            markNotificationRead.url({
                department: department.slug,
                notification: notification.id,
            }),
            {},
            {
                only: ['notification', 'notifications', 'unreadNotificationsCount'],
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Notification marked as read.');
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
            size={size}
            variant={variant}
            disabled={processing}
            onClick={handleMarkAsRead}
        >
            <Check className="size-4" />
            {processing ? 'Marking...' : 'Mark as read'}
        </Button>
    );
}
