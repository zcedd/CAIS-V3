import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAppearance } from '@/hooks/use-appearance';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center bg-muted p-6 md:p-10">
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="absolute top-4 right-4 bg-background/80"
                aria-label={
                    isDark ? 'Switch to light mode' : 'Switch to dark mode'
                }
                onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            >
                {isDark ? <Sun /> : <Moon />}
            </Button>

            <div className="w-full max-w-sm md:max-w-4xl">
                <Card className="gap-0 overflow-hidden py-0">
                    <CardContent className="grid px-0 md:grid-cols-2">
                        <div className="flex flex-col gap-7 p-6 md:p-8">
                            <div className="flex flex-col items-center gap-2 text-center">
                                <div className="mb-1 flex items-center justify-center gap-3">
                                    <img
                                        src="/images/logos/ilocosnorte.png"
                                        alt="Ilocos Norte"
                                        className="size-16"
                                    />
                                    <img
                                        src={
                                            isDark
                                                ? '/images/logos/bagong-pilipinas-dark.png'
                                                : '/images/logos/bagong-pilipinas-light.png'
                                        }
                                        alt="Bagong Pilipinas"
                                        className="h-16 w-auto"
                                    />
                                </div>
                                {title ? (
                                    <h1 className="text-2xl font-bold">
                                        {title}
                                    </h1>
                                ) : null}
                                {description ? (
                                    <p className="text-balance text-muted-foreground">
                                        {description}
                                    </p>
                                ) : null}
                            </div>
                            {children}
                        </div>
                        <div className="relative hidden md:block">
                            <img
                                src="/images/bg/capitol.webp"
                                alt="Ilocos Norte capitol"
                                className="absolute inset-0 h-full w-full object-cover"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
