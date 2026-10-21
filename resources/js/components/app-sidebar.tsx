import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import {
    NavAdministration,
    NavExecutive,
    NavMain,
} from '@/components/nav-main';
import { NavTourButton } from '@/components/nav-tour-button';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppNavItems } from '@/hooks/use-app-nav-items';
import { useCloseMobileSidebar } from '@/hooks/use-close-mobile-sidebar';
import { dashboard } from '@/routes';
import { index as administratorProgramsIndex } from '@/routes/administrator/programs';
import { dashboard as executiveDashboard } from '@/routes/executive';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import type { Auth } from '@/types/auth';

type SidebarPageProps = {
    auth: Auth;
};

export function AppSidebar() {
    const { props } = usePage<SidebarPageProps>();
    const {
        mainNavItems,
        executiveNavItems,
        administratorNavItems,
        administrationNavItems,
        footerNavItems,
    } = useAppNavItems();
    const closeMobileSidebar = useCloseMobileSidebar();
    const departmentSlug = props.auth.user?.department?.slug;
    const homeHref = departmentSlug
        ? departmentDashboardIndex(departmentSlug)
        : executiveNavItems.length > 0
          ? executiveDashboard()
          : administratorNavItems.length > 0
            ? administratorProgramsIndex()
            : dashboard();

    return (
        <Sidebar collapsible="icon" variant="sidebar" data-tour="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            tooltip="Centralized Assistance Information System"
                            className="h-auto min-h-12"
                        >
                            <Link
                                href={homeHref}
                                prefetch
                                onClick={closeMobileSidebar}
                                data-tour="sidebar-logo"
                            >
                                <AppLogo
                                    name="Centralized Assistance Information System"
                                    wrap
                                />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {mainNavItems.length > 0 && <NavMain items={mainNavItems} />}
                {executiveNavItems.length > 0 && (
                    <NavExecutive items={executiveNavItems} />
                )}
                {administratorNavItems.length > 0 && (
                    <NavExecutive
                        label="Administrator"
                        items={administratorNavItems}
                    />
                )}

                {administrationNavItems.length > 0 && (
                    <NavAdministration items={administrationNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems}>
                    <NavTourButton />
                </NavFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
