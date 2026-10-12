import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const OPTIONS: { value: Appearance; label: string; icon: typeof Sun }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

export function HeaderThemeToggle() {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const CurrentIcon = resolvedAppearance === 'dark' ? Moon : Sun;

    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Appearance"
                            data-test="header-theme-toggle"
                        >
                            <CurrentIcon />
                            <span className="sr-only">Appearance</span>
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>Appearance</TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end">
                {OPTIONS.map((option) => {
                    const Icon = option.icon;
                    const selected = appearance === option.value;

                    return (
                        <DropdownMenuItem
                            key={option.value}
                            onClick={() => updateAppearance(option.value)}
                        >
                            <Icon />
                            {option.label}
                            <Check
                                className={cn(
                                    'ml-auto',
                                    selected ? 'opacity-100' : 'opacity-0',
                                )}
                            />
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
