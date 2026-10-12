import { usePage } from '@inertiajs/react';
import {
    Bell,
    FolderKanban,
    Inbox,
    Landmark,
    LayoutGrid,
    Package,
    Shield,
    Stamp,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
import { useCan } from '@/hooks/use-can';
import { Permission } from '@/lib/permissions';
import { dashboard } from '@/routes';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { dashboard as executiveDashboard } from '@/routes/executive';
import { index as executiveProgramsIndex } from '@/routes/executive/programs';
import { index as departmentBeneficiariesIndex } from '@/routes/user/beneficiaries';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import { index as departmentFundsIndex } from '@/routes/user/funds';
import { index as departmentItemsIndex } from '@/routes/user/items';
import { index as departmentNotificationsIndex } from '@/routes/user/notifications';
import { index as departmentProgramsIndex } from '@/routes/user/programs';
import { index as departmentQueueIndex } from '@/routes/user/queue';
import type { NavItem } from '@/types';
import type { Auth } from '@/types/auth';

type NavPageProps = {
    auth: Auth;
    unreadNotificationsCount: number;
};

export type AppNavItems = {
    mainNavItems: NavItem[];
    executiveNavItems: NavItem[];
    administrationNavItems: NavItem[];
    footerNavItems: NavItem[];
};

export function useAppNavItems(): AppNavItems {
    const { props } = usePage<NavPageProps>();
    const can = useCan();

    const footerNavItems = useMemo((): NavItem[] => {
        const slug = props.auth.user?.department?.slug;

        if (!slug) {
            return [];
        }

        return [
            {
                title: 'Notifications',
                href: departmentNotificationsIndex(slug),
                icon: Bell,
                badge: props.unreadNotificationsCount,
            },
        ];
    }, [props.auth.user, props.unreadNotificationsCount]);

    const administrationNavItems = useMemo((): NavItem[] => {
        const items: NavItem[] = [];

        if (props.auth.is_super_admin) {
            items.push({
                title: 'Admin',
                href: adminUsersIndex(),
                icon: Shield,
            });
        }

        return items;
    }, [props.auth.is_super_admin]);

    const executiveNavItems = useMemo((): NavItem[] => {
        const items: NavItem[] = [];

        if (
            props.auth.office_roles?.includes('governor') ||
            can(Permission.ProgramApprove)
        ) {
            items.push(
                {
                    title: 'Dashboard',
                    href: executiveDashboard(),
                    icon: LayoutGrid,
                },
                {
                    title: 'Programs',
                    href: executiveProgramsIndex(),
                    icon: Stamp,
                },
            );
        }

        return items;
    }, [props.auth.office_roles, can]);

    const mainNavItems = useMemo((): NavItem[] => {
        const slug = props.auth.user?.department?.slug;

        const items: NavItem[] = [
            {
                title: 'Dashboard',
                href: slug ? departmentDashboardIndex(slug) : dashboard(),
                icon: LayoutGrid,
            },
        ];

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
    }, [can, props.auth.user]);

    return {
        mainNavItems,
        executiveNavItems,
        administrationNavItems,
        footerNavItems,
    };
}
