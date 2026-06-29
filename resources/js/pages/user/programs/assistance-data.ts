import {
    CheckCircle2,
    Circle,
    CircleX,
    PackageCheck,
} from 'lucide-react';

export const assistanceStatuses = [
    {
        value: 'Pending',
        label: 'Pending',
        icon: Circle,
    },
    {
        value: 'Verified',
        label: 'Verified',
        icon: PackageCheck,
    },
    {
        value: 'Delivered',
        label: 'Delivered',
        icon: CheckCircle2,
    },
    {
        value: 'Denied',
        label: 'Denied',
        icon: CircleX,
    },
] as const;
