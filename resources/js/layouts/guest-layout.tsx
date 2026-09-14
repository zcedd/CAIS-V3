import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { index as applyIndex } from '@/routes/public/apply';
import { index as trackIndex } from '@/routes/public/track';
import type { ReactNode } from 'react';

export default function GuestLayout({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <div className="min-h-svh bg-background">
            <header className="border-b border-border">
                <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-4">
                    <Link
                        href={applyIndex.url()}
                        className="flex items-center gap-2 font-medium"
                    >
                        <div className="flex size-9 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-9 fill-current text-foreground dark:text-white" />
                        </div>
                        <span>CAIS public intake</span>
                    </Link>
                    <nav className="flex items-center gap-4 text-sm">
                        <Link
                            href={applyIndex.url()}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            Programs
                        </Link>
                        <Link
                            href={trackIndex.url()}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            Track request
                        </Link>
                    </nav>
                </div>
            </header>
            <main className="mx-auto w-full max-w-3xl px-4 py-8">
                {children}
            </main>
        </div>
    );
}
