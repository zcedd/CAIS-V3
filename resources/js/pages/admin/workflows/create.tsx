'use client';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { index as adminUsersIndex } from '@/routes/admin/users';
import {
    index as adminWorkflowsIndex,
    store as storeWorkflow,
} from '@/routes/admin/workflows';
import type { BreadcrumbItem } from '@/types';
import type { AdminDepartmentOption } from '@/types/admin-user';
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function AdminWorkflowCreate({
    departments,
}: {
    departments: AdminDepartmentOption[];
}) {
    const [departmentId, setDepartmentId] = useState(
        departments[0] ? String(departments[0].id) : '',
    );
    const [name, setName] = useState('');
    const [code, setCode] = useState('');
    const [description, setDescription] = useState('');
    const [version, setVersion] = useState('1');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Administration',
                    href: adminUsersIndex.url(),
                },
                {
                    title: 'Workflows',
                    href: adminWorkflowsIndex.url(),
                },
                {
                    title: 'Create',
                    href: adminWorkflowsIndex.url(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, []);

    return (
        <>
            <Head title="Create workflow" />

            <Form
                {...storeWorkflow.form.post()}
                disableWhileProcessing
                transform={() => ({
                    department_id: Number(departmentId),
                    name,
                    code,
                    description: description === '' ? null : description,
                    version: Number(version),
                })}
                className="max-w-xl space-y-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="workflow-department">
                                Department
                            </Label>
                            <Select
                                value={departmentId}
                                onValueChange={setDepartmentId}
                            >
                                <SelectTrigger id="workflow-department">
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
                            <Label htmlFor="workflow-name">Name</Label>
                            <Input
                                id="workflow-name"
                                value={name}
                                onChange={(event) => setName(event.target.value)}
                                placeholder="Medical Assistance Workflow"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="workflow-code">Code</Label>
                            <Input
                                id="workflow-code"
                                value={code}
                                onChange={(event) =>
                                    setCode(event.target.value.toUpperCase())
                                }
                                placeholder="MEDICAL_ASSISTANCE"
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="workflow-description">
                                Description
                            </Label>
                            <Textarea
                                id="workflow-description"
                                value={description}
                                onChange={(event) =>
                                    setDescription(event.target.value)
                                }
                                placeholder="Workflow for processing medical assistance requests."
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="workflow-version">Version</Label>
                            <Input
                                id="workflow-version"
                                type="number"
                                min={1}
                                value={version}
                                onChange={(event) =>
                                    setVersion(event.target.value)
                                }
                            />
                            <InputError message={errors.version} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="workflow-status">Status</Label>
                            <Input
                                id="workflow-status"
                                value="Draft"
                                readOnly
                                disabled
                            />
                            <p className="text-sm text-muted-foreground">
                                New workflows start as drafts and must be
                                published before they can be activated.
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                disabled={
                                    processing ||
                                    name.trim() === '' ||
                                    code.trim() === '' ||
                                    departmentId === ''
                                }
                            >
                                {processing ? 'Creating...' : 'Create workflow'}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={adminWorkflowsIndex.url()}>
                                    Cancel
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
