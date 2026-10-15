import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import {
    hasAllPermissions,
    hasAnyPermission,
    hasPermission,
} from '@/lib/permissions';
import type { Auth } from '@/types/auth';

type CanPageProps = {
    auth?: Auth;
};

export type CanChecker = {
    can: (permission: string) => boolean;
    canAny: (permissions: string[]) => boolean;
    canAll: (permissions: string[]) => boolean;
};

export function useCan(): CanChecker {
    const { auth } = usePage<CanPageProps>().props;
    const permissions = auth?.permissions;
    const isSuperAdmin = auth?.is_super_admin === true;

    const can = useCallback(
        (permission: string): boolean =>
            hasPermission(permissions, permission, isSuperAdmin),
        [isSuperAdmin, permissions],
    );

    const canAny = useCallback(
        (required: string[]): boolean =>
            hasAnyPermission(permissions, required, isSuperAdmin),
        [isSuperAdmin, permissions],
    );

    const canAll = useCallback(
        (required: string[]): boolean =>
            hasAllPermissions(permissions, required, isSuperAdmin),
        [isSuperAdmin, permissions],
    );

    return { can, canAny, canAll };
}
