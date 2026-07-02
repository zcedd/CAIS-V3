const INDEX_MODULES = [
    'dashboard',
    'programs',
    'beneficiaries',
    'items',
    'funds',
] as const;

export function normalizePath(path: string): string {
    if (path === '') {
        return '/';
    }

    if (path.endsWith('/')) {
        return path.slice(0, -1);
    }

    return path;
}

export function resolveTourRouteKey(pathname: string): string | null {
    const segments = normalizePath(pathname).split('/').filter(Boolean);

    if (segments.length === 0) {
        return null;
    }

    if (segments.length === 1 && segments[0] === 'dashboard') {
        return 'dashboard';
    }

    if (segments.length < 2) {
        return null;
    }

    const module = segments[1];

    if (segments.length === 2) {
        return INDEX_MODULES.includes(
            module as (typeof INDEX_MODULES)[number],
        )
            ? module
            : null;
    }

    const resource = segments[2];

    if (module === 'beneficiaries') {
        if (resource === 'create') {
            return 'beneficiaries/create';
        }

        if (resource === 'search') {
            return null;
        }

        return 'beneficiaries/show';
    }

    if (module === 'programs') {
        if (segments[3] === 'assistances') {
            if (segments[4] === 'export') {
                return null;
            }

            if (segments[5] === 'edit') {
                return 'assistances/edit';
            }

            if (segments.length >= 5) {
                return 'assistances/show';
            }
        }

        return 'programs/show';
    }

    return null;
}
