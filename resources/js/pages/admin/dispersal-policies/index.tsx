import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/admin/dispersal-policies';

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
    role_name: 'governor',
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

export default function DispersalPoliciesIndex({
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
            breadcrumbs: [{ title: 'Dispersal policies', href: index.url() }],
        });
    }, []);

    return (
        <>
            <Head title="Dispersal policies" />
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
                            <select
                                id="strategy"
                                name="strategy"
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                value={draft.strategy}
                                onChange={(event) =>
                                    setDraft((current) => ({
                                        ...current,
                                        strategy: event.target.value,
                                    }))
                                }
                            >
                                {strategies.map((strategy) => (
                                    <option
                                        key={strategy.value}
                                        value={strategy.value}
                                    >
                                        {strategy.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="department_id">Department</Label>
                            <select
                                id="department_id"
                                name="department_id"
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                value={draft.department_id}
                                onChange={(event) =>
                                    setDraft((current) => ({
                                        ...current,
                                        department_id: event.target.value,
                                    }))
                                }
                            >
                                <option value="">Any department</option>
                                {departments.map((department) => (
                                    <option
                                        key={department.id}
                                        value={department.id}
                                    >
                                        {department.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="program_id">Program</Label>
                            <select
                                id="program_id"
                                name="program_id"
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                value={draft.program_id}
                                onChange={(event) =>
                                    setDraft((current) => ({
                                        ...current,
                                        program_id: event.target.value,
                                    }))
                                }
                            >
                                <option value="">Any program</option>
                                {programs.map((program) => (
                                    <option key={program.id} value={program.id}>
                                        {program.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="approval_mode">Approval mode</Label>
                            <select
                                id="approval_mode"
                                name="approval_mode"
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                value={draft.approval_mode}
                                onChange={(event) =>
                                    setDraft((current) => ({
                                        ...current,
                                        approval_mode: event.target.value,
                                    }))
                                }
                            >
                                <option value="">Any mode</option>
                                {modes.map((mode) => (
                                    <option key={mode.value} value={mode.value}>
                                        {mode.label}
                                    </option>
                                ))}
                            </select>
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
                                <input
                                    type="checkbox"
                                    checked={draft[key]}
                                    onChange={(event) =>
                                        setDraft((current) => ({
                                            ...current,
                                            [key]: event.target.checked,
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
                                <select
                                    aria-label="Stage role"
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                    value={stage.role_name ?? ''}
                                    onChange={(event) =>
                                        setStages((current) =>
                                            current.map((row, rowIndex) =>
                                                rowIndex === index
                                                    ? {
                                                          ...row,
                                                          role_name:
                                                              event.target
                                                                  .value,
                                                      }
                                                    : row,
                                            ),
                                        )
                                    }
                                >
                                    <option value="">No role</option>
                                    {roles.map((role) => (
                                        <option
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </option>
                                    ))}
                                </select>
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

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Policy</th>
                                <th className="px-3 py-2">Scope</th>
                                <th className="px-3 py-2">Stages</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {policies.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-6 text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No approval policies yet. A department
                                        default is created the first time a
                                        batch is submitted.
                                    </td>
                                </tr>
                            )}
                            {policies.map((policy) => (
                                <tr
                                    key={policy.id}
                                    className="border-t align-top"
                                >
                                    <td className="px-3 py-2">
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
                                    </td>
                                    <td className="px-3 py-2">
                                        {policy.department ?? 'Any department'}
                                        <div className="text-muted-foreground">
                                            {policy.program ?? 'Any program'}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {policy.stages
                                            .map(
                                                (stage) =>
                                                    `${stage.sequence}. ${stage.name} (${stage.role_name ?? 'user'})`,
                                            )
                                            .join(', ')}
                                    </td>
                                    <td className="px-3 py-2">
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
                                                    strategy: policy.strategy,
                                                    approval_mode:
                                                        policy.approval_mode ??
                                                        '',
                                                    is_active: policy.is_active,
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
                                                        policy.min_amount ?? '',
                                                    max_amount:
                                                        policy.max_amount ?? '',
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
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
