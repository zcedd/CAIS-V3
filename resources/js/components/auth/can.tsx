import type { ReactNode } from 'react';
import { useCan } from '@/hooks/use-can';

type CanProps = {
    permission: string | string[];
    mode?: 'any' | 'all';
    children: ReactNode;
};

export function Can({ permission, mode = 'any', children }: CanProps) {
    const { canAny, canAll } = useCan();
    const required = Array.isArray(permission) ? permission : [permission];
    const allowed = mode === 'all' ? canAll(required) : canAny(required);

    if (!allowed) {
        return null;
    }

    return <>{children}</>;
}
