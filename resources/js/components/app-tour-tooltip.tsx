import type { MouseEvent, PointerEvent } from 'react';
import type { TooltipRenderProps } from 'react-joyride';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

function pickTourButtonProps({
    onClick,
    'aria-label': ariaLabel,
    title,
    'data-action': dataAction,
}: TooltipRenderProps['primaryProps']) {
    return {
        'aria-label': ariaLabel,
        title,
        'data-action': dataAction,
        onPointerDown: (event: PointerEvent<HTMLButtonElement>) => {
            if (event.button !== 0) {
                return;
            }

            // Fire on pointer down so the drawer focus trap cannot cancel the click.
            event.preventDefault();
            event.stopPropagation();
            onClick?.(event as unknown as MouseEvent<HTMLButtonElement>);
        },
    };
}

export function AppTourTooltip({
    index,
    isLastStep,
    size,
    step,
    backProps,
    primaryProps,
    skipProps,
    tooltipProps,
}: TooltipRenderProps) {
    const currentStep = index + 1;
    const progressPercent = (currentStep / size) * 100;

    return (
        <div
            {...tooltipProps}
            className={cn(
                'pointer-events-auto relative z-10001 w-80 max-w-[calc(100vw-2rem)] rounded-2xl bg-popover p-4 text-popover-foreground shadow-2xl ring-1 ring-foreground/5',
            )}
        >
            {step.title ? (
                <h4 className="mb-2 text-sm font-semibold">{step.title}</h4>
            ) : null}

            <div className="text-sm text-muted-foreground">{step.content}</div>

            <div className="mt-4 space-y-3">
                <div className="flex items-center justify-between gap-3">
                    <span className="text-xs font-medium text-muted-foreground">
                        Step {currentStep} of {size}
                    </span>
                </div>

                <div
                    aria-hidden
                    className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                >
                    <div
                        className="h-full rounded-full bg-primary transition-[width] duration-300"
                        style={{ width: `${progressPercent}%` }}
                    />
                </div>

                <div
                    className={cn(
                        'flex items-center gap-2',
                        !isLastStep ? 'justify-between' : 'justify-end',
                    )}
                >
                    {!isLastStep ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            {...pickTourButtonProps(skipProps)}
                        >
                            {skipProps.title}
                        </Button>
                    ) : null}

                    <div className="flex items-center gap-2">
                        {index > 0 ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                {...pickTourButtonProps(backProps)}
                            >
                                {backProps.title}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            size="sm"
                            {...pickTourButtonProps(primaryProps)}
                        >
                            {isLastStep ? 'Finish' : 'Next'}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
