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
        intro: 'Fill this in to add a program.',
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
                content: 'Add extra fields if assistance records need them.',
            },
            {
                target: '[data-tour="programs-create-organization"]',
                content: 'Turn this on when the program is for organizations.',
            },
            {
                target: '[data-tour="programs-create-submit"]',
                content: 'Save when you are done.',
            },
        ],
    },
    items: {
        trigger: '[data-tour="items-create"]',
        form: '[data-tour="items-create-form"]',
        intro: 'Fill this in to add an item.',
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
                content: 'Save when you are done.',
            },
        ],
    },
    funds: {
        trigger: '[data-tour="funds-create"]',
        form: '[data-tour="funds-create-form"]',
        intro: 'Fill this in to add a fund.',
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
                content: 'Keep this on if the fund should be usable now.',
            },
            {
                target: '[data-tour="funds-create-submit"]',
                content: 'Save when you are done.',
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

/** Panels that mount only after their dashboard tab is selected. */
const DASHBOARD_TAB_FOR_TARGET: Record<string, string> = {
    '[data-tour="dashboard-assistance-overview"]':
        '[data-tour="dashboard-tab-overview"]',
    '[data-tour="dashboard-requests-trend"]':
        '[data-tour="dashboard-tab-overview"]',
    '[data-tour="dashboard-top-delivered-items"]':
        '[data-tour="dashboard-tab-overview"]',
    '[data-tour="dashboard-requests-status-chart"]':
        '[data-tour="dashboard-tab-overview"]',
    '[data-tour="dashboard-unspsc-released-chart"]':
        '[data-tour="dashboard-tab-overview"]',
    '[data-tour="dashboard-insights"]': '[data-tour="dashboard-tab-insights"]',
    '[data-tour="dashboard-beneficiaries"]':
        '[data-tour="dashboard-tab-beneficiaries"]',
    '[data-tour="dashboard-delivery-snapshot"]':
        '[data-tour="dashboard-tab-beneficiaries"]',
    '[data-tour="dashboard-demographics"]':
        '[data-tour="dashboard-tab-demographics"]',
    '[data-tour="dashboard-kpis"]': '[data-tour="dashboard-tab-programs"]',
    '[data-tour="dashboard-programs-summary"]':
        '[data-tour="dashboard-tab-programs"]',
};

const EXECUTIVE_OVERVIEW_CHART_TARGETS = new Set([
    '[data-tour="dashboard-requests-trend"]',
    '[data-tour="dashboard-top-delivered-items"]',
    '[data-tour="dashboard-requests-status-chart"]',
    '[data-tour="dashboard-unspsc-released-chart"]',
]);

function dashboardTabTriggerForTarget(target: string): string | null {
    return DASHBOARD_TAB_FOR_TARGET[target] ?? null;
}

async function revealDashboardTourTarget(target: string): Promise<void> {
    const triggerSelector = dashboardTabTriggerForTarget(target);

    if (triggerSelector) {
        const trigger = document.querySelector(triggerSelector);

        if (trigger instanceof HTMLElement) {
            trigger.click();
        }
    }

    await waitForTourTarget(target);
}

function dashboardTourStep(target: string, content: string): Step {
    return {
        target,
        content,
        targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
        before: () => revealDashboardTourTarget(target),
        beforeTimeout: TOUR_TARGET_MAX_WAIT_MS,
    };
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
        content: 'The sidebar is how you get around.',
        placement: 'right',
    },
    {
        target: '[data-tour="sidebar-nav"]',
        content: 'These links are your department pages.',
        placement: 'right',
    },
    {
        target: '[data-tour="page-header"]',
        content:
            'The header has the sidebar toggle, search, notifications, appearance, fullscreen, and your account menu.',
        placement: 'bottom',
    },
    {
        target: '[data-tour="breadcrumb-links"]',
        content: 'Breadcrumbs take you back up the page trail.',
        placement: 'bottom',
    },
];

const PAGE_STEPS: Record<string, Step[]> = {
    dashboard: [
        {
            target: '[data-tour="dashboard-filters"]',
            content: 'These filters change what the numbers cover.',
        },
        {
            target: '[data-tour="dashboard-tabs"]',
            content: 'These tabs switch the dashboard view.',
        },
        dashboardTourStep(
            '[data-tour="dashboard-assistance-overview"]',
            'Cash disbursed, in-kind packages, beneficiaries by municipality, age, and vulnerability categories.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-requests-trend"]',
            'How many requests arrived over time.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-top-delivered-items"]',
            'The items released most often.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-requests-status-chart"]',
            'Requests grouped by status.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-unspsc-released-chart"]',
            'Released quantity grouped by UNSPSC segment.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-insights"]',
            'Pending items, backlog aging, top barangays, and how requests were submitted.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-beneficiaries"]',
            'How many people were reached once, and how many came back.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-delivery-snapshot"]',
            'Delivery rate and items per delivered request.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-demographics"]',
            'Sex, age, civil status, and other beneficiary breakdowns.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-kpis"]',
            'Active programs, closed programs, delivered items, and denied requests.',
        ),
        dashboardTourStep(
            '[data-tour="dashboard-programs-summary"]',
            'Programs in this view and how they are doing.',
        ),
    ],
    programs: [
        {
            target: '[data-tour="programs-approval"]',
            content: 'These pills filter programs by approval status.',
        },
        {
            target: '[data-tour="programs-filters"]',
            content: 'Search or filter the program cards.',
        },
        {
            target: '[data-tour="programs-create"]',
            content: 'Start a new program here.',
            before: () =>
                closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.programs),
        },
        ...createDrawerTourSteps(CREATE_DRAWER_TOURS.programs),
        {
            target: '[data-tour="programs-list"]',
            content:
                'Each program appears as a card with budget, progress, and reach.',
            before: () =>
                closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.programs),
        },
    ],
    'programs/show': [
        {
            target: '[data-tour="program-header"]',
            content: 'The program name, status, and who it serves.',
        },
        {
            target: '[data-tour="program-edit"]',
            content: 'Open this to change program details.',
        },
        {
            target: '[data-tour="program-kpis"]',
            content:
                'Budget, cash released, requests, and delivered items for this program.',
        },
        {
            target: '[data-tour="program-requests-status-chart"]',
            content:
                'Demographic reach, the endorsement trail, and how requests are grouped by status.',
        },
        {
            target: '[data-tour="program-overview"]',
            content: 'Dates, type, and other program facts sit here.',
        },
        {
            target: '[data-tour="program-assistance"]',
            content: 'Assistance records for this program are below.',
            before: () =>
                waitForDeferredAssistanceTourTarget(
                    '[data-tour="program-assistance-toolbar"]',
                ),
            beforeTimeout: TOUR_TARGET_MAX_WAIT_MS,
        },
        {
            target: '[data-tour="program-assistance-toolbar"]',
            content: 'Filter, export, or add a record.',
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
            content: 'Add an assistance record for this program.',
            targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
        },
        {
            target: '[data-tour="program-assistance-table"]',
            content: 'The table of assistance records.',
            targetWaitTimeout: TOUR_TARGET_MAX_WAIT_MS,
        },
    ],
    beneficiaries: [
        {
            target: '[data-tour="beneficiaries-kpis"]',
            content: 'How many people and organizations are in the registry.',
        },
        {
            target: '[data-tour="beneficiaries-create"]',
            content: 'Add someone or an organization here.',
        },
        {
            target: '[data-tour="beneficiaries-filters"]',
            content: 'Search or filter the registry.',
        },
        {
            target: '[data-tour="beneficiaries-table"]',
            content: 'The registry list.',
            placement: 'top',
        },
    ],
    items: [
        {
            target: '[data-tour="items-filters"]',
            content: 'Search the item list.',
        },
        {
            target: '[data-tour="items-create"]',
            content: 'Add an item here.',
            before: () => closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.items),
        },
        ...createDrawerTourSteps(CREATE_DRAWER_TOURS.items),
        {
            target: '[data-tour="items-table"]',
            content: 'Stock and item details live in this table.',
            before: () => closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.items),
        },
    ],
    funds: [
        {
            target: '[data-tour="funds-filters"]',
            content: 'Search or filter funds.',
        },
        {
            target: '[data-tour="funds-create"]',
            content: 'Add a fund here.',
            before: () => closeCreateDrawerAnimated(CREATE_DRAWER_TOURS.funds),
        },
        ...createDrawerTourSteps(CREATE_DRAWER_TOURS.funds),
        {
            target: '[data-tour="funds-table"]',
            content: 'The funds list.',
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

export function hasTourForPath(pathname: string): boolean {
    const [pathOnly] = pathname.split('?');

    return selectPageSteps(pathOnly).length > 0;
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

        const dashboardTab = dashboardTabTriggerForTarget(step.target);

        if (
            dashboardTab &&
            document.querySelector(dashboardTab) !== null &&
            (!EXECUTIVE_OVERVIEW_CHART_TARGETS.has(step.target) ||
                document.querySelector(
                    '[data-tour="dashboard-tab-beneficiaries"]',
                ) !== null)
        ) {
            return true;
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
