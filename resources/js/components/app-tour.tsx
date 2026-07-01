'use client';

import { usePage } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import {
    ACTIONS,
    EVENTS,
    ORIGIN,
    STATUS,
    useJoyride,
} from 'react-joyride';
import type { EventData, Step } from 'react-joyride';

const TOUR_STORAGE_PREFIX = 'cais-tour-completed:';

const SHARED_STEPS: Step[] = [
    {
        target: '[data-tour="sidebar"]',
        content: 'Use the sidebar to move between core system modules.',
        placement: 'right',
    },
    {
        target: '[data-tour="sidebar-nav"]',
        content: 'These links open your department pages.',
        placement: 'right',
    },
    {
        target: '[data-tour="page-header"]',
        content: 'This header shows where you are through breadcrumbs.',
        placement: 'bottom',
    },
];

const PAGE_STEPS: Record<string, Step[]> = {
    '/user/dashboard': [
        {
            target: '[data-tour="dashboard-filters"]',
            content: 'Use filters to narrow dashboard insights quickly.',
        },
    ],
    '/user/programs': [
        {
            target: '[data-tour="programs-filters"]',
            content: 'Search and filter programs by type and status.',
        },
        {
            target: '[data-tour="programs-create"]',
            content: 'Create a new program for your department.',
        },
    ],
    '/user/beneficiaries': [
        {
            target: '[data-tour="beneficiaries-filters"]',
            content: 'Find beneficiaries with search and type filters.',
        },
        {
            target: '[data-tour="beneficiaries-create"]',
            content: 'Add a new beneficiary from here.',
        },
    ],
    '/user/items': [
        {
            target: '[data-tour="items-table"]',
            content: 'Manage item inventory and update item details here.',
        },
    ],
    '/user/funds': [
        {
            target: '[data-tour="funds-filters"]',
            content: 'Use filters to locate funds by name or status.',
        },
        {
            target: '[data-tour="funds-create"]',
            content: 'Create a fund record for your department.',
        },
    ],
};

function normalizePath(path: string): string {
    if (path === '') {
        return '/';
    }

    if (path.endsWith('/')) {
        return path.slice(0, -1);
    }

    return path;
}

function selectPageSteps(pathname: string): Step[] {
    const exactSteps = PAGE_STEPS[pathname];

    if (exactSteps) {
        return exactSteps;
    }

    const matchedPrefix = Object.keys(PAGE_STEPS).find((prefix) =>
        pathname.startsWith(`${prefix}/`),
    );

    return matchedPrefix ? PAGE_STEPS[matchedPrefix] : [];
}

export function AppTour() {
    const page = usePage();

    const pathname = useMemo(() => {
        const [pathOnly] = page.url.split('?');

        return normalizePath(pathOnly);
    }, [page.url]);

    const steps = useMemo(
        () => [...SHARED_STEPS, ...selectPageSteps(pathname)],
        [pathname],
    );

    const shouldRun = useMemo(() => {
        if (typeof window === 'undefined' || steps.length === 0) {
            return false;
        }

        const isForcedTour =
            new URLSearchParams(window.location.search).get('tour') === '1';
        const completionKey = `${TOUR_STORAGE_PREFIX}${pathname}`;
        const isCompleted = localStorage.getItem(completionKey) === '1';

        return isForcedTour || !isCompleted;
    }, [pathname, steps.length]);

    const handleCallback = (data: EventData) => {
        const { status, type, action, origin } = data;

        if (
            status === STATUS.FINISHED ||
            status === STATUS.SKIPPED ||
            (action === ACTIONS.CLOSE && origin === ORIGIN.KEYBOARD)
        ) {
            localStorage.setItem(`${TOUR_STORAGE_PREFIX}${pathname}`, '1');

            return;
        }

        if (type === EVENTS.TOUR_END) {
            localStorage.setItem(`${TOUR_STORAGE_PREFIX}${pathname}`, '1');
        }
    };

    const { Tour, controls } = useJoyride({
        steps,
        continuous: true,
        onEvent: handleCallback,
        options: { zIndex: 10000 },
        locale: {
            back: 'Back',
            close: 'Close',
            last: 'Finish',
            next: 'Next',
            skip: 'Skip tour',
        },
    });

    useEffect(() => {
        if (!shouldRun || steps.length === 0) {
            controls.stop();

            return;
        }

        controls.start();
    }, [controls, shouldRun, steps.length]);

    return <div key={pathname}>{Tour}</div>;
}
