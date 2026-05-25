import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type DataTableSkeletonProps = {
    columnCount?: number;
    rowCount?: number;
};

export function DataTableSkeleton({
    columnCount = 8,
    rowCount = 8,
}: DataTableSkeletonProps) {
    return (
        <div className="rounded-md border" aria-busy="true" aria-label="Loading table">
            <Table>
                <TableHeader>
                    <TableRow>
                        {Array.from({ length: columnCount }).map((_, index) => (
                            <TableHead key={index}>
                                <Skeleton className="h-4 w-full max-w-[120px]" />
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {Array.from({ length: rowCount }).map((_, rowIndex) => (
                        <TableRow key={rowIndex}>
                            {Array.from({ length: columnCount }).map(
                                (_, cellIndex) => (
                                    <TableCell key={cellIndex}>
                                        <Skeleton
                                            className="h-4 w-full"
                                            style={{
                                                maxWidth:
                                                    cellIndex === 0
                                                        ? '2.5rem'
                                                        : `${60 + ((rowIndex + cellIndex) % 4) * 15}%`,
                                            }}
                                        />
                                    </TableCell>
                                ),
                            )}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
