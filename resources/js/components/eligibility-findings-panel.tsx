'use client';

import InputError from '@/components/input-error';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { show as assistanceShow } from '@/routes/user/assistances';
import type {
    EligibilityFinding,
    EligibilityHistoryItem,
} from '@/types/eligibility';
import { Link } from '@inertiajs/react';
import { Ban, TriangleAlert } from 'lucide-react';

type EligibilityFindingsPanelProps = {
    departmentSlug: string;
    findings: EligibilityFinding[];
    history: EligibilityHistoryItem[];
    overrideReason: string;
    onOverrideReasonChange: (value: string) => void;
    overrideError?: string;
    isLoading?: boolean;
};

export function EligibilityFindingsPanel({
    departmentSlug,
    findings,
    history,
    overrideReason,
    onOverrideReasonChange,
    overrideError,
    isLoading = false,
}: EligibilityFindingsPanelProps) {
    const hardFindings = findings.filter(
        (finding) => finding.severity === 'hard',
    );
    const softFindings = findings.filter(
        (finding) => finding.severity === 'soft',
    );

    if (
        !isLoading &&
        hardFindings.length === 0 &&
        softFindings.length === 0 &&
        history.length === 0
    ) {
        return null;
    }

    return (
        <div className="space-y-3">
            {isLoading ? (
                <p className="text-sm text-muted-foreground">
                    Checking assistance history...
                </p>
            ) : null}

            {history.length > 0 ? (
                <div className="space-y-2 rounded-lg border p-3">
                    <p className="text-sm font-medium">
                        Cross-program history
                    </p>
                    <ul className="space-y-2">
                        {history.map((row) => (
                            <li key={row.id} className="text-sm">
                                <Link
                                    href={assistanceShow.url({
                                        department: departmentSlug,
                                        program: row.program_id,
                                        assistance: row.id,
                                    })}
                                    className="font-medium text-foreground"
                                >
                                    {row.program_name}
                                </Link>
                                <span className="mt-0.5 block text-muted-foreground">
                                    {[
                                        row.status,
                                        row.date_requested
                                            ? `Requested ${row.date_requested}`
                                            : null,
                                        row.date_delivered
                                            ? `Delivered ${row.date_delivered}`
                                            : null,
                                        row.items
                                            .map(
                                                (item) =>
                                                    `${item.name} × ${item.quantity}${item.is_received ? ' released' : ''}`,
                                            )
                                            .join(', '),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {hardFindings.length > 0 ? (
                <Alert variant="destructive">
                    <Ban />
                    <AlertTitle>Cannot encode this request</AlertTitle>
                    <AlertDescription>
                        <ul className="list-inside list-disc">
                            {hardFindings.map((finding) => (
                                <li key={finding.code}>{finding.message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : null}

            {softFindings.length > 0 ? (
                <Alert>
                    <TriangleAlert />
                    <AlertTitle>Eligibility warning</AlertTitle>
                    <AlertDescription>
                        <ul className="mb-3 list-inside list-disc">
                            {softFindings.map((finding, index) => (
                                <li key={`${finding.code}-${index}`}>
                                    {finding.message}
                                </li>
                            ))}
                        </ul>
                        <div className="space-y-2">
                            <Label htmlFor="eligibility-override-reason">
                                Reason to proceed
                            </Label>
                            <Textarea
                                id="eligibility-override-reason"
                                name="eligibility_override_reason"
                                value={overrideReason}
                                onChange={(event) =>
                                    onOverrideReasonChange(event.target.value)
                                }
                                rows={3}
                                placeholder="Explain why this request should continue"
                            />
                            <InputError message={overrideError} />
                        </div>
                    </AlertDescription>
                </Alert>
            ) : (
                <input
                    type="hidden"
                    name="eligibility_override_reason"
                    value={overrideReason}
                />
            )}
        </div>
    );
}

export function hasHardEligibilityFindings(
    findings: EligibilityFinding[],
): boolean {
    return findings.some((finding) => finding.severity === 'hard');
}
