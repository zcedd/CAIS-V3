import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

const barHeights = ['h-16', 'h-24', 'h-32', 'h-40', 'h-28', 'h-36', 'h-20'];

export function Chart() {
    return (
        <Card role="status" aria-busy="true" className="h-full w-full">
            <CardHeader>
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-3 w-48" />
            </CardHeader>
            <CardContent>
                <div className="flex h-48 items-end gap-3">
                    {barHeights.map((height) => (
                        <Skeleton
                            key={height}
                            className={`${height} w-full rounded-t-md`}
                        />
                    ))}
                </div>
                <span className="sr-only">Loading...</span>
            </CardContent>
        </Card>
    );
}
