import { useEffect, useState } from 'react';

const TOUR_CREATE_DRAWER_LOCK_EVENT = 'cais-tour-create-drawer-lock';
const TOUR_CREATE_DRAWER_LOCK_ATTR = 'data-cais-tour-drawer';
const JOYRIDE_PORTAL_ID = 'react-joyride-portal';

let createDrawerTourLocked = false;
let tourLayerObserver: MutationObserver | null = null;

export function isCreateDrawerTourLocked(): boolean {
    return createDrawerTourLocked;
}

function notifyCreateDrawerTourLock(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new Event(TOUR_CREATE_DRAWER_LOCK_EVENT));
}

function restoreTourLayerInteractivity(): void {
    if (typeof document === 'undefined' || !createDrawerTourLocked) {
        return;
    }

    if (!document.documentElement.hasAttribute(TOUR_CREATE_DRAWER_LOCK_ATTR)) {
        document.documentElement.setAttribute(TOUR_CREATE_DRAWER_LOCK_ATTR, 'true');
    }

    if (document.body.style.pointerEvents === 'none') {
        document.body.style.pointerEvents = 'auto';
    }

    const portal = document.getElementById(JOYRIDE_PORTAL_ID);

    if (!(portal instanceof HTMLElement)) {
        return;
    }

    if (portal.inert) {
        portal.inert = false;
    }

    if (portal.hasAttribute('inert')) {
        portal.removeAttribute('inert');
    }

    if (portal.getAttribute('aria-hidden') === 'true') {
        portal.removeAttribute('aria-hidden');
    }

    if (portal.hasAttribute('data-aria-hidden')) {
        portal.removeAttribute('data-aria-hidden');
    }

    if (portal.style.pointerEvents !== 'auto') {
        portal.style.pointerEvents = 'auto';
    }
}

function startTourLayerGuard(): void {
    if (typeof document === 'undefined' || tourLayerObserver !== null) {
        return;
    }

    restoreTourLayerInteractivity();

    tourLayerObserver = new MutationObserver(() => {
        restoreTourLayerInteractivity();
    });

    tourLayerObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ['inert', 'aria-hidden', 'data-aria-hidden', 'style'],
        childList: true,
        subtree: true,
    });
}

function stopTourLayerGuard(): void {
    tourLayerObserver?.disconnect();
    tourLayerObserver = null;

    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.removeAttribute(TOUR_CREATE_DRAWER_LOCK_ATTR);
}

export function lockCreateDrawerTour(): void {
    createDrawerTourLocked = true;
    notifyCreateDrawerTourLock();
    startTourLayerGuard();
}

export function unlockCreateDrawerTour(): void {
    createDrawerTourLocked = false;
    stopTourLayerGuard();
    notifyCreateDrawerTourLock();
}

export async function waitForCreateDrawerTourLockSync(): Promise<void> {
    await Promise.resolve();

    if (typeof window === 'undefined') {
        return;
    }

    restoreTourLayerInteractivity();

    await new Promise<void>((resolve) => {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => resolve());
        });
    });

    restoreTourLayerInteractivity();
}

export function applyCreateDrawerOpenChange(
    open: boolean,
    setOpen: (nextOpen: boolean) => void,
): void {
    if (!open && isCreateDrawerTourLocked()) {
        return;
    }

    setOpen(open);
}

export function useCreateDrawerTourLock(): boolean {
    const [locked, setLocked] = useState(isCreateDrawerTourLocked);

    useEffect(() => {
        const sync = () => {
            setLocked(isCreateDrawerTourLocked());
        };

        window.addEventListener(TOUR_CREATE_DRAWER_LOCK_EVENT, sync);

        return () => {
            window.removeEventListener(TOUR_CREATE_DRAWER_LOCK_EVENT, sync);
        };
    }, []);

    return locked;
}
