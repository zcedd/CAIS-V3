import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { portal } from '@/routes';
import { enter as enterOffice } from '@/routes/portal';
import type { BreadcrumbItem } from '@/types';

type OfficeCard = {
    value: string;
    kicker: string;
    title: string;
    office: string;
    summary: string;
    powers: string[];
    pending_count: number | null;
    pending_label: string | null;
    requires_department: boolean;
};

type DepartmentOption = {
    id: number;
    name: string;
};

export default function Portal({
    offices,
    departments,
}: {
    offices: OfficeCard[];
    departments: DepartmentOption[];
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Offices',
                    href: portal(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, []);

    return (
        <>
            <Head title="Offices" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Choose an office
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Enter the office you want to work in. Your access does
                        not change.
                    </p>
                </div>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {offices.map((office) => (
                        <OfficeEntry
                            key={office.value}
                            office={office}
                            departments={departments}
                            errors={errors}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function OfficeEntry({
    office,
    departments,
    errors,
}: {
    office: OfficeCard;
    departments: DepartmentOption[];
    errors: Record<string, string>;
}) {
    const [departmentId, setDepartmentId] = useState(
        departments[0] ? String(departments[0].id) : '',
    );
    const needsDepartment =
        office.requires_department && departments.length > 0;

    return (
        <Card className="h-full">
            <CardHeader>
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {office.kicker}
                </p>
                <CardTitle>{office.title}</CardTitle>
                <CardDescription>{office.office}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <p>{office.summary}</p>
                {office.pending_label !== null &&
                office.pending_count !== null ? (
                    <p className="rounded-md bg-muted px-3 py-2 text-sm font-medium">
                        {office.pending_count} {office.pending_label}
                    </p>
                ) : null}
                <ul className="space-y-1 text-sm text-muted-foreground">
                    {office.powers.map((power) => (
                        <li key={power}>{power}</li>
                    ))}
                </ul>
            </CardContent>
            <CardFooter>
                <Form
                    {...enterOffice.form.post()}
                    className="flex w-full flex-col gap-3"
                >
                    <input type="hidden" name="office" value={office.value} />
                    {needsDepartment ? (
                        <div className="space-y-2">
                            <Label htmlFor={`department-${office.value}`}>
                                Department
                            </Label>
                            <input
                                type="hidden"
                                name="department_id"
                                value={departmentId}
                            />
                            <Select
                                value={departmentId}
                                onValueChange={setDepartmentId}
                            >
                                <SelectTrigger id={`department-${office.value}`}>
                                    <SelectValue placeholder="Department" />
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
                    ) : null}
                    <Button type="submit" className="w-full">
                        Enter as {office.kicker}
                    </Button>
                    <InputError message={errors.office} />
                </Form>
            </CardFooter>
        </Card>
    );
}
