import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    ProgramKpiCards,
    ProgramKpiCardsSkeleton,
} from '@/pages/user/programs/kpi-cards';
import {
    ProgramStatusBreakdown,
    ProgramStatusBreakdownSkeleton,
} from '@/pages/user/programs/status-breakdown';
import { store as storeProgramBatch } from '@/routes/user/programs/batches';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import { formatProgramPeriod } from '@/lib/format-program-period';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { DocumentTypeOption, ProgramDocumentRequirementInput } from '@/types/document';
import type { ProgramStatusBreakdownPoint, ProgramSummary } from '@/types/program';
import type { ProgramFieldDefinition } from '@/types/program-field';
import { Form, Head, Link, router, setLayoutProps, WhenVisible } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    ChevronDownIcon,
    Pencil,
    Plus,
    RotateCcw,
    UserRound,
    Users,
} from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';
import { toast } from 'sonner';

const ProgramEditDrawer = lazy(() =>
    import('@/pages/user/programs/program-edit-drawer').then((module) => ({
        default: module.ProgramEditDrawer,
    })),
);

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type SelectOption = {
    id: number;
    name: string;
    unit: string;
    year: string;
};

type ProgramDetail = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    start_at_input: string | null;
    end_at_input: string | null;
    is_closed: boolean | null;
    is_organization: boolean | null;
    kind?: string | null;
    department_id: number;
};

type ProgramEditRelations = {
    fund_ids: number[];
    item_ids: number[];
    fields: ProgramFieldDefinition[];
    document_requirements?: ProgramDocumentRequirementInput[];
};

type SchemeBatchRow = {
    id: number;
    name: string;
    batch_name: string | null;
    batch_number: number | null;
    start_at: string | null;
    end_at: string | null;
    is_closed: boolean;
    total_requests: number;
};

function formatDateForSubmit(date: Date | undefined): string | undefined {
    if (!date) {
        return undefined;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

type ProgramDatePickerProps = {
    id: string;
    label: string;
    selected: Date | undefined;
    onSelect: (date: Date | undefined) => void;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    error?: string;
};

function ProgramDatePicker({
    id,
    label,
    selected,
    onSelect,
    open,
    onOpenChange,
    error,
}: ProgramDatePickerProps) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Popover open={open} onOpenChange={onOpenChange}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        id={id}
                        className="w-full justify-between font-normal"
                    >
                        {selected ? selected.toLocaleDateString() : 'Select date'}
                        <ChevronDownIcon className="size-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto overflow-hidden p-0" align="start">
                    <div className="flex gap-2 px-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onSelect(new Date())}
                            className="flex items-center gap-2 bg-transparent"
                        >
                            <CalendarDays className="size-4" />
                            Today
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onSelect(undefined)}
                            className="flex items-center gap-2 bg-transparent"
                        >
                            <RotateCcw className="size-4" />
                            Reset
                        </Button>
                    </div>
                    <Calendar
                        mode="single"
                        selected={selected}
                        captionLayout="dropdown"
                        onSelect={(date) => {
                            onSelect(date);
                            onOpenChange(false);
                        }}
                    />
                </PopoverContent>
            </Popover>
            <InputError message={error} />
        </div>
    );
}

export default function UserProgramScheme({
    program,
    summary,
    status_breakdown,
    batches,
    department,
    program_edit,
    funds,
    items,
    document_types = [],
}: {
    program: ProgramDetail;
    summary?: ProgramSummary;
    status_breakdown?: ProgramStatusBreakdownPoint[];
    batches: SchemeBatchRow[];
    department: DepartmentSummary | null;
    program_edit?: ProgramEditRelations;
    funds?: SelectOption[];
    items?: SelectOption[];
    document_types?: DocumentTypeOption[];
}) {
    const [editOpen, setEditOpen] = useState(false);
    const [editFormKey, setEditFormKey] = useState(0);
    const [addBatchOpen, setAddBatchOpen] = useState(false);
    const [batchName, setBatchName] = useState('');
    const [startAt, setStartAt] = useState<Date | undefined>(undefined);
    const [endAt, setEndAt] = useState<Date | undefined>(undefined);
    const [startAtOpen, setStartAtOpen] = useState(false);
    const [endAtOpen, setEndAtOpen] = useState(false);
    const [selectedFundIds, setSelectedFundIds] = useState<string[]>([]);

    useEffect(() => {
        if (!department?.slug) {
            return;
        }

        const programsHref = departmentProgramsIndex.url(department.slug);
        const selfHref = departmentProgramShow.url({
            department: department.slug,
            program: program.id,
        });

        setLayoutProps({
            breadcrumbs: [
                { title: 'Programs', href: programsHref },
                { title: program.name, href: selfHref },
            ] satisfies BreadcrumbItem[],
        });
    }, [department?.slug, program.id, program.name]);

    useEffect(() => {
        if (
            !editOpen ||
            !department?.slug ||
            (funds !== undefined && items !== undefined && program_edit !== undefined)
        ) {
            return;
        }

        router.reload({
            only: ['funds', 'items', 'program_edit'],
        });
    }, [editOpen, funds, items, program_edit, department?.slug]);

    useEffect(() => {
        if (!editOpen) {
            return;
        }

        setEditFormKey((key) => key + 1);
    }, [editOpen]);

    useEffect(() => {
        if (addBatchOpen) {
            return;
        }

        setBatchName('');
        setStartAt(undefined);
        setEndAt(undefined);
        setSelectedFundIds([]);
    }, [addBatchOpen]);

    const heading = program.name;
    const canEdit = Boolean(department?.slug);
    const isClosed = Boolean(program.is_closed);
    const description = program.descriptions?.trim();
    const fundOptions = (funds ?? []).map((fund) => ({
        value: String(fund.id),
        label: String(`${fund.name} (${fund.year})`),
    }));

    return (
        <>
            <Head title={heading} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge
                                variant="outline"
                                className={cn(
                                    isClosed
                                        ? 'border-border text-muted-foreground'
                                        : 'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
                                )}
                            >
                                {isClosed ? 'Closed' : 'Open'}
                            </Badge>
                            <Badge variant="outline">Parent program</Badge>
                            <Badge variant="outline">
                                {program.is_organization ? (
                                    <Users aria-hidden />
                                ) : (
                                    <UserRound aria-hidden />
                                )}
                                {program.is_organization
                                    ? 'Organization'
                                    : 'Individual'}
                            </Badge>
                            {department ? (
                                <Badge variant="outline">
                                    <Building2 aria-hidden />
                                    {department.name}
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit ? (
                            <Button type="button" onClick={() => setEditOpen(true)}>
                                <Pencil className="size-4" />
                                Edit program
                            </Button>
                        ) : null}
                        {canEdit && department ? (
                            <Button type="button" onClick={() => setAddBatchOpen(true)}>
                                <Plus className="size-4" />
                                Add batch
                            </Button>
                        ) : null}
                    </div>
                </div>

                <WhenVisible data="summary" buffer={200} fallback={<ProgramKpiCardsSkeleton />}>
                    {summary ? (
                        <ProgramKpiCards summary={summary} />
                    ) : (
                        <ProgramKpiCardsSkeleton />
                    )}
                </WhenVisible>

                <WhenVisible
                    data="status_breakdown"
                    buffer={200}
                    fallback={<ProgramStatusBreakdownSkeleton />}
                >
                    {status_breakdown ? (
                        <ProgramStatusBreakdown breakdown={status_breakdown} />
                    ) : (
                        <ProgramStatusBreakdownSkeleton />
                    )}
                </WhenVisible>

                <section className="rounded-xl border border-border bg-card">
                    <div className="flex flex-col gap-4 p-4">
                        <div className="space-y-1">
                            <h2 className="text-[15px] font-semibold tracking-tight">
                                Overview
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Encode assistance on a batch, not this parent
                                program.
                            </p>
                        </div>
                        <p
                            className={cn(
                                'text-sm leading-relaxed whitespace-pre-wrap',
                                description
                                    ? 'text-muted-foreground'
                                    : 'text-muted-foreground/60 italic',
                            )}
                        >
                            {description || 'No description'}
                        </p>
                        <p className="text-sm tabular-nums text-muted-foreground">
                            {formatProgramPeriod(program.start_at, program.end_at)}
                        </p>
                    </div>
                </section>

                <section className="rounded-xl border border-border bg-card">
                    <div className="flex flex-col gap-4 p-4">
                        <div className="space-y-1">
                            <h2 className="text-[15px] font-semibold tracking-tight">
                                Batches
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Each batch is an open period staff encode into.
                            </p>
                        </div>
                        {batches.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No batches yet. Add a batch to start encoding
                                assistance.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {batches.map((batch) => (
                                    <li key={batch.id} className="py-3 first:pt-0 last:pb-0">
                                        {department?.slug ? (
                                            <Link
                                                href={departmentProgramShow.url({
                                                    department: department.slug,
                                                    program: batch.id,
                                                })}
                                                prefetch
                                                className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0">
                                                    <p className="font-medium">
                                                        {batch.batch_name ?? batch.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatProgramPeriod(
                                                            batch.start_at,
                                                            batch.end_at,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                                    <span>
                                                        {batch.total_requests.toLocaleString()}{' '}
                                                        requests
                                                    </span>
                                                    <Badge variant="outline">
                                                        {batch.is_closed
                                                            ? 'Closed'
                                                            : 'Open'}
                                                    </Badge>
                                                </div>
                                            </Link>
                                        ) : (
                                            <span>{batch.name}</span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>

            {canEdit && department && editOpen ? (
                <Suspense fallback={null}>
                    <ProgramEditDrawer
                        open={editOpen}
                        onOpenChange={setEditOpen}
                        program={program}
                        department={department}
                        programEdit={program_edit}
                        funds={funds}
                        items={items}
                        documentTypes={document_types}
                        formKey={editFormKey}
                        onClose={() => setEditOpen(false)}
                        lockOrganization={batches.length > 0}
                    />
                </Suspense>
            ) : null}

            {department ? (
                <Drawer
                    open={addBatchOpen}
                    onOpenChange={setAddBatchOpen}
                    direction="right"
                >
                    <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                        <DrawerHeader>
                            <DrawerTitle>Add batch</DrawerTitle>
                            <DrawerDescription>
                                Copy items, custom fields, and document
                                requirements from {program.name}.
                            </DrawerDescription>
                        </DrawerHeader>
                        <Form
                            action={storeProgramBatch.url({
                                department: department.slug,
                                program: program.id,
                            })}
                            method="post"
                            disableWhileProcessing
                            transform={(data) => ({
                                ...data,
                                batch_name: batchName,
                                start_at: formatDateForSubmit(startAt),
                                end_at: formatDateForSubmit(endAt),
                                fund_ids: selectedFundIds,
                            })}
                            onSuccess={() => {
                                setAddBatchOpen(false);
                                toast.success('Batch created successfully.');
                            }}
                            className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="scheme-batch-name">
                                            Batch name
                                        </Label>
                                        <Input
                                            id="scheme-batch-name"
                                            value={batchName}
                                            onChange={(event) =>
                                                setBatchName(event.target.value)
                                            }
                                            placeholder="Batch 1"
                                        />
                                        <InputError message={errors.batch_name} />
                                    </div>
                                    <ProgramDatePicker
                                        id="scheme-batch-start-at"
                                        label="Start date"
                                        selected={startAt}
                                        onSelect={setStartAt}
                                        open={startAtOpen}
                                        onOpenChange={setStartAtOpen}
                                        error={errors.start_at}
                                    />
                                    <ProgramDatePicker
                                        id="scheme-batch-end-at"
                                        label="End date"
                                        selected={endAt}
                                        onSelect={setEndAt}
                                        open={endAtOpen}
                                        onOpenChange={setEndAtOpen}
                                        error={errors.end_at}
                                    />
                                    <div className="space-y-2">
                                        <Label htmlFor="scheme-batch-funds">
                                            Funds
                                        </Label>
                                        <MultiSelect
                                            options={fundOptions}
                                            selected={selectedFundIds}
                                            onChange={setSelectedFundIds}
                                            placeholder="Choose funds..."
                                            className="w-full"
                                        />
                                        <InputError message={errors.fund_ids} />
                                    </div>
                                    <DrawerFooter className="px-0">
                                        <Button type="submit" disabled={processing}>
                                            {processing
                                                ? 'Creating...'
                                                : 'Create batch'}
                                        </Button>
                                        <DrawerClose asChild>
                                            <Button type="button" variant="outline">
                                                Cancel
                                            </Button>
                                        </DrawerClose>
                                    </DrawerFooter>
                                </>
                            )}
                        </Form>
                    </DrawerContent>
                </Drawer>
            ) : null}
        </>
    );
}
