import { useCallback } from 'react';
import { useSidebar } from '@/components/ui/sidebar';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';

/**
 * Closes the mobile sidebar sheet after a navigation link is clicked.
 */
export function useCloseMobileSidebar(): () => void {
    const { isMobile, setOpenMobile } = useSidebar();
    const cleanup = useMobileNavigation();

    return useCallback(() => {
        if (!isMobile) {
            return;
        }

        setOpenMobile(false);
        cleanup();
    }, [isMobile, setOpenMobile, cleanup]);
}
