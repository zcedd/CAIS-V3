import { Link } from '@inertiajs/react';
import { Fragment, useLayoutEffect, useRef, useState } from 'react';
import {
    Breadcrumb,
    BreadcrumbEllipsis,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type BreadcrumbCollapse = {
    hideFirst: boolean;
    hiddenMiddle: number;
    clipPage: boolean;
};

const expandedCollapse: BreadcrumbCollapse = {
    hideFirst: false,
    hiddenMiddle: 0,
    clipPage: false,
};

const ellipsisTriggerClassName = 'flex items-center';

function hasParentBreadcrumbLinks(breadcrumbs: BreadcrumbItemType[]): boolean {
    return breadcrumbs.some(
        (item, index) => index < breadcrumbs.length - 1 && item.href !== '#',
    );
}

function readPixel(value: string): number {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

function rowWidth(
    itemWidths: number[],
    separatorWidth: number,
    ellipsisWidth: number,
    gap: number,
    hideFirst: boolean,
    hiddenMiddle: number,
): number {
    const count = itemWidths.length;
    const parts: number[] = [];

    if (count === 0) {
        return 0;
    }

    if (hideFirst) {
        parts.push(ellipsisWidth, separatorWidth, itemWidths[count - 1]);
    } else if (hiddenMiddle === 0) {
        itemWidths.forEach((width, index) => {
            parts.push(width);

            if (index < count - 1) {
                parts.push(separatorWidth);
            }
        });
    } else {
        const tailStart = 1 + hiddenMiddle;

        parts.push(
            itemWidths[0],
            separatorWidth,
            ellipsisWidth,
            separatorWidth,
        );

        for (let index = tailStart; index < count; index += 1) {
            parts.push(itemWidths[index]);

            if (index < count - 1) {
                parts.push(separatorWidth);
            }
        }
    }

    return (
        parts.reduce((sum, width) => sum + width, 0) +
        Math.max(0, parts.length - 1) * gap
    );
}

function chooseCollapse(
    itemWidths: number[],
    separatorWidth: number,
    ellipsisWidth: number,
    gap: number,
    available: number,
): BreadcrumbCollapse {
    const count = itemWidths.length;

    if (count <= 1) {
        return {
            hideFirst: false,
            hiddenMiddle: 0,
            clipPage: (itemWidths[0] ?? 0) > available,
        };
    }

    const maxMiddle = count - 2;

    for (let hiddenMiddle = 0; hiddenMiddle <= maxMiddle; hiddenMiddle += 1) {
        const width = rowWidth(
            itemWidths,
            separatorWidth,
            ellipsisWidth,
            gap,
            false,
            hiddenMiddle,
        );

        if (width <= available) {
            return {
                hideFirst: false,
                hiddenMiddle,
                clipPage: false,
            };
        }
    }

    const collapsedWidth = rowWidth(
        itemWidths,
        separatorWidth,
        ellipsisWidth,
        gap,
        true,
        0,
    );

    return {
        hideFirst: true,
        hiddenMiddle: 0,
        clipPage: collapsedWidth > available,
    };
}

function clampCollapse(
    count: number,
    collapse: BreadcrumbCollapse,
): BreadcrumbCollapse {
    if (count <= 1) {
        return {
            hideFirst: false,
            hiddenMiddle: 0,
            clipPage: collapse.clipPage,
        };
    }

    if (collapse.hideFirst) {
        return {
            hideFirst: true,
            hiddenMiddle: 0,
            clipPage: collapse.clipPage,
        };
    }

    return {
        hideFirst: false,
        hiddenMiddle: Math.min(collapse.hiddenMiddle, Math.max(0, count - 2)),
        clipPage: false,
    };
}

function hiddenBreadcrumbs(
    breadcrumbs: BreadcrumbItemType[],
    collapse: BreadcrumbCollapse,
): BreadcrumbItemType[] {
    if (collapse.hideFirst) {
        return breadcrumbs.slice(0, -1);
    }

    if (collapse.hiddenMiddle === 0) {
        return [];
    }

    return breadcrumbs.slice(1, 1 + collapse.hiddenMiddle);
}

type BreadcrumbSegment = { type: 'item'; index: number } | { type: 'ellipsis' };

function breadcrumbSegments(
    count: number,
    collapse: BreadcrumbCollapse,
): BreadcrumbSegment[] {
    if (count === 0) {
        return [];
    }

    const showEllipsis = collapse.hideFirst || collapse.hiddenMiddle > 0;
    const segments: BreadcrumbSegment[] = [];

    if (!collapse.hideFirst) {
        segments.push({ type: 'item', index: 0 });
    }

    if (showEllipsis) {
        segments.push({ type: 'ellipsis' });
    }

    const tailStart = collapse.hideFirst
        ? count - 1
        : showEllipsis
          ? 1 + collapse.hiddenMiddle
          : 1;

    for (let index = tailStart; index < count; index += 1) {
        segments.push({ type: 'item', index });
    }

    return segments;
}

function BreadcrumbCrumb({
    item,
    isLast,
    clip,
}: {
    item: BreadcrumbItemType;
    isLast: boolean;
    clip: boolean;
}) {
    return (
        <BreadcrumbItem
            className={clip ? 'min-w-0 shrink overflow-hidden' : 'shrink-0'}
        >
            {isLast ? (
                <BreadcrumbPage
                    className={
                        clip
                            ? 'block w-full min-w-0 truncate'
                            : 'whitespace-nowrap'
                    }
                    {...(clip ? { title: item.title } : {})}
                >
                    {item.title}
                </BreadcrumbPage>
            ) : (
                <BreadcrumbLink asChild>
                    <Link className="whitespace-nowrap" href={item.href}>
                        {item.title}
                    </Link>
                </BreadcrumbLink>
            )}
        </BreadcrumbItem>
    );
}

function CollapsedBreadcrumbs({ items }: { items: BreadcrumbItemType[] }) {
    return (
        <BreadcrumbItem className="shrink-0">
            <DropdownMenu>
                <DropdownMenuTrigger className={ellipsisTriggerClassName}>
                    <BreadcrumbEllipsis />
                    <span className="sr-only">Toggle menu</span>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-max max-w-xs">
                    <DropdownMenuGroup>
                        {items.map((item, index) =>
                            item.href === '#' ? (
                                <DropdownMenuItem
                                    key={index}
                                    className="whitespace-normal"
                                >
                                    {item.title}
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem
                                    key={index}
                                    className="whitespace-normal"
                                    asChild
                                >
                                    <Link
                                        className="block w-full cursor-pointer"
                                        href={item.href}
                                    >
                                        {item.title}
                                    </Link>
                                </DropdownMenuItem>
                            ),
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </BreadcrumbItem>
    );
}

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    const navRef = useRef<HTMLElement>(null);
    const measureRef = useRef<HTMLOListElement>(null);
    const [collapse, setCollapse] =
        useState<BreadcrumbCollapse>(expandedCollapse);
    const showBreadcrumbTourTarget = hasParentBreadcrumbLinks(breadcrumbs);
    const layout = clampCollapse(breadcrumbs.length, collapse);
    const hiddenItems = hiddenBreadcrumbs(breadcrumbs, layout);
    const segments = breadcrumbSegments(breadcrumbs.length, layout);

    useLayoutEffect(() => {
        const nav = navRef.current;
        const measure = measureRef.current;

        if (!nav || !measure) {
            return;
        }

        let frame = 0;
        let attempts = 0;

        const update = () => {
            const available = nav.clientWidth;

            if (available <= 0) {
                return;
            }

            const itemNodes = measure.querySelectorAll<HTMLElement>(
                '[data-breadcrumb-measure="item"]',
            );
            const separator = measure.querySelector<HTMLElement>(
                '[data-breadcrumb-measure="separator"]',
            );
            const ellipsis = measure.querySelector<HTMLElement>(
                '[data-breadcrumb-measure="ellipsis"]',
            );
            const itemWidths = Array.from(
                itemNodes,
                (node) => node.offsetWidth,
            );

            if (
                itemWidths.length !== breadcrumbs.length ||
                itemWidths.some(
                    (width, index) =>
                        width === 0 && breadcrumbs[index]?.title !== '',
                )
            ) {
                if (attempts < 8) {
                    attempts += 1;
                    frame = requestAnimationFrame(update);
                }

                return;
            }

            attempts = 0;

            const next = chooseCollapse(
                itemWidths,
                separator?.offsetWidth ?? 0,
                ellipsis?.offsetWidth ?? 0,
                readPixel(getComputedStyle(measure).columnGap),
                available,
            );

            setCollapse((current) =>
                current.hideFirst === next.hideFirst &&
                current.hiddenMiddle === next.hiddenMiddle &&
                current.clipPage === next.clipPage
                    ? current
                    : next,
            );
        };

        update();

        const observer = new ResizeObserver(update);
        observer.observe(nav);
        observer.observe(measure);

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
        };
    }, [breadcrumbs]);

    if (breadcrumbs.length === 0) {
        return null;
    }

    return (
        <Breadcrumb
            ref={navRef}
            className="relative min-w-0 flex-1 overflow-hidden"
        >
            <BreadcrumbList
                ref={measureRef}
                aria-hidden
                className="pointer-events-none absolute top-0 left-0 -z-10 w-max flex-nowrap opacity-0"
            >
                {breadcrumbs.map((item, index) => (
                    <BreadcrumbItem
                        key={index}
                        data-breadcrumb-measure="item"
                        className="shrink-0"
                    >
                        <span className="whitespace-nowrap">{item.title}</span>
                    </BreadcrumbItem>
                ))}
                <BreadcrumbSeparator
                    data-breadcrumb-measure="separator"
                    className="shrink-0"
                />
                <BreadcrumbItem
                    data-breadcrumb-measure="ellipsis"
                    className="shrink-0"
                >
                    <button
                        type="button"
                        tabIndex={-1}
                        className={ellipsisTriggerClassName}
                    >
                        <BreadcrumbEllipsis />
                        <span className="sr-only">Toggle menu</span>
                    </button>
                </BreadcrumbItem>
            </BreadcrumbList>
            <BreadcrumbList
                className="w-full flex-nowrap"
                {...(showBreadcrumbTourTarget
                    ? { 'data-tour': 'breadcrumb-links' }
                    : {})}
            >
                {segments.map((segment, index) => (
                    <Fragment
                        key={
                            segment.type === 'ellipsis'
                                ? 'ellipsis'
                                : segment.index
                        }
                    >
                        {index > 0 && (
                            <BreadcrumbSeparator className="shrink-0" />
                        )}
                        {segment.type === 'ellipsis' ? (
                            <CollapsedBreadcrumbs items={hiddenItems} />
                        ) : (
                            <BreadcrumbCrumb
                                item={breadcrumbs[segment.index]}
                                isLast={
                                    segment.index === breadcrumbs.length - 1
                                }
                                clip={
                                    layout.clipPage &&
                                    segment.index === breadcrumbs.length - 1
                                }
                            />
                        )}
                    </Fragment>
                ))}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
