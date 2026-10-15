import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatPeso } from '@/lib/format-peso';
import { cn } from '@/lib/utils';
import {
    create,
    edit,
    index as distributionsIndex,
    store,
    update,
} from '@/routes/user/distributions';
import type { BreadcrumbItem } from '@/types';

type DepartmentSummary = { slug: string; name: string };
type ProgramOption = {
    id: number;
    name: string;
    distribution_mode?: 'individual' | 'batch' | 'both';
};
type RequestRow = {
    id: number;
    beneficiary: string | null;
    cais_number: string | null;
    status: string | null;
    lines: {
        name: string | null;
        quantity: number | null;
        kind: string | null;
    }[];
};
type BatchForm = {
    id: number;
    program_id: number;
    title: string;
    description: string | null;
    scheduled_at: string | null;
    venue: string | null;
    notes: string | null;
    lock_version: number;
    assistance_ids: number[];
};

function fieldError(
    errors: Record<string, string | string[] | undefined>,
    key: string,
): string | undefined {
    const value = errors[key];

    if (Array.isArray(value)) {
        return value[0];
    }

    return value || undefined;
}

export default function DistributionForm({
    department,
    batch,
    programs,
    requests,
    mode = 'batch',
    prefill_assistance_id = null,
    selected_program_id = null,
}: {
    department: DepartmentSummary;
    batch: BatchForm | null;
    programs: ProgramOption[];
    requests: RequestRow[];
    mode?: 'individual' | 'batch';
    prefill_assistance_id?: number | null;
    selected_program_id?: number | null;
}) {
    const individual = mode === 'individual';
    const visibleRequests =
        individual && prefill_assistance_id
            ? requests.filter((request) => request.id === prefill_assistance_id)
            : requests;
    const [programId, setProgramId] = useState(
        String(
            batch?.program_id ?? selected_program_id ?? programs[0]?.id ?? '',
        ),
    );
    const [selected, setSelected] = useState<number[]>(
        batch?.assistance_ids ??
            (prefill_assistance_id ? [prefill_assistance_id] : []),
    );
    const selectedProgram = programs.find(
        (program) => String(program.id) === programId,
    );
    const programAllowsMode =
        programs.length > 0 &&
        (selectedProgram?.distribution_mode == null ||
            selectedProgram.distribution_mode === 'both' ||
            selectedProgram.distribution_mode === mode);
    const pageErrors = (usePage().props.errors ?? {}) as Record<
        string,
        string | string[] | undefined
    >;
    const programError = fieldError(pageErrors, 'program_id');
    const titleError = fieldError(pageErrors, 'title');
    const scheduleError = fieldError(pageErrors, 'scheduled_at');
    const venueError = fieldError(pageErrors, 'venue');
    const rosterError = fieldError(pageErrors, 'assistance_ids');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Distributions',
                    href: distributionsIndex.url(department.slug),
                },
                {
                    title: batch ? 'Edit' : 'New',
                    href: '#',
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [batch, department.slug]);

    function loadProgram(nextProgramId: string): void {
        setProgramId(nextProgramId);
        setSelected(
            individual && prefill_assistance_id ? [prefill_assistance_id] : [],
        );
        const url = batch
            ? edit.url(
                  {
                      department: department.slug,
                      distributionBatch: batch.id,
                  },
                  { query: { program_id: nextProgramId } },
              )
            : create.url(department.slug, {
                  query: {
                      mode,
                      program_id: nextProgramId,
                      ...(prefill_assistance_id
                          ? { assistance_id: prefill_assistance_id }
                          : {}),
                  },
              });

        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    const heading = batch
        ? 'Edit distribution'
        : individual
          ? 'Individual distribution'
          : 'New distribution batch';

    return (
        <>
            <Head title={batch ? 'Edit distribution' : 'New distribution'} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {heading}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {individual
                            ? 'Direct release for an approved request. Creating it does not mark the assistance as received.'
                            : 'Batch distribution. Executive approval is required before a release can be recorded.'}
                    </p>
                </div>

                <form
                    className="mx-auto flex w-full max-w-3xl flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        const payload = {
                            program_id: Number(programId),
                            title: String(form.get('title') ?? ''),
                            description: String(form.get('description') ?? ''),
                            scheduled_at: String(form.get('scheduled_at') ?? ''),
                            venue: String(form.get('venue') ?? ''),
                            notes: String(form.get('notes') ?? ''),
                            approval_mode: individual ? 'beneficiary' : 'batch',
                            assistance_ids:
                                individual && prefill_assistance_id
                                    ? [prefill_assistance_id]
                                    : selected,
                            lock_version: batch?.lock_version,
                        };

                        if (batch) {
                            router.put(
                                update.url({
                                    department: department.slug,
                                    distributionBatch: batch.id,
                                }),
                                payload,
                            );

                            return;
                        }

                        router.post(store.url(department.slug), payload);
                    }}
                >
                    <section className="rounded-xl border border-border bg-card p-4">
                        <FieldGroup>
                            <Field data-invalid={programError ? true : undefined}>
                                <FieldLabel htmlFor="program_id">
                                    Program
                                </FieldLabel>
                                <Select
                                    value={programId}
                                    onValueChange={loadProgram}
                                    disabled={
                                        individual &&
                                        prefill_assistance_id !== null
                                    }
                                >
                                    <SelectTrigger
                                        id="program_id"
                                        className="w-full"
                                        aria-invalid={
                                            programError ? true : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Select a program" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                {programs.length === 0 ? (
                                    <FieldDescription>
                                        No open program in {department.name} is
                                        configured for{' '}
                                        {individual
                                            ? 'individual distribution'
                                            : 'batch distribution'}
                                        .
                                    </FieldDescription>
                                ) : null}
                                {programError ? (
                                    <FieldError>{programError}</FieldError>
                                ) : null}
                            </Field>

                            <Field data-invalid={titleError ? true : undefined}>
                                <FieldLabel htmlFor="title">Title</FieldLabel>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={
                                        batch?.title ??
                                        (individual
                                            ? 'Individual distribution'
                                            : '')
                                    }
                                    aria-invalid={titleError ? true : undefined}
                                    required
                                />
                                {titleError ? (
                                    <FieldError>{titleError}</FieldError>
                                ) : null}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="description">
                                    Description
                                </FieldLabel>
                                <Textarea
                                    id="description"
                                    name="description"
                                    defaultValue={batch?.description ?? ''}
                                />
                            </Field>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    data-invalid={
                                        scheduleError ? true : undefined
                                    }
                                >
                                    <FieldLabel htmlFor="scheduled_at">
                                        Distribution date and time
                                    </FieldLabel>
                                    <Input
                                        id="scheduled_at"
                                        name="scheduled_at"
                                        type="datetime-local"
                                        defaultValue={batch?.scheduled_at ?? ''}
                                        aria-invalid={
                                            scheduleError ? true : undefined
                                        }
                                        required
                                    />
                                    {scheduleError ? (
                                        <FieldError>{scheduleError}</FieldError>
                                    ) : null}
                                </Field>
                                <Field
                                    data-invalid={venueError ? true : undefined}
                                >
                                    <FieldLabel htmlFor="venue">
                                        Venue
                                    </FieldLabel>
                                    <Input
                                        id="venue"
                                        name="venue"
                                        defaultValue={batch?.venue ?? ''}
                                        aria-invalid={
                                            venueError ? true : undefined
                                        }
                                        required
                                    />
                                    {venueError ? (
                                        <FieldError>{venueError}</FieldError>
                                    ) : null}
                                </Field>
                            </div>
                        </FieldGroup>
                    </section>

                    <section className="rounded-xl border border-border bg-card p-4">
                        <FieldSet data-invalid={rosterError ? true : undefined}>
                            <FieldLegend>Eligible requests</FieldLegend>
                            <FieldDescription>
                                {individual
                                    ? prefill_assistance_id
                                        ? 'This approved request is included in the release.'
                                        : 'Select one approved request.'
                                    : 'Verified requests that are ready for this program.'}
                            </FieldDescription>
                            {visibleRequests.length === 0 ? (
                                <p className="text-sm text-muted-foreground italic">
                                    {individual
                                        ? 'This request is not eligible for distribution.'
                                        : 'Choose a program to list requests that are ready for distribution.'}
                                </p>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {visibleRequests.map((request) => {
                                        const checked = selected.includes(
                                            request.id,
                                        );

                                        return (
                                            <label
                                                key={request.id}
                                                className={cn(
                                                    'flex items-start gap-3 rounded-lg border border-border p-3 text-sm',
                                                    checked && 'bg-muted',
                                                )}
                                            >
                                                <Checkbox
                                                    className="mt-0.5"
                                                    checked={checked}
                                                    disabled={
                                                        individual &&
                                                        prefill_assistance_id !==
                                                            null
                                                    }
                                                    onCheckedChange={(
                                                        next,
                                                    ) => {
                                                        if (
                                                            individual &&
                                                            prefill_assistance_id
                                                        ) {
                                                            return;
                                                        }

                                                        if (individual) {
                                                            setSelected(
                                                                next === true
                                                                    ? [
                                                                          request.id,
                                                                      ]
                                                                    : [],
                                                            );

                                                            return;
                                                        }

                                                        setSelected((current) =>
                                                            next === true
                                                                ? [
                                                                      ...current,
                                                                      request.id,
                                                                  ]
                                                                : current.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          request.id,
                                                                  ),
                                                        );
                                                    }}
                                                />
                                                <span className="min-w-0">
                                                    <span className="font-medium">
                                                        {request.beneficiary}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                                        {request.cais_number} ·{' '}
                                                        {request.status}
                                                    </span>
                                                    <RequestLines
                                                        lines={request.lines}
                                                    />
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            )}
                            {rosterError ? (
                                <FieldError>{rosterError}</FieldError>
                            ) : null}
                        </FieldSet>
                    </section>

                    <section className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4">
                        <Field>
                            <FieldLabel htmlFor="notes">Notes</FieldLabel>
                            <Textarea
                                id="notes"
                                name="notes"
                                defaultValue={batch?.notes ?? ''}
                            />
                        </Field>
                        <div>
                            <Button
                                type="submit"
                                disabled={!programAllowsMode}
                            >
                                {batch
                                    ? 'Save distribution'
                                    : individual
                                      ? 'Open individual distribution'
                                      : 'Create batch distribution'}
                            </Button>
                        </div>
                    </section>
                </form>
            </div>
        </>
    );
}

function RequestLines({ lines }: { lines: RequestRow['lines'] }) {
    const groups = (
        [
            { kind: 'goods', label: 'Goods' },
            { kind: 'service', label: 'Services' },
            { kind: 'cash', label: 'Cash' },
        ] as const
    )
        .map((group) => ({
            ...group,
            lines: lines.filter(
                (line) => (line.kind ?? 'goods') === group.kind,
            ),
        }))
        .filter((group) => group.lines.length > 0);

    if (groups.length === 0) {
        return null;
    }

    return (
        <span className="mt-1 flex flex-col gap-1 text-xs text-muted-foreground">
            {groups.map((group) => (
                <span key={group.kind}>
                    {group.label}:{' '}
                    {group.lines
                        .map((line) =>
                            group.kind === 'cash'
                                ? `${line.name ?? 'Cash'} ${formatPeso(line.quantity ?? 0)}`
                                : `${line.name ?? 'Item'} × ${line.quantity ?? 0}`,
                        )
                        .join(', ')}
                </span>
            ))}
        </span>
    );
}
