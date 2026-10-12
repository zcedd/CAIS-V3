import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, store, update } from '@/routes/admin/distribution-policies';

type Option = { value: string; label: string };
type DepartmentOption = { id: number; name: string };
type ProgramOption = { id: number; name: string; department_id: number };
type Stage = {
    name: string;
    sequence: number;
    role_name: string | null;
    user_id: number | null;
};
type Policy = {
    id: number;
    name: string;
    department_id: number | null;
    program_id: number | null;
    department: string | null;
    program: string | null;
    approval_mode: string | null;
    strategy: string;
    is_active: boolean;
    allow_partial_finalization: boolean;
    allow_amount_modification: boolean;
    allow_self_approval: boolean;
    reschedule_requires_reapproval: boolean;
    allow_resubmit_after_reject: boolean;
    block_active_batch_reuse: boolean;
    min_amount: string | null;
    max_amount: string | null;
    stages: Stage[];
};

const emptyStage = (): Stage => ({
    name: 'Executive approval',
    sequence: 1,
    role_name: 'executive',
    user_id: null,
});

type PolicyDraft = {
    name: string;
    department_id: string;
    program_id: string;
    approval_mode: string;
    strategy: string;
    min_amount: string;
    max_amount: string;
    is_active: boolean;
    allow_partial_finalization: boolean;
    allow_amount_modification: boolean;
    allow_self_approval: boolean;
    reschedule_requires_reapproval: boolean;
    allow_resubmit_after_reject: boolean;
    block_active_batch_reuse: boolean;
};

function blankDraft(): PolicyDraft {
    return {
        name: '',
        department_id: '',
        program_id: '',
        approval_mode: '',
        strategy: 'sequential',
        min_amount: '',
        max_amount: '',
        is_active: true,
        allow_partial_finalization: false,
        allow_amount_modification: false,
        allow_self_approval: false,
        reschedule_requires_reapproval: false,
        allow_resubmit_after_reject: false,
        block_active_batch_reuse: true,
    };
}

export default function DistributionPoliciesIndex({
    policies,
    departments,
    programs,
    modes,
    strategies,
    roles,
}: {
    policies: Policy[];
    departments: DepartmentOption[];
    programs: ProgramOption[];
    modes: Option[];
    strategies: Option[];
    roles: Option[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [draft, setDraft] = useState<PolicyDraft>(blankDraft);
    const [stages, setStages] = useState<Stage[]>([emptyStage()]);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Distribution policies', href: index.url() }],
        });
    }, []);

    return (
        <>
            <Head title="Distribution policies" />
            <div className="flex flex-col gap-8">
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const payload = {
                            name: draft.name,
                            department_id: draft.department_id || null,
                            program_id: draft.program_id || null,
                            approval_mode: draft.approval_mode || null,
                            strategy: draft.strategy,
                            min_amount: draft.min_amount || null,
                            max_amount: draft.max_amount || null,
                            is_active: draft.is_active,
                            allow_partial_finalization:
                                draft.allow_partial_finalization,
                            allow_amount_modification:
                                draft.allow_amount_modification,
                            allow_self_approval: draft.allow_self_approval,
                            reschedule_requires_reapproval:
                                draft.reschedule_requires_reapproval,
                            allow_resubmit_after_reject:
                                draft.allow_resubmit_after_reject,
                            block_active_batch_reuse:
                                draft.block_active_batch_reuse,
                            stages: stages.map((stage, index) => ({
                                ...stage,
                                sequence: index + 1,
                                user_id: stage.user_id || null,
                                role_name: stage.role_name || null,
                            })),
                        };

                        const reset = () => {
                            setEditingId(null);
                            setDraft(blankDraft());
                            setStages([emptyStage()]);
                        };

                        if (editingId) {
                            router.put(update.url(editingId), payload, {
                                onSuccess: reset,
                            });

                            return;
                        }

                        router.post(store.url(), payload, {
                            onSuccess: reset,
                        });
                    }}
                >
                    <div>
                        <h2 className="text-lg font-medium">
                            {editingId ? 'Update policy' : 'New policy'}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Assign stages by role. A creator cannot approve
                            their own batch unless self-approval is enabled.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={draft.name}
                                required
                                onChange={(event) =>
                                    setDraft((current) => ({
                                        ...current,
                                        name: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="strategy">Strategy</Label>
                            <Select
                                value={draft.strategy}
                                onValueChange={(value) =>
                                    setDraft((current) => ({
                                        ...current,
                                        strategy: value,
                                    }))
                                }
                            >
                                <SelectTrigger id="strategy" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {strategies.map((strategy) => (
                                        <SelectItem
                                            key={strategy.value}
                                            value={strategy.value}
                                        >
                                            {strategy.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="department_id">Department</Label>
                            <Select
                                value={draft.department_id || 'none'}
                                onValueChange={(value) =>
                                    setDraft((current) => ({
                                        ...current,
                                        department_id:
                                            value === 'none' ? '' : value,
                                    }))
                                }
                            >
                                <SelectTrigger
                                    id="department_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Any department" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Any department
                                    </SelectItem>
                                    {departments.map((department) => (
                                        <SelectItem
                                            key={department.id}
                                            value={String(department.id)}
                                        >
                                            {department.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="program_id">Program</Label>
                            <Select
                                value={draft.program_id || 'none'}
                                onValueChange={(value) =>
                                    setDraft((current) => ({
                                        ...current,
                                        program_id:
                                            value === 'none' ? '' : value,
                                    }))
                                }
                            >
                                <SelectTrigger
                                    id="program_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Any program" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Any program
                                    </SelectItem>
                                    {programs.map((program) => (
                                        <SelectItem
                                            key={program.id}
                                            value={String(program.id)}
                                        >
                                            {program.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="approval_mode">Approval mode</Label>
                            <Select
                                value={draft.approval_mode || 'none'}
                                onValueChange={(value) =>
                                    setDraft((current) => ({
                                        ...current,
                                        approval_mode:
                                            value === 'none' ? '' : value,
                                    }))
                                }
                            >
                                <SelectTrigger
                                    id="approval_mode"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Any mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Any mode
                                    </SelectItem>
                                    {modes.map((mode) => (
                                        <SelectItem
                                            key={mode.value}
                                            value={mode.value}
                                        >
                                            {mode.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="min_amount">
                                    Minimum amount
                                </Label>
                                <Input
                                    id="min_amount"
                                    inputMode="decimal"
                                    value={draft.min_amount}
                                    onChange={(event) =>
                                        setDraft((current) => ({
                                            ...current,
                                            min_amount: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="max_amount">
                                    Maximum amount
                                </Label>
                                <Input
                                    id="max_amount"
                                    inputMode="decimal"
                                    value={draft.max_amount}
                                    onChange={(event) =>
                                        setDraft((current) => ({
                                            ...current,
                                            max_amount: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>
                    </div>

                    <fieldset className="grid gap-2 text-sm md:grid-cols-2">
                        {(
                            [
                                ['is_active', 'Active'],
                                [
                                    'allow_partial_finalization',
                                    'Allow partial finalization',
                                ],
                                [
                                    'allow_amount_modification',
                                    'Allow amount changes',
                                ],
                                ['allow_self_approval', 'Allow self-approval'],
                                [
                                    'reschedule_requires_reapproval',
                                    'Reschedule requires reapproval',
                                ],
                                [
                                    'allow_resubmit_after_reject',
                                    'Allow resubmission after rejection',
                                ],
                                [
                                    'block_active_batch_reuse',
                                    'Block reuse on another active batch',
                                ],
                            ] as const
                        ).map(([key, label]) => (
                            <label
                                key={key}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    checked={draft[key]}
                                    onCheckedChange={(checked) =>
                                        setDraft((current) => ({
                                            ...current,
                                            [key]: checked === true,
                                        }))
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    </fieldset>

                    <div className="grid gap-3">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-medium">Stages</h3>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setStages((current) => [
                                        ...current,
                                        {
                                            ...emptyStage(),
                                            sequence: current.length + 1,
                                            name: `Stage ${current.length + 1}`,
                                        },
                                    ])
                                }
                            >
                                Add stage
                            </Button>
                        </div>
                        {stages.map((stage, index) => (
                            <div
                                key={index}
                                className="grid gap-2 rounded-md border p-3 md:grid-cols-3"
                            >
                                <Input
                                    aria-label="Stage name"
                                    value={stage.name}
                                    onChange={(event) =>
                                        setStages((current) =>
                                            current.map((row, rowIndex) =>
                                                rowIndex === index
                                                    ? {
                                                          ...row,
                                                          name: event.target
                                                              .value,
                                                      }
                                                    : row,
                                            ),
                                        )
                                    }
                                />
                                <Select
                                    value={stage.role_name || 'none'}
                                    onValueChange={(value) =>
                                        setStages((current) =>
                                            current.map((row, rowIndex) =>
                                                rowIndex === index
                                                    ? {
                                                          ...row,
                                                          role_name:
                                                              value === 'none'
                                                                  ? null
                                                                  : value,
                                                      }
                                                    : row,
                                            ),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        aria-label="Stage role"
                                    >
                                        <SelectValue placeholder="No role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            No role
                                        </SelectItem>
                                        {roles.map((role) => (
                                            <SelectItem
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Input
                                    aria-label="Assigned user id"
                                    inputMode="numeric"
                                    placeholder="User id, optional"
                                    value={stage.user_id ?? ''}
                                    onChange={(event) =>
                                        setStages((current) =>
                                            current.map((row, rowIndex) =>
                                                rowIndex === index
                                                    ? {
                                                          ...row,
                                                          user_id: event.target
                                                              .value
                                                              ? Number(
                                                                    event.target
                                                                        .value,
                                                                )
                                                              : null,
                                                      }
                                                    : row,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        ))}
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit">
                            {editingId ? 'Update policy' : 'Save policy'}
                        </Button>
                        {editingId && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setEditingId(null);
                                    setDraft(blankDraft());
                                    setStages([emptyStage()]);
                                }}
                            >
                                Cancel edit
                            </Button>
                        )}
                    </div>
                </form>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Policy</TableHead>
                                    <TableHead>Scope</TableHead>
                                    <TableHead>Stages</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {policies.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            className="text-muted-foreground"
                                            colSpan={4}
                                        >
                                            No approval policies yet. A
                                            department default is created the
                                            first time a batch is submitted.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {policies.map((policy) => (
                                    <TableRow
                                        key={policy.id}
                                        className="align-top"
                                    >
                                        <TableCell className="whitespace-normal">
                                            <div className="font-medium">
                                                {policy.name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {policy.strategy}
                                                {policy.approval_mode
                                                    ? ` · ${policy.approval_mode}`
                                                    : ''}
                                            </div>
                                            <Badge variant="outline">
                                                {policy.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {policy.department ??
                                                'Any department'}
                                            <div className="text-muted-foreground">
                                                {policy.program ??
                                                    'Any program'}
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {policy.stages
                                                .map(
                                                    (stage) =>
                                                        `${stage.sequence}. ${stage.name} (${stage.role_name ?? 'user'})`,
                                                )
                                                .join(', ')}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setEditingId(policy.id);
                                                    setDraft({
                                                        ...blankDraft(),
                                                        name: policy.name,
                                                        department_id:
                                                            policy.department_id
                                                                ? String(
                                                                      policy.department_id,
                                                                  )
                                                                : '',
                                                        program_id:
                                                            policy.program_id
                                                                ? String(
                                                                      policy.program_id,
                                                                  )
                                                                : '',
                                                        strategy:
                                                            policy.strategy,
                                                        approval_mode:
                                                            policy.approval_mode ??
                                                            '',
                                                        is_active:
                                                            policy.is_active,
                                                        allow_partial_finalization:
                                                            policy.allow_partial_finalization,
                                                        allow_amount_modification:
                                                            policy.allow_amount_modification,
                                                        allow_self_approval:
                                                            policy.allow_self_approval,
                                                        reschedule_requires_reapproval:
                                                            policy.reschedule_requires_reapproval,
                                                        allow_resubmit_after_reject:
                                                            policy.allow_resubmit_after_reject,
                                                        block_active_batch_reuse:
                                                            policy.block_active_batch_reuse,
                                                        min_amount:
                                                            policy.min_amount ??
                                                            '',
                                                        max_amount:
                                                            policy.max_amount ??
                                                            '',
                                                    });
                                                    setStages(
                                                        policy.stages.length > 0
                                                            ? policy.stages
                                                            : [emptyStage()],
                                                    );
                                                }}
                                            >
                                                Edit stages
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
