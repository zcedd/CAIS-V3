import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { index as applyIndex } from '@/routes/public/apply';
import { index as trackIndex } from '@/routes/public/track';

export default function GuestLayout({ children }: { children: ReactNode }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const programsCurrent = isCurrentOrParentUrl(applyIndex());
    const trackCurrent = isCurrentOrParentUrl(trackIndex());

    return (
        <div className="min-h-svh bg-muted/40">
            <header className="border-b border-border bg-background">
                <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-4">
                    <Link
                        href={applyIndex.url()}
                        className="flex items-center gap-2 font-medium"
                    >
                        <div className="flex size-9 items-center justify-center">
                            <AppLogoIcon className="size-9 fill-current text-foreground dark:text-white" />
                        </div>
                        <span>Assistance</span>
                    </Link>
                    <nav className="flex items-center gap-1 text-sm">
                        <Link
                            href={applyIndex.url()}
                            aria-current={programsCurrent ? 'page' : undefined}
                            className={cn(
                                'rounded-md px-3 py-1.5',
                                programsCurrent
                                    ? 'bg-muted font-medium text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Programs
                        </Link>
                        <Link
                            href={trackIndex.url()}
                            aria-current={trackCurrent ? 'page' : undefined}
                            className={cn(
                                'rounded-md px-3 py-1.5',
                                trackCurrent
                                    ? 'bg-muted font-medium text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Track request
                        </Link>
                    </nav>
                </div>
            </header>
            <main className="mx-auto flex w-full max-w-3xl flex-col px-4 py-8">
                {children}
            </main>
        </div>
    );
}
