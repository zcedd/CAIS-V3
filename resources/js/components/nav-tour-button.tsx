import { usePage } from '@inertiajs/react';
import { CircleHelp } from 'lucide-react';
import { hasTourForPath } from '@/components/app-tour';
import { SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';

export function NavTourButton() {
    const { url } = usePage();
    const pathname = url.split('?')[0] ?? url;

    if (!hasTourForPath(pathname)) {
        return null;
    }

    function replayTour() {
        window.location.assign(`${window.location.pathname}?tour=1`);
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                tooltip={{ children: 'Replay page tour' }}
                data-test="sidebar-tour"
                onClick={replayTour}
            >
                <CircleHelp />
                <span>Replay page tour</span>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}
