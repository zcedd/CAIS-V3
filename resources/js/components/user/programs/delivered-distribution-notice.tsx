import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { Permission } from '@/lib/permissions';
import { store as startDistribution } from '@/routes/user/assistances/distribution';

export type ProgramDistributionMode = 'individual' | 'batch' | 'both';

export function programAllowsDistribution(
    mode: ProgramDistributionMode | null | undefined,
    kind: 'individual' | 'batch',
): boolean {
    const resolved = mode ?? 'batch';

    return resolved === 'both' || resolved === kind;
}

export function DeliveredDistributionNotice({
    departmentSlug,
    programId,
    assistanceId,
    distributionMode = 'batch',
}: {
    departmentSlug: string;
    programId: number;
    assistanceId: number;
    distributionMode?: ProgramDistributionMode | null;
}) {
    const { can } = useCan();
    const canDistribute = can(Permission.DistributionCreate);
    const allowIndividual = programAllowsDistribution(
        distributionMode,
        'individual',
    );
    const allowBatch = programAllowsDistribution(distributionMode, 'batch');

    function start(mode: 'individual' | 'batch'): void {
        router.post(
            startDistribution.url({
                department: departmentSlug,
                program: programId,
                assistance: assistanceId,
            }),
            { mode },
            { preserveScroll: true },
        );
    }

    return (
        <div className="flex flex-col gap-3 rounded-lg border border-border bg-muted/40 p-3">
            <div className="space-y-1">
                <p className="text-sm font-medium">
                    Delivery goes through distribution
                </p>
                <p className="text-sm text-muted-foreground">
                    Marking this request delivered requires a completed
                    distribution, acknowledgment, and receipt.
                    {allowIndividual
                        ? ' Individual distribution releases this beneficiary directly after the request is approved.'
                        : ''}
                    {allowBatch
                        ? ' Batch distribution waits for executive approval.'
                        : ''}
                </p>
            </div>
            {canDistribute && (allowIndividual || allowBatch) ? (
                <div className="flex flex-wrap gap-2">
                    {allowIndividual ? (
                        <Button
                            type="button"
                            onClick={() => start('individual')}
                        >
                            Individual distribution
                        </Button>
                    ) : null}
                    {allowBatch ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => start('batch')}
                        >
                            Batch distribution
                        </Button>
                    ) : null}
                </div>
            ) : canDistribute ? (
                <p className="text-sm text-muted-foreground">
                    Configure this program&apos;s distribution mode before
                    starting a release.
                </p>
            ) : (
                <p className="text-sm text-muted-foreground">
                    You are not authorized to start a distribution.
                </p>
            )}
        </div>
    );
}
