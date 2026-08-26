'use client';

import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { show as beneficiaryShow } from '@/routes/user/beneficiaries';
import type { DuplicateCandidate } from '@/types/eligibility';
import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';

type DuplicateCandidatesAlertProps = {
    departmentSlug: string;
    candidates: DuplicateCandidate[];
    error?: string;
    acknowledged: boolean;
    onAcknowledge: () => void;
};

export function DuplicateCandidatesAlert({
    departmentSlug,
    candidates,
    error,
    acknowledged,
    onAcknowledge,
}: DuplicateCandidatesAlertProps) {
    if (candidates.length === 0) {
        return null;
    }

    return (
        <Alert>
            <TriangleAlert />
            <AlertTitle>Possible duplicate records</AlertTitle>
            <AlertDescription>
                <p className="mb-2">
                    {error ??
                        'Existing beneficiaries look similar to this record. Open the match instead of creating another profile unless you are sure this is a different person.'}
                </p>
                <ul className="space-y-2">
                    {candidates.map((candidate) => (
                        <li key={candidate.id} className="text-sm">
                            <Link
                                href={beneficiaryShow.url({
                                    department: departmentSlug,
                                    beneficiary: candidate.id,
                                })}
                                className="font-medium text-foreground"
                            >
                                {candidate.cais_number ?? 'No CAIS'} —{' '}
                                {candidate.name}
                            </Link>
                            <span className="mt-0.5 block text-muted-foreground">
                                {[
                                    candidate.birthday,
                                    candidate.barangay,
                                    candidate.match_reasons.join(', '),
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </li>
                    ))}
                </ul>
                {acknowledged ? (
                    <p className="mt-3 text-sm font-medium text-foreground">
                        Create anyway is selected. Submit the form to continue.
                    </p>
                ) : (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="mt-3"
                        onClick={onAcknowledge}
                    >
                        Create anyway
                    </Button>
                )}
            </AlertDescription>
        </Alert>
    );
}
