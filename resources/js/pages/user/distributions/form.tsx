import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatPeso } from '@/lib/format-peso';
import {
    create,
    edit,
    index as distributionsIndex,
    store,
    update,
} from '@/routes/user/distributions';

type DepartmentSummary = { slug: string; name: string };
type ProgramOption = { id: number; name: string };
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

export default function DistributionForm({
    department,
    batch,
    programs,
    requests,
}: {
    department: DepartmentSummary;
    batch: BatchForm | null;
    programs: ProgramOption[];
    requests: RequestRow[];
}) {
    const [programId, setProgramId] = useState(
        String(batch?.program_id ?? programs[0]?.id ?? ''),
    );
    const [selected, setSelected] = useState<number[]>(
        batch?.assistance_ids ?? [],
    );

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Distributions',
                    href: distributionsIndex.url(department.slug),
                },
                { title: batch ? 'Edit' : 'New', href: '#' },
            ],
        });
    }, [batch, department.slug]);

    function loadProgram(nextProgramId: string): void {
        setProgramId(nextProgramId);
        setSelected([]);
        const url = batch
            ? edit.url(
                  {
                      department: department.slug,
                      distributionBatch: batch.id,
                  },
                  { query: { program_id: nextProgramId } },
              )
            : create.url(department.slug, {
                  query: { program_id: nextProgramId },
              });

        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title={batch ? 'Edit distribution' : 'New distribution'} />
            <form
                className="mx-auto flex max-w-3xl flex-col gap-5 p-4 md:p-6"
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
                        assistance_ids: selected,
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
                <div>
                    <h1 className="text-2xl font-semibold">
                        {batch
                            ? 'Edit distribution batch'
                            : 'New distribution batch'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {department.name}
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="program_id">Program</Label>
                    <Select value={programId} onValueChange={loadProgram}>
                        <SelectTrigger id="program_id" className="w-full">
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
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="title">Title</Label>
                    <Input
                        id="title"
                        name="title"
                        defaultValue={batch?.title ?? ''}
                        required
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="description">Description</Label>
                    <Textarea
                        id="description"
                        name="description"
                        defaultValue={batch?.description ?? ''}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="scheduled_at">Scheduled date</Label>
                        <Input
                            id="scheduled_at"
                            name="scheduled_at"
                            type="datetime-local"
                            defaultValue={batch?.scheduled_at ?? ''}
                            required
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="venue">Venue</Label>
                        <Input
                            id="venue"
                            name="venue"
                            defaultValue={batch?.venue ?? ''}
                            required
                        />
                    </div>
                </div>

                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">
                        Eligible requests
                    </legend>
                    {requests.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Choose a program to list verified requests that are
                            ready for distribution.
                        </p>
                    )}
                    {requests.map((request) => (
                        <label
                            key={request.id}
                            className="flex items-start gap-3 rounded-xl p-3 text-sm ring-1 ring-foreground/10"
                        >
                            <Checkbox
                                className="mt-1"
                                checked={selected.includes(request.id)}
                                onCheckedChange={(checked) => {
                                    setSelected((current) =>
                                        checked === true
                                            ? [...current, request.id]
                                            : current.filter(
                                                  (id) => id !== request.id,
                                              ),
                                    );
                                }}
                            />
                            <span>
                                <span className="font-medium">
                                    {request.beneficiary}
                                </span>
                                <span className="ml-2 text-muted-foreground">
                                    {request.cais_number} · {request.status}
                                </span>
                                <RequestLines lines={request.lines} />
                            </span>
                        </label>
                    ))}
                </fieldset>

                <div className="grid gap-2">
                    <Label htmlFor="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        name="notes"
                        defaultValue={batch?.notes ?? ''}
                    />
                </div>

                <Button type="submit">
                    {batch ? 'Save batch' : 'Create batch'}
                </Button>
            </form>
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
        <span className="mt-1 flex flex-col gap-1 text-muted-foreground">
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
