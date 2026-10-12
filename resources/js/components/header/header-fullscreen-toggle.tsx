import { Maximize, Minimize } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

function subscribeToFullscreenSupport(): () => void {
    return () => {};
}

function subscribeToFullscreen(onStoreChange: () => void): () => void {
    document.addEventListener('fullscreenchange', onStoreChange);

    return () => {
        document.removeEventListener('fullscreenchange', onStoreChange);
    };
}

function fullscreenIsActive(): boolean {
    return document.fullscreenElement !== null;
}

function fullscreenIsSupported(): boolean {
    return document.fullscreenEnabled;
}

export function HeaderFullscreenToggle() {
    const supported = useSyncExternalStore(
        subscribeToFullscreenSupport,
        fullscreenIsSupported,
        () => false,
    );
    const active = useSyncExternalStore(
        subscribeToFullscreen,
        fullscreenIsActive,
        () => false,
    );

    if (!supported) {
        return null;
    }

    async function toggleFullscreen() {
        if (document.fullscreenElement) {
            await document.exitFullscreen();

            return;
        }

        await document.documentElement.requestFullscreen();
    }

    const label = active ? 'Exit fullscreen' : 'Fullscreen';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="hidden md:inline-flex"
                    aria-label={label}
                    data-test="header-fullscreen"
                    onClick={() => {
                        void toggleFullscreen();
                    }}
                >
                    {active ? <Minimize /> : <Maximize />}
                    <span className="sr-only">{label}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
