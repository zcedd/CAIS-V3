'use client';

import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Textarea } from '@/components/ui/textarea';
import {
    activate as activateAdminWorkflow,
    deactivate as deactivateAdminWorkflow,
    destroy as destroyAdminWorkflow,
    publish as publishAdminWorkflow,
    update as updateAdminWorkflow,
    version as versionAdminWorkflow,
} from '@/routes/admin/workflows';
import { Form, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

export type WorkflowDepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

export type WorkflowStatus = {
    id: number;
    name: string;
    code: string | null;
};

export type WorkflowReason = {
    id: number;
    name: string;
    request_status_id: number;
};

export type WorkflowStepPayload = {
    id?: number;
    code?: string | null;
    name?: string | null;
    step_type?: string | null;
    request_status_id: number;
    request_status_name?: string | null;
    request_status_code?: string | null;
    sort_order: number;
    is_start?: boolean;
    is_end?: boolean;
    default_request_sub_status_id: number | null;
    sla_hours: number | null;
    requires_assignee: boolean;
    assignment_type?: string | null;
    assigned_role?: string | null;
    assigned_department_id?: number | null;
    assigned_to_id: number | null;
    automatic_assignment?: boolean;
    allows_skip_to_deliver: boolean;
    transition_status_ids: number[];
};

export type WorkflowPayload = {
    id: number;
    name: string;
    code?: string | null;
    description?: string | null;
    version?: number;
    status?: string;
    status_label?: string;
    is_mutable?: boolean;
    template: string | null;
    is_default: boolean;
    staff_entry_request_status_id: number | null;
    public_entry_request_status_id: number | null;
    programs?: Array<{ id: number; name: string }>;
    steps: WorkflowStepPayload[];
};

export type WorkflowStaffOption = {
    id: number;
    name: string;
};

type DraftStep = {
    id?: number;
    code: string;
    name: string;
    step_type: string;
    request_status_id: string;
    default_request_sub_status_id: string;
    sla_hours: string;
    assignment_type: string;
    assigned_role: string;
    assigned_department_id: string;
    assigned_to_id: string;
    automatic_assignment: boolean;
    allows_skip_to_deliver: boolean;
    is_start: boolean;
    is_end: boolean;
    transition_status_ids: string[];
};

function stepsFromWorkflow(workflow: WorkflowPayload): DraftStep[] {
    return [...workflow.steps]
        .sort((left, right) => left.sort_order - right.sort_order)
        .map((step) => ({
            id: step.id,
            code: step.code ?? '',
            name: step.name ?? step.request_status_name ?? '',
            step_type: step.step_type ?? 'custom',
            request_status_id: String(step.request_status_id),
            default_request_sub_status_id: step.default_request_sub_status_id
                ? String(step.default_request_sub_status_id)
                : '',
            sla_hours: step.sla_hours ? String(step.sla_hours) : '',
            assignment_type: step.assignment_type ?? 'none',
            assigned_role: step.assigned_role ?? '',
            assigned_department_id: step.assigned_department_id
                ? String(step.assigned_department_id)
                : '',
            assigned_to_id: step.assigned_to_id
                ? String(step.assigned_to_id)
                : 'none',
            automatic_assignment: Boolean(step.automatic_assignment),
            allows_skip_to_deliver: step.allows_skip_to_deliver,
            is_start: Boolean(step.is_start),
            is_end: Boolean(step.is_end),
            transition_status_ids: (step.transition_status_ids ?? []).map(
                String,
            ),
        }));
}

export type WorkflowRoleOption = {
    value: string;
    label: string;
};

export type WorkflowAbilities = {
    create?: boolean;
    update?: boolean;
    publish?: boolean;
    manage?: boolean;
    version?: boolean;
    assign?: boolean;
    delete?: boolean;
    duplicate?: boolean;
};

function workflowErrorMessages(errors: Record<string, string>): string[] {
    return Object.entries(errors)
        .filter(([key]) => key === 'workflow' || key.startsWith('workflow.'))
        .map(([, message]) => message)
        .filter((message) => message.trim() !== '');
}

export function WorkflowEditor({
    department,
    departments = [],
    workflows,
    statuses,
    reasons,
    staff_options = [],
    role_options = [],
    variant = 'department',
    can = {},
}: {
    department: WorkflowDepartmentSummary;
    departments?: WorkflowDepartmentSummary[];
    workflows: WorkflowPayload[];
    statuses: WorkflowStatus[];
    reasons: WorkflowReason[];
    staff_options?: WorkflowStaffOption[];
    role_options?: WorkflowRoleOption[];
    variant?: 'department' | 'admin';
    can?: WorkflowAbilities;
}) {
    const canUpdate = can.update ?? true;
    const canPublish = can.publish ?? true;
    const canManage = can.manage ?? true;
    const canVersion = can.version ?? true;
    const canDelete = can.delete ?? false;
    const isAdmin = variant === 'admin';
    const assignmentDepartments =
        departments.length > 0 ? departments : [department];
    const [selectedId, setSelectedId] = useState<number | null>(
        workflows[0]?.id ?? null,
    );

    const selected = workflows.find((workflow) => workflow.id === selectedId);

    const [name, setName] = useState(selected?.name ?? '');
    const [code, setCode] = useState(selected?.code ?? '');
    const [description, setDescription] = useState(selected?.description ?? '');
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
        if (
            selectedId !== null &&
            workflows.some((workflow) => workflow.id === selectedId)
        ) {
            return;
        }

        setSelectedId(workflows[0]?.id ?? null);
    }, [workflows, selectedId]);

    useEffect(() => {
        if (!selected) {
            setName('');
            setCode('');
            setDescription('');
            setIsDefault(false);
            setStaffEntryId('');
            setSteps([]);

            return;
        }

        setName(selected.name);
        setCode(selected.code ?? '');
        setDescription(selected.description ?? '');
        setIsDefault(selected.is_default);
        setStaffEntryId(
            selected.staff_entry_request_status_id
                ? String(selected.staff_entry_request_status_id)
                : '',
        );
        setSteps(stepsFromWorkflow(selected));
    }, [selected]);

    const addableStatuses = statuses;

    const postWorkflowAction = (url: string, success: string) => {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success(success),
            },
        );
    };

    const publishUrl = (workflowId: number) =>
        publishAdminWorkflow.url(workflowId);

    const activateUrl = (workflowId: number) =>
        activateAdminWorkflow.url(workflowId);

    const deactivateUrl = (workflowId: number) =>
        deactivateAdminWorkflow.url(workflowId);

    const versionUrl = (workflowId: number) =>
        versionAdminWorkflow.url(workflowId);

    const updateStep = (index: number, changes: Partial<DraftStep>) => {
        setSteps((current) =>
            current.map((step, stepIndex) =>
                stepIndex === index ? { ...step, ...changes } : step,
            ),
        );
    };

    return (
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
                                    onClick={() => setSelectedId(workflow.id)}
                                    className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm ${
                                        workflow.id === selectedId
                                            ? 'bg-primary/10 font-medium text-foreground'
                                            : 'text-muted-foreground hover:bg-muted'
                                    }`}
                                >
                                    <span>{workflow.name}</span>
                                    <span className="flex items-center gap-1">
                                        {workflow.version ? (
                                            <Badge variant="outline">
                                                v{workflow.version}
                                            </Badge>
                                        ) : null}
                                        {workflow.status_label ? (
                                            <Badge variant="secondary">
                                                {workflow.status_label}
                                            </Badge>
                                        ) : null}
                                        {workflow.is_default ? (
                                            <Badge variant="outline">
                                                Default
                                            </Badge>
                                        ) : null}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>

            {selected ? (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">
                            {selected.is_mutable === false
                                ? selected.name
                                : `Edit ${selected.name}`}
                        </CardTitle>
                        <div className="flex flex-wrap gap-2">
                            {selected.status === 'draft' && canPublish ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() =>
                                        postWorkflowAction(
                                            publishUrl(selected.id),
                                            'Workflow published.',
                                        )
                                    }
                                >
                                    Publish
                                </Button>
                            ) : null}
                            {selected.status === 'published' && canManage ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() =>
                                        postWorkflowAction(
                                            activateUrl(selected.id),
                                            'Workflow activated.',
                                        )
                                    }
                                >
                                    Activate
                                </Button>
                            ) : null}
                            {selected.status === 'active' && canManage ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        postWorkflowAction(
                                            deactivateUrl(selected.id),
                                            'Workflow deactivated.',
                                        )
                                    }
                                >
                                    Deactivate
                                </Button>
                            ) : null}
                            {selected.status === 'inactive' && canManage ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        postWorkflowAction(
                                            activateUrl(selected.id),
                                            'Workflow activated.',
                                        )
                                    }
                                >
                                    Activate
                                </Button>
                            ) : null}
                            {selected.status !== 'draft' && canVersion ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        postWorkflowAction(
                                            versionUrl(selected.id),
                                            'Workflow version created as draft.',
                                        )
                                    }
                                >
                                    New version
                                </Button>
                            ) : null}
                            {isAdmin && canDelete ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    onClick={() =>
                                        router.delete(
                                            destroyAdminWorkflow.url(
                                                selected.id,
                                            ),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    toast.success(
                                                        'Workflow removed.',
                                                    ),
                                            },
                                        )
                                    }
                                >
                                    Delete
                                </Button>
                            ) : null}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...updateAdminWorkflow.form.put(selected.id)}
                            disableWhileProcessing
                            options={{ preserveScroll: true }}
                            transform={() => ({
                                name,
                                code,
                                description:
                                    description === '' ? null : description,
                                is_default: isDefault,
                                staff_entry_request_status_id:
                                    staffEntryId === ''
                                        ? null
                                        : Number(staffEntryId),
                                steps: steps.map((step, index) => ({
                                    id: step.id,
                                    code: step.code,
                                    name: step.name,
                                    step_type: step.step_type,
                                    is_start: step.is_start,
                                    is_end: step.is_end,
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
                                    assignment_type: step.assignment_type,
                                    assigned_role:
                                        step.assigned_role === ''
                                            ? null
                                            : step.assigned_role,
                                    assigned_department_id:
                                        step.assignment_type ===
                                        'department_role'
                                            ? Number(
                                                  step.assigned_department_id ||
                                                      department.id,
                                              )
                                            : step.assigned_department_id === ''
                                              ? null
                                              : Number(
                                                    step.assigned_department_id,
                                                ),
                                    assigned_to_id:
                                        step.assigned_to_id === 'none' ||
                                        step.assigned_to_id === ''
                                            ? null
                                            : Number(step.assigned_to_id),
                                    automatic_assignment:
                                        step.automatic_assignment,
                                    allows_skip_to_deliver:
                                        step.allows_skip_to_deliver,
                                    transition_status_ids:
                                        step.transition_status_ids.map(Number),
                                })),
                            })}
                            onSuccess={() => toast.success('Workflow updated.')}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    {workflowErrorMessages(errors).length >
                                    0 ? (
                                        <Alert variant="destructive">
                                            <AlertTitle>
                                                Cannot publish workflow.
                                            </AlertTitle>
                                            <AlertDescription>
                                                <p className="mb-2">
                                                    {
                                                        workflowErrorMessages(
                                                            errors,
                                                        ).length
                                                    }{' '}
                                                    configuration{' '}
                                                    {workflowErrorMessages(
                                                        errors,
                                                    ).length === 1
                                                        ? 'error'
                                                        : 'errors'}
                                                    :
                                                </p>
                                                <ul className="list-disc space-y-1 pl-4">
                                                    {workflowErrorMessages(
                                                        errors,
                                                    ).map((message) => (
                                                        <li key={message}>
                                                            {message}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}

                                    <div className="space-y-2">
                                        <Label htmlFor="workflow-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="workflow-name"
                                            value={name}
                                            onChange={(event) =>
                                                setName(event.target.value)
                                            }
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="workflow-code">
                                                Code
                                            </Label>
                                            <Input
                                                id="workflow-code"
                                                value={code}
                                                onChange={(event) =>
                                                    setCode(
                                                        event.target.value.toUpperCase(),
                                                    )
                                                }
                                            />
                                            <InputError message={errors.code} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="workflow-version">
                                                Version
                                            </Label>
                                            <Input
                                                id="workflow-version"
                                                value={
                                                    selected.version
                                                        ? `v${selected.version}`
                                                        : 'v1'
                                                }
                                                readOnly
                                                disabled
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="workflow-description">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="workflow-description"
                                            value={description}
                                            onChange={(event) =>
                                                setDescription(
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.description}
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
                                            onValueChange={setStaffEntryId}
                                        >
                                            <SelectTrigger id="workflow-staff-entry">
                                                <SelectValue placeholder="Select stage" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {statuses.map((status) => (
                                                    <SelectItem
                                                        key={status.id}
                                                        value={String(
                                                            status.id,
                                                        )}
                                                    >
                                                        {status.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <Label>Stages</Label>
                                            {addableStatuses.length > 0 ? (
                                                <Select
                                                    onValueChange={(value) => {
                                                        setSteps((current) => [
                                                            ...current,
                                                            {
                                                                request_status_id:
                                                                    value,
                                                                code: '',
                                                                name: '',
                                                                step_type:
                                                                    'custom',
                                                                default_request_sub_status_id:
                                                                    '',
                                                                sla_hours: '',
                                                                assignment_type:
                                                                    'none',
                                                                assigned_role:
                                                                    '',
                                                                assigned_department_id:
                                                                    '',
                                                                assigned_to_id:
                                                                    'none',
                                                                automatic_assignment: false,
                                                                allows_skip_to_deliver: false,
                                                                is_start: false,
                                                                is_end: false,
                                                                transition_status_ids:
                                                                    [],
                                                            },
                                                        ]);
                                                    }}
                                                >
                                                    <SelectTrigger className="h-8 w-48">
                                                        <SelectValue placeholder="Add stage" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {addableStatuses.map(
                                                            (status) => (
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
                                        <InputError message={errors.steps} />

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
                                                        String(status.id) ===
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
                                                        {steps.length > 1 ? (
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
                                                                Step code
                                                            </Label>
                                                            <Input
                                                                value={
                                                                    step.code
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            code: event.target.value.toUpperCase(),
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="VERIFY_BENEFICIARY"
                                                            />
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>
                                                                Step name
                                                            </Label>
                                                            <Input
                                                                value={
                                                                    step.name
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            name: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder={
                                                                    statusName
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="flex flex-wrap gap-4 text-sm">
                                                        <label className="flex items-center gap-2">
                                                            <Input
                                                                type="checkbox"
                                                                className="size-4"
                                                                checked={
                                                                    step.is_start
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            is_start:
                                                                                event
                                                                                    .target
                                                                                    .checked,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            Start step
                                                        </label>
                                                        <label className="flex items-center gap-2">
                                                            <Input
                                                                type="checkbox"
                                                                className="size-4"
                                                                checked={
                                                                    step.is_end
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            is_end: event
                                                                                .target
                                                                                .checked,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            End step
                                                        </label>
                                                    </div>
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label>
                                                                Step code
                                                            </Label>
                                                            <Input
                                                                value={
                                                                    step.code
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            code: event.target.value.toUpperCase(),
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="VERIFY_BENEFICIARY"
                                                            />
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>
                                                                Step name
                                                            </Label>
                                                            <Input
                                                                value={
                                                                    step.name
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            name: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder={
                                                                    statusName
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="flex flex-wrap gap-4 text-sm">
                                                        <label className="flex items-center gap-2">
                                                            <Input
                                                                type="checkbox"
                                                                className="size-4"
                                                                checked={
                                                                    step.is_start
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            is_start:
                                                                                event
                                                                                    .target
                                                                                    .checked,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            Start step
                                                        </label>
                                                        <label className="flex items-center gap-2">
                                                            <Input
                                                                type="checkbox"
                                                                className="size-4"
                                                                checked={
                                                                    step.is_end
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            is_end: event
                                                                                .target
                                                                                .checked,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            End step
                                                        </label>
                                                    </div>
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label>
                                                                Default reason
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
                                                                SLA hours
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
                                                                Assignment type
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    step.assignment_type
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            assignment_type:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="none">
                                                                        Claim
                                                                        pool
                                                                    </SelectItem>
                                                                    <SelectItem value="user">
                                                                        User
                                                                    </SelectItem>
                                                                    <SelectItem value="role">
                                                                        Role
                                                                    </SelectItem>
                                                                    <SelectItem value="department_role">
                                                                        Department
                                                                        + role
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        {step.assignment_type ===
                                                            'role' ||
                                                        step.assignment_type ===
                                                            'department_role' ? (
                                                            <div className="min-w-56 space-y-2">
                                                                <Label>
                                                                    Role
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        step.assigned_role ===
                                                                        ''
                                                                            ? 'none'
                                                                            : step.assigned_role
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateStep(
                                                                            index,
                                                                            {
                                                                                assigned_role:
                                                                                    value ===
                                                                                    'none'
                                                                                        ? ''
                                                                                        : value,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue placeholder="Select role" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="none">
                                                                            Select
                                                                            role
                                                                        </SelectItem>
                                                                        {role_options.map(
                                                                            (
                                                                                role,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        role.value
                                                                                    }
                                                                                    value={
                                                                                        role.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        role.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                        ) : null}
                                                        {step.assignment_type ===
                                                        'department_role' ? (
                                                            <div className="min-w-56 space-y-2">
                                                                <Label>
                                                                    Department
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        step.assigned_department_id ===
                                                                        ''
                                                                            ? String(
                                                                                  department.id,
                                                                              )
                                                                            : step.assigned_department_id
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateStep(
                                                                            index,
                                                                            {
                                                                                assigned_department_id:
                                                                                    value,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue placeholder="Select department" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {assignmentDepartments.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        option.id
                                                                                    }
                                                                                    value={String(
                                                                                        option.id,
                                                                                    )}
                                                                                >
                                                                                    {
                                                                                        option.name
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                        ) : null}
                                                        {step.assignment_type ===
                                                        'user' ? (
                                                            <div className="min-w-56 space-y-2">
                                                                <Label>
                                                                    User
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
                                                                            -
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
                                                        ) : null}
                                                        <label className="flex items-center gap-2">
                                                            <Input
                                                                type="checkbox"
                                                                className="size-4"
                                                                checked={
                                                                    step.automatic_assignment
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStep(
                                                                        index,
                                                                        {
                                                                            automatic_assignment:
                                                                                event
                                                                                    .target
                                                                                    .checked,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            Automatic assignment
                                                        </label>
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
                                                            Allow skip to
                                                            delivered
                                                        </label>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            selected.is_mutable === false ||
                                            !canUpdate
                                        }
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
    );
}
