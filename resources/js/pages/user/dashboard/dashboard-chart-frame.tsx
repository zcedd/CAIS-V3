import { useEffect, useRef, useState  } from 'react';
import type {ReactNode} from 'react';
import { cn } from '@/lib/utils';

type DashboardChartFrameProps = {
    children: ReactNode;
    /** Fixed chart area height in px */
    height?: number;
    className?: string;
};

/**
 * Waits until the container has a real width before mounting Recharts.
 * Prevents ResponsiveContainer from collapsing to 0 (blank/broken charts).
 */
export function DashboardChartFrame({
    children,
    height = 260,
    className,
}: DashboardChartFrameProps) {
    const ref = useRef<HTMLDivElement>(null);
    const [ready, setReady] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const update = () => {
            setReady(element.clientWidth > 0 && element.clientHeight > 0);
        };

        update();

        const observer = new ResizeObserver(update);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            className={cn('relative w-full min-w-0', className)}
            style={{ height }}
        >
            {ready ? (
                children
            ) : (
                <div className="h-full w-full animate-pulse rounded-md bg-muted/40" />
            )}
        </div>
    );
}
