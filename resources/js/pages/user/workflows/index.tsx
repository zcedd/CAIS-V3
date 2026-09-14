'use client';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    store as storeWorkflow,
    update as updateWorkflow,
    index as departmentWorkflowsIndex,
} from '@/routes/user/workflows';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type WorkflowStatus = {
    id: number;
    name: string;
    code: string | null;
};

type WorkflowReason = {
    id: number;
    name: string;
    request_status_id: number;
};

type WorkflowStepPayload = {
    id?: number;
    request_status_id: number;
    request_status_name?: string | null;
    request_status_code?: string | null;
    sort_order: number;
    default_request_sub_status_id: number | null;
    sla_hours: number | null;
    requires_assignee: boolean;
    assigned_to_id: number | null;
    allows_skip_to_deliver: boolean;
    transition_status_ids: number[];
};

type WorkflowPayload = {
    id: number;
    name: string;
    template: string | null;
    is_default: boolean;
    staff_entry_request_status_id: number | null;
    public_entry_request_status_id: number | null;
    steps: WorkflowStepPayload[];
};

type DraftStep = {
    request_status_id: string;
    default_request_sub_status_id: string;
    sla_hours: string;
    assigned_to_id: string;
    allows_skip_to_deliver: boolean;
};

function stepsFromWorkflow(workflow: WorkflowPayload): DraftStep[] {
    return [...workflow.steps]
        .sort((left, right) => left.sort_order - right.sort_order)
        .map((step) => ({
            request_status_id: String(step.request_status_id),
            default_request_sub_status_id: step.default_request_sub_status_id
                ? String(step.default_request_sub_status_id)
                : '',
            sla_hours: step.sla_hours ? String(step.sla_hours) : '',
            assigned_to_id: step.assigned_to_id
                ? String(step.assigned_to_id)
                : 'none',
            allows_skip_to_deliver: step.allows_skip_to_deliver,
        }));
}

export default function UserWorkflowsIndex({
    department,
    workflows,
    statuses,
    reasons,
    staff_options = [],
    can_create = false,
}: {
    department: DepartmentSummary;
    workflows: WorkflowPayload[];
    statuses: WorkflowStatus[];
    reasons: WorkflowReason[];
    staff_options?: { id: number; name: string }[];
    can_create?: boolean;
}) {
    const [selectedId, setSelectedId] = useState<number | null>(
        workflows[0]?.id ?? null,
    );
    const [createName, setCreateName] = useState('');
    const [createTemplate, setCreateTemplate] = useState('standard');
    const [createDefault, setCreateDefault] = useState(false);

    const selected = workflows.find((workflow) => workflow.id === selectedId);

    const [name, setName] = useState(selected?.name ?? '');
    const [isDefault, setIsDefault] = useState(selected?.is_default ?? false);
    const [staffEntryId, setStaffEntryId] = useState(
        selected?.staff_entry_request_status_id
            ? String(selected.staff_entry_request_status_id)
            : '',
    );
    const [steps, setSteps] = useState<DraftStep[]>(
        selected ? stepsFromWorkflow(selected) : [],
    );

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Workflows',
                    href: departmentWorkflowsIndex.url(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    useEffect(() => {
        if (selectedId !== null && workflows.some((workflow) => workflow.id === selectedId)) {
            return;
        }

        setSelectedId(workflows[0]?.id ?? null);
    }, [workflows, selectedId]);

    useEffect(() => {
        if (!selected) {
            setName('');
            setIsDefault(false);
            setStaffEntryId('');
            setSteps([]);

            return;
        }

        setName(selected.name);
        setIsDefault(selected.is_default);
        setStaffEntryId(
            selected.staff_entry_request_status_id
                ? String(selected.staff_entry_request_status_id)
                : '',
        );
        setSteps(stepsFromWorkflow(selected));
    }, [selected]);

    const usedStatusIds = useMemo(
        () => new Set(steps.map((step) => step.request_status_id)),
        [steps],
    );

    const addableStatuses = statuses.filter(
        (status) => !usedStatusIds.has(String(status.id)),
    );

    const updateStep = (index: number, changes: Partial<DraftStep>) => {
        setSteps((current) =>
            current.map((step, stepIndex) =>
                stepIndex === index ? { ...step, ...changes } : step,
            ),
        );
    };

    return (
        <>
            <Head title="Workflows" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Workflows
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        The stages a request can move through. Programs use the
                        department default unless they pick another workflow.
                    </p>
                </div>

                <div className="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Pipelines</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <ul className="space-y-1">
                                {workflows.map((workflow) => (
                                    <li key={workflow.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setSelectedId(workflow.id)
                                            }
                                            className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm ${
                                                workflow.id === selectedId
                                                    ? 'bg-primary/10 font-medium text-foreground'
                                                    : 'text-muted-foreground hover:bg-muted'
                                            }`}
                                        >
                                            <span>{workflow.name}</span>
                                            {workflow.is_default ? (
                                                <Badge variant="outline">
                                                    Default
                                                </Badge>
                                            ) : null}
                                        </button>
                                    </li>
                                ))}
                            </ul>

                            {can_create ? (
                            <Form
                                {...storeWorkflow.form.post(department.slug)}
                                disableWhileProcessing
                                resetOnSuccess
                                transform={() => ({
                                    name: createName,
                                    template: createTemplate,
                                    is_default: createDefault,
                                    steps: [],
                                })}
                                onSuccess={() => {
                                    setCreateName('');
                                    setCreateTemplate('standard');
                                    setCreateDefault(false);
                                    toast.success('Workflow created.');
                                }}
                                className="space-y-3 border-t pt-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="workflow-create-name">
                                                New workflow
                                            </Label>
                                            <Input
                                                id="workflow-create-name"
                                                value={createName}
                                                onChange={(event) =>
                                                    setCreateName(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Walk-in relief"
                                            />
                                            <InputError
                                                message={errors.name}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="workflow-create-template">
                                                Template
                                            </Label>
                                            <Select
                                                value={createTemplate}
                                                onValueChange={
                                                    setCreateTemplate
                                                }
                                            >
                                                <SelectTrigger id="workflow-create-template">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="standard">
                                                        Standard
                                                    </SelectItem>
                                                    <SelectItem value="walk_in">
                                                        Walk-in
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <label className="flex items-center gap-2 text-sm">
                                            <Input
                                                type="checkbox"
                                                className="size-4"
                                                checked={createDefault}
                                                onChange={(event) =>
                                                    setCreateDefault(
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            Set as department default
                                        </label>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={
                                                processing ||
                                                createName.trim() === ''
                                            }
                                        >
                                            <Plus className="size-4" />
                                            {processing
                                                ? 'Creating...'
                                                : 'Create from template'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                            ) : null}
                        </CardContent>
                    </Card>

                    {selected ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Edit {selected.name}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...updateWorkflow.form.put({
                                        department: department.slug,
                                        workflow: selected.id,
                                    })}
                                    disableWhileProcessing
                                    options={{ preserveScroll: true }}
                                    transform={() => ({
                                        name,
                                        is_default: isDefault,
                                        staff_entry_request_status_id:
                                            staffEntryId === ''
                                                ? null
                                                : Number(staffEntryId),
                                        steps: steps.map((step, index) => ({
                                            request_status_id: Number(
                                                step.request_status_id,
                                            ),
                                            sort_order: (index + 1) * 10,
                                            default_request_sub_status_id:
                                                step.default_request_sub_status_id ===
                                                ''
                                                    ? null
                                                    : Number(
                                                          step.default_request_sub_status_id,
                                                      ),
                                            sla_hours:
                                                step.sla_hours === ''
                                                    ? null
                                                    : Number(step.sla_hours),
                                            assigned_to_id:
                                                step.assigned_to_id ===
                                                    'none' ||
                                                step.assigned_to_id === ''
                                                    ? null
                                                    : Number(
                                                          step.assigned_to_id,
                                                      ),
                                            allows_skip_to_deliver:
                                                step.allows_skip_to_deliver,
                                            transition_status_ids: [],
                                        })),
                                    })}
                                    onSuccess={() =>
                                        toast.success('Workflow updated.')
                                    }
                                    className="space-y-4"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="space-y-2">
                                                <Label htmlFor="workflow-name">
                                                    Name
                                                </Label>
                                                <Input
                                                    id="workflow-name"
                                                    value={name}
                                                    onChange={(event) =>
                                                        setName(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>

                                            <label className="flex items-center gap-2 text-sm">
                                                <Input
                                                    type="checkbox"
                                                    className="size-4"
                                                    checked={isDefault}
                                                    onChange={(event) =>
                                                        setIsDefault(
                                                            event.target.checked,
                                                        )
                                                    }
                                                />
                                                Department default
                                            </label>

                                            <div className="space-y-2">
                                                <Label htmlFor="workflow-staff-entry">
                                                    Staff encode entry
                                                </Label>
                                                <Select
                                                    value={staffEntryId}
                                                    onValueChange={
                                                        setStaffEntryId
                                                    }
                                                >
                                                    <SelectTrigger id="workflow-staff-entry">
                                                        <SelectValue placeholder="Select stage" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {statuses.map(
                                                            (status) => (
                                                                <SelectItem
                                                                    key={
                                                                        status.id
                                                                    }
                                                                    value={String(
                                                                        status.id,
                                                                    )}
                                                                >
                                                                    {status.name}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-3">
                                                <div className="flex items-center justify-between gap-2">
                                                    <Label>Stages</Label>
                                                    {addableStatuses.length >
                                                    0 ? (
                                                        <Select
                                                            onValueChange={(
                                                                value,
                                                            ) => {
                                                                setSteps(
                                                                    (current) => [
                                                                        ...current,
                                                                        {
                                                                            request_status_id:
                                                                                value,
                                                                            default_request_sub_status_id:
                                                                                '',
                                                                            sla_hours:
                                                                                '',
                                                                            assigned_to_id:
                                                                                'none',
                                                                            allows_skip_to_deliver:
                                                                                false,
                                                                        },
                                                                    ],
                                                                );
                                                            }}
                                                        >
                                                            <SelectTrigger className="h-8 w-48">
                                                                <SelectValue placeholder="Add stage" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {addableStatuses.map(
                                                                    (
                                                                        status,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                status.id
                                                                            }
                                                                            value={String(
                                                                                status.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                status.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    ) : null}
                                                </div>
                                                <InputError
                                                    message={errors.steps}
                                                />

                                                {steps.map((step, index) => {
                                                    const statusReasons =
                                                        reasons.filter(
                                                            (reason) =>
                                                                String(
                                                                    reason.request_status_id,
                                                                ) ===
                                                                step.request_status_id,
                                                        );
                                                    const statusName =
                                                        statuses.find(
                                                            (status) =>
                                                                String(
                                                                    status.id,
                                                                ) ===
                                                                step.request_status_id,
                                                        )?.name ?? 'Stage';

                                                    return (
                                                        <div
                                                            key={`${step.request_status_id}-${index}`}
                                                            className="space-y-3 rounded-lg border p-3"
                                                        >
                                                            <div className="flex items-center justify-between gap-2">
                                                                <p className="font-medium">
                                                                    {index + 1}.{' '}
                                                                    {statusName}
                                                                </p>
                                                                {steps.length >
                                                                1 ? (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            setSteps(
                                                                                (
                                                                                    current,
                                                                                ) =>
                                                                                    current.filter(
                                                                                        (
                                                                                            _,
                                                                                            stepIndex,
                                                                                        ) =>
                                                                                            stepIndex !==
                                                                                            index,
                                                                                    ),
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 className="size-4" />
                                                                        Remove
                                                                    </Button>
                                                                ) : null}
                                                            </div>
                                                            <div className="grid gap-3 sm:grid-cols-2">
                                                                <div className="space-y-2">
                                                                    <Label>
                                                                        Default
                                                                        reason
                                                                    </Label>
                                                                    <Select
                                                                        value={
                                                                            step.default_request_sub_status_id
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) =>
                                                                            updateStep(
                                                                                index,
                                                                                {
                                                                                    default_request_sub_status_id:
                                                                                        value,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <SelectTrigger>
                                                                            <SelectValue placeholder="Select reason" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {statusReasons.map(
                                                                                (
                                                                                    reason,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            reason.id
                                                                                        }
                                                                                        value={String(
                                                                                            reason.id,
                                                                                        )}
                                                                                    >
                                                                                        {
                                                                                            reason.name
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                                <div className="space-y-2">
                                                                    <Label>
                                                                        SLA
                                                                        hours
                                                                    </Label>
                                                                    <Input
                                                                        type="number"
                                                                        min={1}
                                                                        value={
                                                                            step.sla_hours
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateStep(
                                                                                index,
                                                                                {
                                                                                    sla_hours:
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                            )
                                                                        }
                                                                        placeholder="None"
                                                                    />
                                                                </div>
                                                            </div>
                                                            <div className="flex flex-wrap gap-4 text-sm">
                                                                <div className="min-w-56 space-y-2">
                                                                    <Label>
                                                                        Assign
                                                                        to
                                                                    </Label>
                                                                    <Select
                                                                        value={
                                                                            step.assigned_to_id
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) =>
                                                                            updateStep(
                                                                                index,
                                                                                {
                                                                                    assigned_to_id:
                                                                                        value,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <SelectTrigger>
                                                                            <SelectValue placeholder="None (team queue)" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            <SelectItem value="none">
                                                                                None
                                                                                —
                                                                                team
                                                                                queue
                                                                            </SelectItem>
                                                                            {staff_options.map(
                                                                                (
                                                                                    staff,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            staff.id
                                                                                        }
                                                                                        value={String(
                                                                                            staff.id,
                                                                                        )}
                                                                                    >
                                                                                        {
                                                                                            staff.name
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                                <label className="flex items-center gap-2">
                                                                    <Input
                                                                        type="checkbox"
                                                                        className="size-4"
                                                                        checked={
                                                                            step.allows_skip_to_deliver
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateStep(
                                                                                index,
                                                                                {
                                                                                    allows_skip_to_deliver:
                                                                                        event
                                                                                            .target
                                                                                            .checked,
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                    Allow skip
                                                                    to delivered
                                                                </label>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Saving...'
                                                    : 'Save workflow'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    ) : null}
                </div>
            </div>
        </>
    );
}
