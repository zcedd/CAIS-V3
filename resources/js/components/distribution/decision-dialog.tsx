import { useId, useState } from 'react';
import {
    distributionStatusVariant,
    StatusDialog,
    type VisitCallbacks,
} from '@/components/distribution/status-dialog';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export type DecisionChoice = {
    value: string;
    label: string;
};

export type DecisionLine = {
    id: number;
    label: string;
    kind: 'amount' | 'quantity';
};

export type DecisionPayload = {
    decision: string;
    remarks: string;
    lines: Array<{
        id: number;
        approved_amount: string | null;
        approved_quantity: string | null;
    }>;
};

const statusLabels: Record<string, string> = {
    approved: 'Approved',
    returned: 'Returned',
    rejected: 'Rejected',
    excluded: 'Excluded',
};

type DecisionDialogProps = {
    triggerLabel: string;
    triggerVariant?: 'default' | 'outline' | 'destructive';
    triggerSize?: 'default' | 'sm';
    title: string;
    description: string;
    choices: DecisionChoice[];
    reasonRequired?: string[];
    lines?: DecisionLine[];
    onDecide: (payload: DecisionPayload, visit: VisitCallbacks) => void;
};

function blankToNull(value: string | undefined): string | null {
    const trimmed = value?.trim() ?? '';

    return trimmed === '' ? null : trimmed;
}

export function DistributionDecisionDialog({
    triggerLabel,
    triggerVariant = 'default',
    triggerSize = 'default',
    title,
    description,
    choices,
    reasonRequired = ['returned', 'rejected', 'excluded'],
    lines = [],
    onDecide,
}: DecisionDialogProps) {
    const remarksId = useId();
    const [open, setOpen] = useState(false);
    const [decision, setDecision] = useState(choices[0]?.value ?? 'approved');
    const [remarks, setRemarks] = useState('');
    const [amounts, setAmounts] = useState<Record<number, string>>({});
    const [notice, setNotice] = useState<string | null>(null);
    const [pending, setPending] = useState(false);
    const needsReason = reasonRequired.includes(decision);
    const status = statusLabels[decision] ?? decision;
    const confirmVariant =
        decision === 'rejected' || decision === 'excluded'
            ? 'destructive'
            : 'default';

    function confirm(): void {
        if (needsReason && remarks.trim() === '') {
            setNotice('A reason is required for this decision.');

            return;
        }

        onDecide(
            {
                decision,
                remarks,
                lines: lines.map((line) => ({
                    id: line.id,
                    approved_amount:
                        line.kind === 'amount'
                            ? blankToNull(amounts[line.id])
                            : null,
                    approved_quantity:
                        line.kind === 'quantity'
                            ? blankToNull(amounts[line.id])
                            : null,
                })),
            },
            {
                onStart: () => setPending(true),
                onSuccess: () => {
                    setOpen(false);
                    setRemarks('');
                    setAmounts({});
                    setNotice(null);
                },
                onFinish: () => setPending(false),
            },
        );
    }

    return (
        <>
            <Button
                type="button"
                variant={triggerVariant}
                size={triggerSize}
                onClick={() => {
                    setNotice(null);
                    setOpen(true);
                }}
            >
                {triggerLabel}
            </Button>
            <StatusDialog
                open={open}
                onOpenChange={setOpen}
                title={title}
                description={description}
                status={status}
                statusVariant={distributionStatusVariant(decision)}
                confirmLabel={
                    choices.find((choice) => choice.value === decision)
                        ?.label ?? triggerLabel
                }
                confirmVariant={confirmVariant}
                pending={pending}
                onConfirm={confirm}
                className={lines.length > 0 ? 'sm:max-w-lg' : undefined}
            >
                <FieldGroup className="gap-4">
                    <Field>
                        <FieldLabel>Decision</FieldLabel>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            spacing={2}
                            value={decision}
                            onValueChange={(value) => {
                                if (!value) {
                                    return;
                                }

                                setDecision(value);
                                setNotice(null);
                            }}
                        >
                            {choices.map((choice) => (
                                <ToggleGroupItem
                                    key={choice.value}
                                    value={choice.value}
                                >
                                    {choice.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </Field>
                    {lines.map((line) => (
                        <Field key={line.id}>
                            <FieldLabel htmlFor={`${remarksId}-${line.id}`}>
                                Approved {line.kind} for {line.label}
                            </FieldLabel>
                            <Input
                                id={`${remarksId}-${line.id}`}
                                value={amounts[line.id] ?? ''}
                                inputMode="decimal"
                                onChange={(event) =>
                                    setAmounts((current) => ({
                                        ...current,
                                        [line.id]: event.target.value,
                                    }))
                                }
                            />
                        </Field>
                    ))}
                    <Field data-invalid={notice ? true : undefined}>
                        <FieldLabel htmlFor={remarksId}>Reason</FieldLabel>
                        <Textarea
                            id={remarksId}
                            value={remarks}
                            aria-invalid={notice ? true : undefined}
                            onChange={(event) => {
                                setRemarks(event.target.value);
                                setNotice(null);
                            }}
                        />
                        <FieldDescription>
                            {needsReason
                                ? 'Required for this decision.'
                                : 'Optional for this decision.'}
                        </FieldDescription>
                        {notice ? <FieldError>{notice}</FieldError> : null}
                    </Field>
                </FieldGroup>
            </StatusDialog>
        </>
    );
}
