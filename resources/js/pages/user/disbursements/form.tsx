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
import {
    create,
    edit,
    index as disbursementsIndex,
    store,
    update,
} from '@/routes/user/disbursements';

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
    approval_mode: string;
    notes: string | null;
    lock_version: number;
    assistance_ids: number[];
};

export default function DisbursementForm({
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
    const [approvalMode, setApprovalMode] = useState(
        batch?.approval_mode ?? 'batch',
    );
    const [selected, setSelected] = useState<number[]>(
        batch?.assistance_ids ?? [],
    );

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Disbursements',
                    href: disbursementsIndex.url(department.slug),
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
                      disbursementBatch: batch.id,
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
            <Head title={batch ? 'Edit disbursement' : 'New disbursement'} />
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
                        approval_mode: approvalMode,
                        notes: String(form.get('notes') ?? ''),
                        assistance_ids: selected,
                        lock_version: batch?.lock_version,
                    };

                    if (batch) {
                        router.put(
                            update.url({
                                department: department.slug,
                                disbursementBatch: batch.id,
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
                        {batch ? 'Edit disbursement batch' : 'New disbursement batch'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {department.name}
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="program_id">Program</Label>
                    <Select
                        value={programId}
                        onValueChange={loadProgram}
                    >
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

                <div className="grid gap-2">
                    <Label htmlFor="approval_mode">Approval mode</Label>
                    <Select value={approvalMode} onValueChange={setApprovalMode}>
                        <SelectTrigger id="approval_mode" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="batch">Whole batch</SelectItem>
                            <SelectItem value="beneficiary">
                                Individual beneficiary
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">
                        Eligible requests
                    </legend>
                    {requests.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Choose a program to list verified requests that are
                            ready for disbursement.
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
                                <span className="block text-muted-foreground">
                                    {request.lines
                                        .map(
                                            (line) =>
                                                `${line.name ?? 'Item'} × ${line.quantity ?? 0}`,
                                        )
                                        .join(', ')}
                                </span>
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
