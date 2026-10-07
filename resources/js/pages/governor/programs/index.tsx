import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    approve as approveProgram,
    index as governorProgramsIndex,
    returnMethod as returnProgram,
    show as governorProgramShow,
} from '@/routes/governor/programs';
import type { BreadcrumbItem } from '@/types';

type GovernorProgramRow = {
    id: number;
    name: string;
    descriptions: string | null;
    department: { id: number; name: string; slug: string } | null;
};

type PaginatedPrograms = {
    data: GovernorProgramRow[];
};

export default function GovernorProgramsIndex({
    programs,
    pending_count,
}: {
    programs: PaginatedPrograms;
    pending_count: number;
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Approvals',
                    href: governorProgramsIndex(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, []);

    return (
        <>
            <Head title="Gubernatorial approvals" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Programs awaiting approval
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {pending_count} program(s) awaiting gubernatorial
                        approval
                    </p>
                </div>
                {programs.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No programs are waiting for approval.
                    </p>
                ) : (
                    <div className="grid gap-3">
                        {programs.data.map((program) => (
                            <article
                                key={program.id}
                                className="flex flex-col gap-3 rounded-lg border bg-card p-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div className="space-y-1">
                                    <h2 className="font-medium">
                                        <Link
                                            href={governorProgramShow(program.id)}
                                            className="hover:underline"
                                        >
                                            {program.name}
                                        </Link>
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {program.department?.name ??
                                            'No department'}
                                    </p>
                                    {program.descriptions ? (
                                        <p className="text-sm">
                                            {program.descriptions}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex flex-col gap-2 sm:w-72">
                                    <Form {...approveProgram.form.post(program.id)}>
                                        <Button type="submit">Approve</Button>
                                    </Form>
                                    <Form
                                        {...returnProgram.form.post(program.id)}
                                        className="space-y-2"
                                    >
                                        <Label htmlFor={`return-${program.id}`}>
                                            Return comment
                                        </Label>
                                        <Input
                                            id={`return-${program.id}`}
                                            name="comment"
                                            placeholder="Why this program is being returned"
                                        />
                                        <Button type="submit" variant="outline">
                                            Return
                                        </Button>
                                    </Form>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
