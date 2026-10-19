import { Head, Link } from '@inertiajs/react';
import { formatProgramPeriod } from '@/lib/format-program-period';
import { show as applyShow } from '@/routes/public/apply';
import { index as trackIndex } from '@/routes/public/track';

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
            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Apply for assistance
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Choose a program to apply. Staff will check the request
                        in CAIS.{' '}
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
                    <div className="flex flex-col gap-8">
                        {departments.map((department) => (
                            <section
                                key={department.department_name}
                                className="flex flex-col gap-3"
                            >
                                <h2 className="text-sm font-semibold tracking-tight">
                                    {department.department_name}
                                </h2>
                                <ul className="grid gap-3">
                                    {department.programs.map((program) => (
                                        <li key={program.id}>
                                            <Link
                                                href={applyShow.url(program.id)}
                                                className="flex items-start justify-between gap-4 rounded-xl border border-border bg-card p-4 transition-colors hover:border-foreground/25 hover:bg-muted/30"
                                            >
                                                <span className="flex min-w-0 flex-col gap-1">
                                                    <span className="font-medium">
                                                        {program.name}
                                                    </span>
                                                    <span className="text-sm text-muted-foreground">
                                                        {formatProgramPeriod(
                                                            program.start_at,
                                                            program.end_at,
                                                        )}
                                                    </span>
                                                    {program.batch_name ? (
                                                        <span className="text-xs text-muted-foreground">
                                                            {program.batch_name}
                                                        </span>
                                                    ) : null}
                                                    {program.descriptions ? (
                                                        <span className="line-clamp-2 text-sm text-muted-foreground">
                                                            {
                                                                program.descriptions
                                                            }
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <span className="shrink-0 text-sm font-medium text-primary">
                                                    Apply
                                                </span>
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
