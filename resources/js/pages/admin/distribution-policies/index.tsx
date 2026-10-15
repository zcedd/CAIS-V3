import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { createDistributionPolicyColumns } from '@/pages/admin/distribution-policies/policy-columns';
import type {
    DistributionPolicy as Policy,
    DistributionPolicyStage as Stage,
} from '@/pages/admin/distribution-policies/policy-columns';
import { DistributionPolicyToolbar } from '@/pages/admin/distribution-policies/policy-toolbar';
import { index, store, update } from '@/routes/admin/distribution-policies';

type Option = { value: string; label: string };
type DepartmentOption = { id: number; name: string };
type ProgramOption = { id: number; name: string; department_id: number };

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
    const pageErrors = (usePage().props.errors ?? {}) as Record<
        string,
        string | string[] | undefined
    >;
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [draft, setDraft] = useState<PolicyDraft>(blankDraft);
    const [stages, setStages] = useState<Stage[]>([emptyStage()]);
    const [search, setSearch] = useState('');
    const [departmentId, setDepartmentId] = useState<number | null>(null);
    const [status, setStatus] = useState<string[]>([]);

    const resetForm = () => {
        setEditingId(null);
        setDraft(blankDraft());
        setStages([emptyStage()]);
    };

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Distribution policies', href: index.url() },
            ],
        });
    }, []);

    const openEdit = (policy: Policy) => {
        setDrawerOpen(true);
        setEditingId(policy.id);
        setDraft({
            ...blankDraft(),
            name: policy.name,
            department_id: policy.department_id
                ? String(policy.department_id)
                : '',
            program_id: policy.program_id ? String(policy.program_id) : '',
            strategy: policy.strategy,
            approval_mode: policy.approval_mode ?? '',
            is_active: policy.is_active,
            allow_partial_finalization: policy.allow_partial_finalization,
            allow_amount_modification: policy.allow_amount_modification,
            allow_self_approval: policy.allow_self_approval,
            reschedule_requires_reapproval:
                policy.reschedule_requires_reapproval,
            allow_resubmit_after_reject: policy.allow_resubmit_after_reject,
            block_active_batch_reuse: policy.block_active_batch_reuse,
            min_amount: policy.min_amount ?? '',
            max_amount: policy.max_amount ?? '',
        });
        setStages(policy.stages.length > 0 ? policy.stages : [emptyStage()]);
    };

    const filteredPolicies = useMemo(() => {
        const query = search.trim().toLowerCase();

        return policies.filter((policy) => {
            if (
                departmentId !== null &&
                policy.department_id !== departmentId
            ) {
                return false;
            }

            if (status.length > 0) {
                const policyStatus = policy.is_active ? 'active' : 'inactive';

                if (!status.includes(policyStatus)) {
                    return false;
                }
            }

            if (query === '') {
                return true;
            }

            const haystack = [
                policy.name,
                policy.department,
                policy.program,
                policy.strategy,
                policy.approval_mode,
                ...policy.stages.map((stage) => stage.name),
            ]
                .filter((value) => value !== null && value !== '')
                .join(' ')
                .toLowerCase();

            return haystack.includes(query);
        });
    }, [policies, search, departmentId, status]);

    const columns = useMemo(
        () =>
            createDistributionPolicyColumns({
                strategies,
                modes,
                onEdit: openEdit,
            }),
        [strategies, modes],
    );

    const hasActiveFilters =
        search.trim() !== '' || departmentId !== null || status.length > 0;

    return (
        <>
            <Head title="Distribution policies" />
            <div className="flex flex-col gap-4">
                <Drawer
                    open={drawerOpen}
                    onOpenChange={setDrawerOpen}
                    direction="right"
                >
                    <DrawerContent className="h-dvh max-h-dvh overflow-hidden data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                        <DrawerHeader className="shrink-0">
                            <DrawerTitle>
                                {editingId ? 'Update policy' : 'New policy'}
                            </DrawerTitle>
                            <DrawerDescription>
                                Assign stages by role. A creator cannot approve
                                their own batch unless self-approval is enabled.
                            </DrawerDescription>
                            {Object.keys(pageErrors).length > 0 && (
                                <p
                                    className="text-sm text-destructive"
                                    role="alert"
                                >
                                    {Object.values(pageErrors)
                                        .flatMap((message) =>
                                            Array.isArray(message)
                                                ? message
                                                : message
                                                  ? [message]
                                                  : [],
                                        )
                                        .join(' ')}
                                </p>
                            )}
                        </DrawerHeader>
                        <form
                            className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4"
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
                                    allow_self_approval:
                                        draft.allow_self_approval,
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
                                    resetForm();
                                    setDrawerOpen(false);
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
                                        <SelectTrigger
                                            id="strategy"
                                            className="w-full"
                                        >
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
                                    <Label htmlFor="department_id">
                                        Department
                                    </Label>
                                    <Select
                                        value={draft.department_id || 'none'}
                                        onValueChange={(value) =>
                                            setDraft((current) => ({
                                                ...current,
                                                department_id:
                                                    value === 'none'
                                                        ? ''
                                                        : value,
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
                                                    value={String(
                                                        department.id,
                                                    )}
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
                                                    value === 'none'
                                                        ? ''
                                                        : value,
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
                                    <Label htmlFor="approval_mode">
                                        Approval mode
                                    </Label>
                                    <Select
                                        value={draft.approval_mode || 'none'}
                                        onValueChange={(value) =>
                                            setDraft((current) => ({
                                                ...current,
                                                approval_mode:
                                                    value === 'none'
                                                        ? ''
                                                        : value,
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
                                                    min_amount:
                                                        event.target.value,
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
                                                    max_amount:
                                                        event.target.value,
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
                                        [
                                            'allow_self_approval',
                                            'Allow self-approval',
                                        ],
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
                                    <h3 className="text-sm font-medium">
                                        Stages
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setStages((current) => [
                                                ...current,
                                                {
                                                    ...emptyStage(),
                                                    sequence:
                                                        current.length + 1,
                                                    name: `Stage ${current.length + 1}`,
                                                },
                                            ])
                                        }
                                    >
                                        Add stage
                                    </Button>
                                </div>
                                {stages.map((stage, index) => {
                                    const removeLabel = stage.has_tasks
                                        ? 'This stage has approval tasks and cannot be removed.'
                                        : stages.length === 1
                                          ? 'A policy needs at least one stage.'
                                          : `Remove ${stage.name}`;

                                    return (
                                        <div
                                            key={stage.id ?? `new-${index}`}
                                            className="grid gap-2 rounded-md border p-3"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-sm font-medium">
                                                    Stage {index + 1}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={removeLabel}
                                                    title={removeLabel}
                                                    disabled={
                                                        stages.length === 1 ||
                                                        stage.has_tasks === true
                                                    }
                                                    onClick={() =>
                                                        setStages((current) =>
                                                            current.filter(
                                                                (_, rowIndex) =>
                                                                    rowIndex !==
                                                                    index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                            <div className="grid gap-2 md:grid-cols-3">
                                                <Input
                                                    aria-label="Stage name"
                                                    value={stage.name}
                                                    onChange={(event) =>
                                                        setStages((current) =>
                                                            current.map(
                                                                (
                                                                    row,
                                                                    rowIndex,
                                                                ) =>
                                                                    rowIndex ===
                                                                    index
                                                                        ? {
                                                                              ...row,
                                                                              name: event
                                                                                  .target
                                                                                  .value,
                                                                          }
                                                                        : row,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <Select
                                                    value={
                                                        stage.role_name ||
                                                        'none'
                                                    }
                                                    onValueChange={(value) =>
                                                        setStages((current) =>
                                                            current.map(
                                                                (
                                                                    row,
                                                                    rowIndex,
                                                                ) =>
                                                                    rowIndex ===
                                                                    index
                                                                        ? {
                                                                              ...row,
                                                                              role_name:
                                                                                  value ===
                                                                                  'none'
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
                                                                value={
                                                                    role.value
                                                                }
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
                                                            current.map(
                                                                (
                                                                    row,
                                                                    rowIndex,
                                                                ) =>
                                                                    rowIndex ===
                                                                    index
                                                                        ? {
                                                                              ...row,
                                                                              user_id:
                                                                                  event
                                                                                      .target
                                                                                      .value
                                                                                      ? Number(
                                                                                            event
                                                                                                .target
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
                                        </div>
                                    );
                                })}
                            </div>

                            <DrawerFooter className="px-0">
                                <Button type="submit">
                                    {editingId
                                        ? 'Update policy'
                                        : 'Save policy'}
                                </Button>
                                <DrawerClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DrawerClose>
                            </DrawerFooter>
                        </form>
                    </DrawerContent>
                </Drawer>

                <Card>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            data={filteredPolicies}
                            emptyMessage={
                                hasActiveFilters
                                    ? 'No policies match your filters.'
                                    : 'No approval policies yet. A department default is created the first time a batch is submitted.'
                            }
                            toolbar={(table, columnVisibility) => (
                                <DistributionPolicyToolbar
                                    table={table}
                                    columnVisibility={columnVisibility}
                                    search={search}
                                    departmentId={departmentId}
                                    status={status}
                                    departments={departments}
                                    onSearchChange={setSearch}
                                    onDepartmentChange={setDepartmentId}
                                    onStatusChange={setStatus}
                                    onReset={() => {
                                        setSearch('');
                                        setDepartmentId(null);
                                        setStatus([]);
                                    }}
                                    onCreate={() => {
                                        resetForm();
                                        setDrawerOpen(true);
                                    }}
                                />
                            )}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
