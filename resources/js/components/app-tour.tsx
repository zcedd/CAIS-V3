'use client';

import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ACTIONS, EVENTS, ORIGIN, STATUS, useJoyride } from 'react-joyride';
import type { EventData, Step } from 'react-joyride';
import { AppTourTooltip } from '@/components/app-tour-tooltip';
import {
    lockCreateDrawerTour,
    unlockCreateDrawerTour,
    waitForCreateDrawerTourLockSync,
} from '@/lib/tour-create-drawer';
import {
    normalizePath,
    resolveTourCompletionKey,
    resolveTourRouteKey,
} from '@/lib/tour-routes';

const TOUR_STORAGE_PREFIX = 'cais-tour-completed:';
const SHARED_TOUR_KEY = `${TOUR_STORAGE_PREFIX}shared`;
const TOUR_TARGET_POLL_INTERVAL_MS = 100;
const TOUR_TARGET_MAX_WAIT_MS = 8000;

/** Targets rendered inside Inertia `WhenVisible` / deferred assistance content. */
const DEFERRED_ASSISTANCE_TOUR_PARENT = '[data-tour="program-assistance"]';
const DEFERRED_ASSISTANCE_TOUR_TARGETS = [
    '[data-tour="program-assistance-toolbar"]',
    '[data-tour="program-assistance-export"]',
    '[data-tour="program-assistance-create"]',
    '[data-tour="program-assistance-table"]',
] as const;

const TOUR_DRAWER_ANIMATION_MS = 400;

type CreateDrawerField = {
    target: string;
    content: string;
};

type CreateDrawerTour = {
    trigger: string;
    form: string;
    intro: string;
    fields: readonly CreateDrawerField[];
};

const CREATE_DRAWER_TOURS = {
    programs: {
        trigger: '[data-tour="programs-create"]',
        form: '[data-tour="programs-create-form"]',
        intro: 'This form creates a program for your department.',
        fields: [
            {
                target: '[data-tour="programs-create-name"]',
                content: 'Enter the program name.',
            },
            {
                target: '[data-tour="programs-create-description"]',
                content: 'Add a short description of the program.',
            },
            {
                target: '[data-tour="programs-create-dates"]',
                content: 'Set the program start and end dates.',
            },
            {
                target: '[data-tour="programs-create-funds"]',
                content: 'Choose the funds this program can use.',
            },
            {
                target: '[data-tour="programs-create-items"]',
                content: 'Choose the items this program can distribute.',
            },
            {
                target: '[data-tour="programs-create-fields"]',
                content:
                    'Add optional custom fields for assistance records.',
            },
            {
                target: '[data-tour="programs-create-organization"]',
                content:
                    'Enable this when the program is for organizations.',
            },
            {
                target: '[data-tour="programs-create-submit"]',
                content: 'Save the program when the form is complete.',
            },
        ],
    },
    items: {
        trigger: '[data-tour="items-create"]',
        form: '[data-tour="items-create-form"]',
        intro: 'This form creates an item for your department.',
        fields: [
            {
                target: '[data-tour="items-create-name"]',
                content: 'Enter the item name.',
            },
            {
                target: '[data-tour="items-create-unit"]',
                content: 'Select the unit of measurement.',
            },
            {
                target: '[data-tour="items-create-submit"]',
                content: 'Save the item when the form is complete.',
            },
        ],
    },
    funds: {
        trigger: '[data-tour="funds-create"]',
        form: '[data-tour="funds-create-form"]',
        intro: 'This form creates a fund for your department.',
        fields: [
            {
                target: '[data-tour="funds-create-name"]',
                content: 'Enter the fund name.',
            },
            {
                target: '[data-tour="funds-create-amount"]',
                content: 'Enter the fund amount.',
            },
            {
                target: '[data-tour="funds-create-year"]',
                content: 'Set the fund year.',
            },
            {
                target: '[data-tour="funds-create-active"]',
                content:
                    'Leave this on to make the fund available right away.',
            },
            {
                target: '[data-tour="funds-create-submit"]',
                content: 'Save the fund when the form is complete.',
            },
        ],
    },
} as const satisfies Record<string, CreateDrawerTour>;

function isDeferredAssistanceTourTarget(target: string): boolean {
    return (DEFERRED_ASSISTANCE_TOUR_TARGETS as readonly string[]).includes(
        target,
    );
}

function revealTourTargetParent(parentSelector: string): void {
    const parent = document.querySelector(parentSelector);

    if (parent instanceof HTMLElement) {
        parent.scrollIntoView({ block: 'center', behavior: 'auto' });
    }
}

function waitForTourTarget(
    selector: string,
    {
        parentSelector,
        timeoutMs = TOUR_TARGET_MAX_WAIT_MS,
    }: {
        parentSelector?: string;
        timeoutMs?: number;
    } = {},
): Promise<void> {
    const startedAt = Date.now();

    return new Promise((resolve) => {
        const tick = () => {
            if (parentSelector) {
                revealTourTargetParent(parentSelector);
            }

            if (document.querySelector(selector)) {
                resolve();

                return;
            }

            if (Date.now() - startedAt >= timeoutMs) {
                resolve();

                return;
            }

            window.setTimeout(tick, TOUR_TARGET_POLL_INTERVAL_MS);
        };

        tick();
    });
}

function waitForDeferredAssistanceTourTarget(selector: string): Promise<void> {
    return waitForTourTarget(selector, {
        parentSelector: DEFERRED_ASSISTANCE_TOUR_PARENT,
    });
}

function wait(ms: number): Promise<void> {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

function findCreateDrawerTour(target: string): CreateDrawerTour | undefined {
    return Object.values(CREATE_DRAWER_TOURS).find(
        (drawer) =>
            drawer.form === target ||
            drawer.fields.some((field) => field.target === target),
    );
}

function canOpenCreateDrawer(triggerSelector: string): boolean {
    const trigger = document.querySelector(triggerSelector);

    return (
        trigger instanceof HTMLButtonElement &&
        !trigger.disabled &&
        trigger.getAttribute('aria-disabled') !== 'true'
    );
}

function closeCreateDrawer(formSelector: string): void {
    const closeButton = document.querySelector(
        `${formSelector} [data-slot="drawer-close"]`,
    );

    if (closeButton instanceof HTMLElement) {
        closeButton.click();
    }
}

async function closeAllCreateDrawers(): Promise<void> {
    unlockCreateDrawerTour();
    await waitForCreateDrawerTourLockSync();

    for (const drawer of Object.values(CREATE_DRAWER_TOURS)) {
        closeCreateDrawer(drawer.form);
    }
}

async function openCreateDrawer(drawer: CreateDrawerTour): Promise<void> {
    lockCreateDrawerTour();
    await waitForCreateDrawerTourLockSync();

    const alreadyOpen = document.querySelector(drawer.form) !== null;

    if (!alreadyOpen) {
        const trigger = document.querySelector(drawer.trigger);

        if (trigger instanceof HTMLButtonElement && !trigger.disabled) {
            trigger.click();
        }
    }

    await waitForTourTarget(drawer.form);

    if (!alreadyOpen) {
        await wait(TOUR_DRAWER_ANIMATION_MS);
    }
}

async function closeCreateDrawerAnimated(
    drawer: CreateDrawerTour,
): Promise<void> {
    const wasOpen = document.querySelector(drawer.form) !== null;

    unlockCreateDrawerTour();

    if (!wasOpen) {
        return;
    }

    await waitForCreateDrawerTourLockSync();
    closeCreateDrawer(drawer.form);
    await wait(TOUR_DRAWER_ANIMATION_MS);
}

function createDrawerFormStep(drawer: CreateDrawerTour): Step {
    return {
        target: drawer.form,
        content: drawer.intro,
        placement: 'left',
        skipScroll: true,
        disableFocusTrap: true,
        overlayClickAction: false,
        targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
        before: () => openCreateDrawer(drawer),
        beforeTimeout: TOUR_TARGET_MAX_WAIT_MS,
    };
}

function createDrawerFieldStep(
    drawer: CreateDrawerTour,
    field: CreateDrawerField,
): Step {
    return {
        target: field.target,
        content: field.content,
        placement: 'left',
        skipScroll: true,
        disableFocusTrap: true,
        overlayClickAction: false,
        targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
        before: async () => {
            await openCreateDrawer(drawer);

            const element = document.querySelector(field.target);

            if (element instanceof HTMLElement) {
                element.scrollIntoView({ block: 'nearest', behavior: 'auto' });
            }
        },
        beforeTimeout: TOUR_TARGET_MAX_WAIT_MS,
    };
}

function createDrawerTourSteps(drawer: CreateDrawerTour): Step[] {
    return [
        createDrawerFormStep(drawer),
        ...drawer.fields.map((field) => createDrawerFieldStep(drawer, field)),
    ];
}

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
        content:
            'Use the sidebar toggle and your account menu from this header.',
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
            before: () =>
                closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.programs),
        },
        ...createDrawerTourSteps(CREATE_DRAWER_TOURS.programs),
        {
            target: '[data-tour="programs-list"]',
            content: 'View programs list by type and status.',
            before: () =>
                closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.programs),
        },
    ],
    'programs/show': [
        {
            target: '[data-tour="program-header"]',
            content: 'View program title with status and beneficiary type.',
        },
        {
            target: '[data-tour="program-edit"]',
            content: 'Edit program details when changes are needed.',
        },
        {
            target: '[data-tour="program-kpis"]',
            content:
                'View program KPIs by requests, beneficiaries, and items delivered.',
        },
        {
            target: '[data-tour="program-requests-status-chart"]',
            content: 'View requests status chart by status.',
        },
        {
            target: '[data-tour="program-overview"]',
            content: 'Review program details, type, status, and period here.',
        },
        {
            target: '[data-tour="program-assistance"]',
            content: 'Manage assistance records for this program.',
            before: () =>
                waitForDeferredAssistanceTourTarget(
                    '[data-tour="program-assistance-toolbar"]',
                ),
            beforeTimeout: TOUR_TARGET_MAX_WAIT_MS,
        },
        {
            target: '[data-tour="program-assistance-toolbar"]',
            content: 'Filter assistance, export data, or add new records.',
            targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
            before: () =>
                waitForDeferredAssistanceTourTarget(
                    '[data-tour="program-assistance-toolbar"]',
                ),
            beforeTimeout: TOUR_TARGET_MAX_WAIT_MS,
        },
        {
            target: '[data-tour="program-assistance-export"]',
            content: 'Export assistance records as CSV or XLSX.',
            targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
        },
        {
            target: '[data-tour="program-assistance-create"]',
            content: 'Create a new assistance record for this program.',
            targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
        },
        {
            target: '[data-tour="program-assistance-table"]',
            content: 'View assistance records.',
            targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
        },
    ],
    beneficiaries: [
        {
            target: '[data-tour="beneficiaries-kpis"]',
            content:
                'View beneficiaries KPIs by total, individual, and organization beneficiaries.',
        },
        {
            target: '[data-tour="beneficiaries-create"]',
            content: 'Add a new beneficiary from here.',
        },
        {
            target: '[data-tour="beneficiaries-filters"]',
            content: 'Find beneficiaries with search and type filters.',
        },
        {
            target: '[data-tour="beneficiaries-table"]',
            content: 'View beneficiaries table by type.',
            placement: 'top',
        },
    ],
    items: [
        {
            target: '[data-tour="items-filters"]',
            content: 'Find items with search filters.',
        },
        {
            target: '[data-tour="items-create"]',
            content: 'Create a new item for your department.',
            before: () => closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.items),
        },
        ...createDrawerTourSteps(CREATE_DRAWER_TOURS.items),
        {
            target: '[data-tour="items-table"]',
            content: 'Manage item inventory and update item details here.',
            before: () => closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.items),
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
            before: () => closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.funds),
        },
        ...createDrawerTourSteps(CREATE_DRAWER_TOURS.funds),
        {
            target: '[data-tour="funds-table"]',
            content: 'Manage funds and update fund details here.',
            before: () => closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.funds),
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

function hasCompletedSharedTour(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return localStorage.getItem(SHARED_TOUR_KEY) === '1';
}

function isTourForced(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return new URLSearchParams(window.location.search).get('tour') === '1';
}

function hasCompletedPageTour(completionKey: string | null): boolean {
    if (completionKey === null || typeof window === 'undefined') {
        return false;
    }

    return (
        localStorage.getItem(`${TOUR_STORAGE_PREFIX}${completionKey}`) === '1'
    );
}

function shouldStartTour(
    completionKey: string | null,
    tourDismissed: boolean,
): boolean {
    if (
        typeof window === 'undefined' ||
        tourDismissed ||
        completionKey === null
    ) {
        return false;
    }

    return isTourForced() || !hasCompletedPageTour(completionKey);
}

function assembleSteps(pathname: string, includeShared: boolean): Step[] {
    const pageSteps = selectPageSteps(pathname);

    if (!includeShared) {
        return pageSteps;
    }

    return [...SHARED_STEPS, ...pageSteps];
}

function filterAvailableSteps(steps: Step[]): Step[] {
    if (typeof document === 'undefined') {
        return [];
    }

    const assistanceParentPresent =
        document.querySelector(DEFERRED_ASSISTANCE_TOUR_PARENT) !== null;

    return steps.filter((step) => {
        if (typeof step.target !== 'string') {
            return Boolean(step.target);
        }

        if (document.querySelector(step.target) !== null) {
            return true;
        }

        const createDrawer = findCreateDrawerTour(step.target);

        if (createDrawer) {
            return canOpenCreateDrawer(createDrawer.trigger);
        }

        // Keep WhenVisible-deferred targets while their parent section exists.
        // `before` hooks scroll the section into view and wait for mount.
        return (
            isDeferredAssistanceTourTarget(step.target) &&
            assistanceParentPresent
        );
    });
}

export function AppTour() {
    const page = usePage();

    const pathname = useMemo(() => {
        const [pathOnly] = page.url.split('?');

        return normalizePath(pathOnly);
    }, [page.url]);

    const completionKey = useMemo(
        () => resolveTourCompletionKey(pathname),
        [pathname],
    );

    const includeShared = useMemo(() => !hasCompletedSharedTour(), [pathname]);

    const assembledSteps = useMemo(
        () => assembleSteps(pathname, includeShared),
        [includeShared, pathname],
    );

    const [stepState, setStepState] = useState<{
        pathname: string;
        steps: Step[];
    }>({
        pathname: '',
        steps: [],
    });

    const includedSharedRef = useRef(false);
    const tourDismissedRef = useRef(
        hasCompletedPageTour(completionKey) && !isTourForced(),
    );

    const [tourDismissed, setTourDismissed] = useState(
        () => tourDismissedRef.current,
    );

    const steps = useMemo(
        () => (stepState.pathname === pathname ? stepState.steps : []),
        [pathname, stepState],
    );

    const shouldRun = useMemo(
        () => shouldStartTour(completionKey, tourDismissed) && steps.length > 0,
        [completionKey, steps.length, tourDismissed],
    );

    const dismissTour = () => {
        tourDismissedRef.current = true;
        setTourDismissed(true);
    };

    const handleCallback = (data: EventData) => {
        const { status, type, action, origin } = data;

        const markTourCompleted = () => {
            void closeAllCreateDrawers();

            if (completionKey !== null) {
                localStorage.setItem(
                    `${TOUR_STORAGE_PREFIX}${completionKey}`,
                    '1',
                );
            }

            if (includedSharedRef.current) {
                localStorage.setItem(SHARED_TOUR_KEY, '1');
            }

            dismissTour();
        };

        if (
            status === STATUS.FINISHED ||
            status === STATUS.SKIPPED ||
            (action === ACTIONS.CLOSE && origin === ORIGIN.KEYBOARD)
        ) {
            markTourCompleted();

            return;
        }

        if (type === EVENTS.TOUR_END) {
            markTourCompleted();
        }
    };

    const { Tour } = useJoyride({
        steps,
        run: shouldRun,
        continuous: true,
        scrollToFirstStep: true,
        onEvent: handleCallback,
        tooltipComponent: AppTourTooltip,
        floatingOptions: { hideArrow: true },
        options: {
            zIndex: 10000,
            buttons: ['back', 'skip', 'primary'],
            skipBeacon: true,
        },
        locale: {
            back: 'Back',
            close: 'Close',
            last: 'Finish',
            next: 'Next',
            skip: 'Skip tour',
        },
    });

    useEffect(() => {
        const dismissed =
            hasCompletedPageTour(completionKey) && !isTourForced();

        tourDismissedRef.current = dismissed;
        setTourDismissed(dismissed);
    }, [completionKey]);

    useEffect(() => {
        let cancelled = false;
        const startedAt = Date.now();

        const resolveSteps = () => {
            if (cancelled || tourDismissedRef.current) {
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

            includedSharedRef.current = includeShared;

            setStepState({ pathname, steps: availableSteps });
        };

        const handle = window.setTimeout(resolveSteps, 0);

        return () => {
            cancelled = true;
            window.clearTimeout(handle);
        };
    }, [assembledSteps, includeShared, pathname]);

    return <div key={pathname}>{Tour}</div>;
}
