import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { index as adminDistributionPoliciesIndex } from '@/routes/admin/distribution-policies';
import { index as adminDocumentTypesIndex } from '@/routes/admin/document-types';
import { index as adminModesOfRequestIndex } from '@/routes/admin/modes-of-request';
import { index as adminSectorsIndex } from '@/routes/admin/sectors';
import { index as adminUnitMeasurementsIndex } from '@/routes/admin/unit-measurements';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as adminWorkflowsIndex } from '@/routes/admin/workflows';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Users',
        href: adminUsersIndex(),
        icon: null,
    },
    {
        title: 'Workflows',
        href: adminWorkflowsIndex(),
        icon: null,
    },
    {
        title: 'Distribution policies',
        href: adminDistributionPoliciesIndex(),
        icon: null,
    },
    {
        title: 'Modes of request',
        href: adminModesOfRequestIndex(),
        icon: null,
    },
    {
        title: 'Document types',
        href: adminDocumentTypesIndex(),
        icon: null,
    },
    {
        title: 'Unit measurements',
        href: adminUnitMeasurementsIndex(),
        icon: null,
    },
    {
        title: 'Sectors',
        href: adminSectorsIndex(),
        icon: null,
    },
];

export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="px-4 py-6">
            <Heading
                title="Administration"
                description="Manage users, roles, workflows, distribution approval policies, and lookups."
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Administration"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}
