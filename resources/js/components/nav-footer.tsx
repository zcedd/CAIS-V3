import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import {
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

function formatBadgeCount(count: number): string {
    if (count > 99) {
        return '99+';
    }

    return String(count);
}

function isExternalHref(href: NavItem['href']): boolean {
    const url = toUrl(href);

    return url.startsWith('http://') || url.startsWith('https://');
}

export function NavFooter({
    items,
    children,
}: {
    items: NavItem[];
    children?: ReactNode;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarMenu>
            {items.map((item) => {
                const badgeCount = item.badge ?? 0;

                return (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={
                                !isExternalHref(item.href) &&
                                isCurrentOrParentUrl(item.href)
                            }
                            tooltip={{ children: item.title }}
                        >
                            {isExternalHref(item.href) ? (
                                <a
                                    href={toUrl(item.href)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </a>
                            ) : (
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            )}
                        </SidebarMenuButton>
                        {badgeCount > 0 ? (
                            <SidebarMenuBadge>
                                {formatBadgeCount(badgeCount)}
                            </SidebarMenuBadge>
                        ) : null}
                    </SidebarMenuItem>
                );
            })}
            {children}
        </SidebarMenu>
    );
}
