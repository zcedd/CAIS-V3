import type { InertiaLinkProps } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';

export type IsCurrentUrlFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl?: string,
    startsWith?: boolean,
) => boolean;

export type IsCurrentOrParentUrlFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl?: string,
) => boolean;

export type WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    ifTrue: TIfTrue,
    ifFalse?: TIfFalse,
) => TIfTrue | TIfFalse;

export type UseCurrentUrlReturn = {
    currentUrl: string;
    isCurrentUrl: IsCurrentUrlFn;
    isCurrentOrParentUrl: IsCurrentOrParentUrlFn;
    whenCurrentUrl: WhenCurrentUrlFn;
};

function pathnameFrom(url: string): string {
    if (!url.startsWith('http')) {
        const pathOnly = url.split('?')[0]?.split('#')[0] ?? url;

        return pathOnly.replace(/\/+$/, '') || '/';
    }

    try {
        return new URL(url).pathname.replace(/\/+$/, '') || '/';
    } catch {
        return url.replace(/\/+$/, '') || '/';
    }
}

function isSameOrChildPath(currentPath: string, itemPath: string): boolean {
    return (
        currentPath === itemPath || currentPath.startsWith(`${itemPath}/`)
    );
}

export function useCurrentUrl(): UseCurrentUrlReturn {
    const page = usePage();
    const currentUrlPath = pathnameFrom(
        new URL(
            page.url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ).pathname,
    );

    const isCurrentUrl: IsCurrentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) => {
        const urlToCompare = pathnameFrom(currentUrl ?? currentUrlPath);
        const itemPath = pathnameFrom(toUrl(urlToCheck));

        if (startsWith) {
            return isSameOrChildPath(urlToCompare, itemPath);
        }

        return itemPath === urlToCompare;
    };

    const isCurrentOrParentUrl: IsCurrentOrParentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => {
        return isCurrentUrl(urlToCheck, currentUrl, true);
    };

    const whenCurrentUrl: WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: TIfTrue,
        ifFalse: TIfFalse = null as TIfFalse,
    ): TIfTrue | TIfFalse => {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
