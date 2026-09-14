import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { hasPermission } from '@/lib/permissions';
import type { Auth } from '@/types/auth';

type CanPageProps = {
    auth: Auth;
};

export function useCan(): (permission: string) => boolean {
    const { auth } = usePage<CanPageProps>().props;

    return useCallback(
        (permission: string): boolean =>
            hasPermission(auth.permissions, permission, auth.is_super_admin),
        [auth.is_super_admin, auth.permissions],
    );
}
