import { formatProgramPeriod } from '@/lib/format-program-period';
import { show as applyShow } from '@/routes/public/apply';
import { index as trackIndex } from '@/routes/public/track';
import { Head, Link } from '@inertiajs/react';

type CatalogProgram = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    batch_name: string | null;
};

type CatalogDepartment = {
    department_name: string;
    programs: CatalogProgram[];
};

export default function PublicApplyIndex({
    departments,
}: {
    departments: CatalogDepartment[];
}) {
    return (
        <>
            <Head title="Apply for assistance" />
            <div className="space-y-6">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Apply for assistance
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Choose a program to start a public request. Staff will
                        verify and approve it inside CAIS.{' '}
                        <Link
                            href={trackIndex.url()}
                            className="underline underline-offset-4"
                        >
                            Track an existing request
                        </Link>
                    </p>
                </div>

                {departments.length === 0 ? (
                    <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">
                        No programs are accepting public applications right now.
                    </p>
                ) : (
                    <div className="space-y-8">
                        {departments.map((department) => (
                            <section
                                key={department.department_name}
                                className="space-y-3"
                            >
                                <h2 className="text-sm font-semibold tracking-tight">
                                    {department.department_name}
                                </h2>
                                <ul className="grid gap-3">
                                    {department.programs.map((program) => (
                                        <li key={program.id}>
                                            <Link
                                                href={applyShow.url(program.id)}
                                                className="block rounded-xl border border-border bg-card p-4 transition-colors hover:border-foreground/25 hover:bg-muted/30"
                                            >
                                                <p className="font-medium">
                                                    {program.name}
                                                </p>
                                                {program.batch_name ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        {program.batch_name}
                                                    </p>
                                                ) : null}
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {formatProgramPeriod(
                                                        program.start_at,
                                                        program.end_at,
                                                    )}
                                                </p>
                                                {program.descriptions ? (
                                                    <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">
                                                        {program.descriptions}
                                                    </p>
                                                ) : null}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
