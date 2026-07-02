'use client';

import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ACTIONS, EVENTS, ORIGIN, STATUS, useJoyride } from 'react-joyride';
import type { EventData, Step } from 'react-joyride';
import { normalizePath, resolveTourRouteKey } from '@/lib/tour-routes';

const TOUR_STORAGE_PREFIX = 'cais-tour-completed:';
const TOUR_TARGET_POLL_INTERVAL_MS = 100;
const TOUR_TARGET_MAX_WAIT_MS = 3000;

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
    {
        target: '[data-tour="breadcrumb-links"]',
        content: 'Use breadcrumb links to navigate back to parent pages.',
        placement: 'bottom',
    },
];

const PAGE_STEPS: Record<string, Step[]> = {
    dashboard: [
        {
            target: '[data-tour="dashboard-filters"]',
            content: 'Use filters to narrow dashboard insights quickly.',
        },
        {
            target: '[data-tour="dashboard-kpis"]',
            content: 'View KPIs by program and status.',
        },
        {
            target: '[data-tour="dashboard-requests-status-chart"]',
            content: 'Explore charts and metrics to understand your data.',
        },
        {
            target: '[data-tour="dashboard-items-delivered-charts"]',
            content: 'View items delivered to beneficiaries by program.',
        },
        {
            target: '[data-tour="dashboard-programs-summary"]',
            content: 'View programs summary by type and status.',
        },
    ],
    programs: [
        {
            target: '[data-tour="programs-filters"]',
            content: 'Search and filter programs by type and status.',
        },
        {
            target: '[data-tour="programs-create"]',
            content: 'Create a new program for your department.',
        },
    ],
    'programs/show': [
        {
            target: '[data-tour="program-overview"]',
            content: 'Review program details, type, status, and period here.',
        },
        {
            target: '[data-tour="program-edit"]',
            content: 'Edit program details when changes are needed.',
        },
        {
            target: '[data-tour="program-assistance"]',
            content: 'Manage assistance records for this program.',
        },
        {
            target: '[data-tour="program-assistance-toolbar"]',
            content: 'Filter assistance, export data, or add new records.',
        },
    ],
    beneficiaries: [
        {
            target: '[data-tour="beneficiaries-filters"]',
            content: 'Find beneficiaries with search and type filters.',
        },
        {
            target: '[data-tour="beneficiaries-create"]',
            content: 'Add a new beneficiary from here.',
        },
    ],
    items: [
        {
            target: '[data-tour="items-table"]',
            content: 'Manage item inventory and update item details here.',
        },
    ],
    funds: [
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

function selectPageSteps(pathname: string): Step[] {
    const routeKey = resolveTourRouteKey(pathname);

    if (!routeKey) {
        return [];
    }

    return PAGE_STEPS[routeKey] ?? [];
}

function assembleSteps(pathname: string): Step[] {
    return [...SHARED_STEPS, ...selectPageSteps(pathname)];
}

function filterAvailableSteps(steps: Step[]): Step[] {
    if (typeof document === 'undefined') {
        return [];
    }

    return steps.filter((step) => {
        if (typeof step.target !== 'string') {
            return Boolean(step.target);
        }

        return document.querySelector(step.target) !== null;
    });
}

export function AppTour() {
    const page = usePage();

    const pathname = useMemo(() => {
        const [pathOnly] = page.url.split('?');

        return normalizePath(pathOnly);
    }, [page.url]);

    const assembledSteps = useMemo(
        () => assembleSteps(pathname),
        [pathname],
    );

    const [stepState, setStepState] = useState<{
        pathname: string;
        steps: Step[];
    }>({
        pathname: '',
        steps: [],
    });

    const steps = useMemo(
        () => (stepState.pathname === pathname ? stepState.steps : []),
        [pathname, stepState],
    );

    const shouldRun = useMemo(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        const isForcedTour =
            new URLSearchParams(window.location.search).get('tour') === '1';
        const completionKey = `${TOUR_STORAGE_PREFIX}${pathname}`;
        const isCompleted = localStorage.getItem(completionKey) === '1';

        return isForcedTour || !isCompleted;
    }, [pathname]);

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
        let cancelled = false;
        const startedAt = Date.now();

        const resolveSteps = () => {
            if (cancelled) {
                return;
            }

            const availableSteps = filterAvailableSteps(assembledSteps);

            if (
                availableSteps.length === 0 &&
                Date.now() - startedAt < TOUR_TARGET_MAX_WAIT_MS
            ) {
                window.setTimeout(resolveSteps, TOUR_TARGET_POLL_INTERVAL_MS);

                return;
            }

            setStepState({ pathname, steps: availableSteps });
        };

        const handle = window.setTimeout(resolveSteps, 0);

        return () => {
            cancelled = true;
            window.clearTimeout(handle);
        };
    }, [assembledSteps, pathname]);

    useEffect(() => {
        if (!shouldRun || steps.length === 0) {
            controls.stop();

            return;
        }

        controls.start();
    }, [controls, shouldRun, steps]);

    if (steps.length === 0) {
        return null;
    }

    return <div key={pathname}>{Tour}</div>;
}
