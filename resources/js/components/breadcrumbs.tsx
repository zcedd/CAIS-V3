import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

function hasParentBreadcrumbLinks(
    breadcrumbs: BreadcrumbItemType[],
): boolean {
    return breadcrumbs.some(
        (item, index) =>
            index < breadcrumbs.length - 1 && item.href !== '#',
    );
}

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    const showBreadcrumbTourTarget = hasParentBreadcrumbLinks(breadcrumbs);

    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb className="min-w-0">
                    <BreadcrumbList
                        className="flex-nowrap overflow-hidden"
                        {...(showBreadcrumbTourTarget
                            ? { 'data-tour': 'breadcrumb-links' }
                            : {})}
                    >
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;

                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem className="min-w-0 max-w-48">
                                        {isLast ? (
                                            <BreadcrumbPage
                                                className="min-w-0 truncate"
                                                title={item.title}
                                            >
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link
                                                    className="min-w-0 truncate"
                                                    href={item.href}
                                                    title={item.title}
                                                >
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
