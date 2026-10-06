'use client';

import InputError from '@/components/input-error';
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
import type { AdminDepartmentOption } from '@/types/admin-user';
import { useState } from 'react';

type WorkflowFormFieldsProps = {
    departments: AdminDepartmentOption[];
    errors: Record<string, string>;
    idPrefix: string;
};

export function WorkflowFormFields({
    departments,
    errors,
    idPrefix,
}: WorkflowFormFieldsProps) {
    const [departmentId, setDepartmentId] = useState(
        departments[0] ? String(departments[0].id) : '',
    );
    const [code, setCode] = useState('');

    return (
        <>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-department`}>Department</Label>
                <input type="hidden" name="department_id" value={departmentId} />
                <Select value={departmentId} onValueChange={setDepartmentId}>
                    <SelectTrigger id={`${idPrefix}-department`}>
                        <SelectValue placeholder="Select a department" />
                    </SelectTrigger>
                    <SelectContent>
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
                <InputError message={errors.department_id} />
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-name`}>Name</Label>
                <Input
                    id={`${idPrefix}-name`}
                    name="name"
                    placeholder="Medical Assistance Workflow"
                />
                <InputError message={errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-code`}>Code</Label>
                <Input
                    id={`${idPrefix}-code`}
                    name="code"
                    value={code}
                    onChange={(event) =>
                        setCode(event.target.value.toUpperCase())
                    }
                    placeholder="MEDICAL_ASSISTANCE"
                />
                <InputError message={errors.code} />
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-description`}>Description</Label>
                <Textarea
                    id={`${idPrefix}-description`}
                    name="description"
                    placeholder="Workflow for processing medical assistance requests."
                />
                <InputError message={errors.description} />
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-version`}>Version</Label>
                <Input
                    id={`${idPrefix}-version`}
                    name="version"
                    type="number"
                    min={1}
                    defaultValue="1"
                />
                <InputError message={errors.version} />
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-status`}>Status</Label>
                <Input
                    id={`${idPrefix}-status`}
                    value="Draft"
                    readOnly
                    disabled
                />
                <p className="text-sm text-muted-foreground">
                    New workflows start as drafts and must be published before
                    they can be activated.
                </p>
            </div>
        </>
    );
}
