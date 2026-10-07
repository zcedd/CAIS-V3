import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    approve as approveProgram,
    index as governorProgramsIndex,
    returnMethod as returnProgram,
} from '@/routes/governor/programs';
import type { BreadcrumbItem } from '@/types';

type GovernorProgram = {
    id: number;
    name: string;
    descriptions: string | null;
    approval_status: string | null;
    approval_label: string | null;
    return_comment: string | null;
    department: { id: number; name: string; slug: string } | null;
};

export default function GovernorProgramShow({
    program,
}: {
    program: GovernorProgram;
}) {
    const canDecide = program.approval_status === 'awaiting_governor';

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Approvals',
                    href: governorProgramsIndex(),
                },
                {
                    title: program.name,
                    href: governorProgramsIndex(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [program.name]);

    return (
        <>
            <Head title={program.name} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        <Link
                            href={governorProgramsIndex()}
                            className="hover:underline"
                        >
                            Approvals
                        </Link>
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {program.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {program.department?.name ?? 'No department'} ·{' '}
                        {program.approval_label}
                    </p>
                </div>
                {program.descriptions ? <p>{program.descriptions}</p> : null}
                {program.return_comment ? (
                    <p className="text-sm text-muted-foreground">
                        Returned: {program.return_comment}
                    </p>
                ) : null}
                {canDecide ? (
                    <div className="flex max-w-md flex-col gap-3">
                        <Form {...approveProgram.form.post(program.id)}>
                            <Button type="submit">Approve</Button>
                        </Form>
                        <Form
                            {...returnProgram.form.post(program.id)}
                            className="space-y-2"
                        >
                            <Label htmlFor="return-comment">
                                Return comment
                            </Label>
                            <Input id="return-comment" name="comment" />
                            <Button type="submit" variant="outline">
                                Return
                            </Button>
                        </Form>
                    </div>
                ) : null}
            </div>
        </>
    );
}
