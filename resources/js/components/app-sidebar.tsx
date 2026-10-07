import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    FolderKanban,
    Inbox,
    Landmark,
    LayoutGrid,
    Package,
    Shield,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { Permission } from '@/lib/permissions';
import { dashboard } from '@/routes';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import { index as departmentFundsIndex } from '@/routes/user/funds';
import { index as departmentItemsIndex } from '@/routes/user/items';
import { index as departmentNotificationsIndex } from '@/routes/user/notifications';
import { index as departmentProgramsIndex } from '@/routes/user/programs';
import { index as departmentBeneficiariesIndex } from '@/routes/user/beneficiaries';
import { index as departmentQueueIndex } from '@/routes/user/queue';
import type { NavItem } from '@/types';
import type { Auth } from '@/types/auth';

type SidebarPageProps = {
    auth: Auth;
    unreadNotificationsCount: number;
};

export function AppSidebar() {
    const { props } = usePage<SidebarPageProps>();
    const can = useCan();

    const footerNavItems = useMemo((): NavItem[] => {
        const slug = props.auth.user?.department?.slug;

        const items: NavItem[] = [];

        if (slug) {
            items.push({
                title: 'Notifications',
                href: departmentNotificationsIndex(slug),
                icon: Bell,
                badge: props.unreadNotificationsCount,
            });
        }

        return items;
    }, [props.auth.user, props.unreadNotificationsCount]);

    const mainNavItems = useMemo((): NavItem[] => {
        const slug = props.auth.user?.department?.slug;

        const items: NavItem[] = [
            {
                title: 'Dashboard',
                href: slug ? departmentDashboardIndex(slug) : dashboard(),
                icon: LayoutGrid,
            },
        ];

        if (props.auth.is_super_admin) {
            items.push({
                title: 'Admin',
                href: adminUsersIndex(),
                icon: Shield,
            });
        }

        if (slug) {
            const departmentItems: NavItem[] = [
                {
                    title: 'Programs',
                    href: departmentProgramsIndex(slug),
                    icon: FolderKanban,
                    permission: Permission.ProgramViewAny,
                },
                {
                    title: 'Queue',
                    href: departmentQueueIndex(slug),
                    icon: Inbox,
                    permission: Permission.AssistanceViewAny,
                },
                {
                    title: 'Beneficiaries',
                    href: departmentBeneficiariesIndex(slug),
                    icon: Users,
                    permission: Permission.BeneficiaryViewAny,
                },
                {
                    title: 'Items',
                    href: departmentItemsIndex(slug),
                    icon: Package,
                    permission: Permission.ItemViewAny,
                },
                {
                    title: 'Funds',
                    href: departmentFundsIndex(slug),
                    icon: Landmark,
                    permission: Permission.FundViewAny,
                },
            ];

            items.push(
                ...departmentItems.filter(
                    (item) =>
                        item.permission === undefined || can(item.permission),
                ),
            );
        }

        return items;
    }, [can, props.auth.is_super_admin, props.auth.user]);

    return (
        <Sidebar collapsible="icon" variant="sidebar" data-tour="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={
                                    props.auth.user?.department?.slug
                                        ? departmentDashboardIndex(
                                            props.auth.user.department.slug,
                                        )
                                        : dashboard()
                                }
                                prefetch
                                data-tour="sidebar-logo"
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
            </SidebarFooter>
        </Sidebar>
    );
}
