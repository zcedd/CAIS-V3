import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useCloseMobileSidebar } from '@/hooks/use-close-mobile-sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const closeMobileSidebar = useCloseMobileSidebar();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Department</SidebarGroupLabel>
            <SidebarMenu data-tour="sidebar-nav">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentOrParentUrl(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link
                                href={item.href}
                                prefetch
                                onClick={closeMobileSidebar}
                            >
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

export function NavAdministration({ items = [] }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const closeMobileSidebar = useCloseMobileSidebar();

    return (
        <>
            <SidebarSeparator />
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>Administration</SidebarGroupLabel>
                <SidebarMenu data-tour="sidebar-nav">
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentOrParentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link
                                    href={item.href}
                                    prefetch
                                    onClick={closeMobileSidebar}
                                >
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroup>
        </>
    );
}

export function NavExecutive({
    items = [],
    label = 'Executive',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const closeMobileSidebar = useCloseMobileSidebar();

    return (
        <>
            <SidebarSeparator />
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>{label}</SidebarGroupLabel>
                <SidebarMenu data-tour="sidebar-nav">
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentOrParentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link
                                    href={item.href}
                                    prefetch
                                    onClick={closeMobileSidebar}
                                >
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroup>
        </>
    );
}
