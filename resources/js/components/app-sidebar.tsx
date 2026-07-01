import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, FolderKanban, Landmark, LayoutGrid, Package, Users } from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
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
import { dashboard } from '@/routes';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import { index as departmentFundsIndex } from '@/routes/user/funds';
import { index as departmentItemsIndex } from '@/routes/user/items';
import { index as departmentProgramsIndex } from '@/routes/user/programs';
import { index as departmentBeneficiariesIndex } from '@/routes/user/beneficiaries';
import type { NavItem } from '@/types';
import type { User } from '@/types/auth';

type SidebarPageProps = {
    auth: {
        user: User | null;
    };
};

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { props } = usePage<SidebarPageProps>();

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
            items.push({
                title: 'Programs',
                href: departmentProgramsIndex(slug),
                icon: FolderKanban,
            });
            items.push({
                title: 'Beneficiaries',
                href: departmentBeneficiariesIndex(slug),
                icon: Users,
            });
            items.push({
                title: 'Items',
                href: departmentItemsIndex(slug),
                icon: Package,
            });
            items.push({
                title: 'Funds',
                href: departmentFundsIndex(slug),
                icon: Landmark,
            });
        }

        return items;
    }, [props.auth.user]);

    return (
        <Sidebar collapsible="icon" variant="inset">
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
