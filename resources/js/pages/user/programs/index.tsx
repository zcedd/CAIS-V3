import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import InputError from '@/components/input-error';
import { ProgramDocumentRequirementsEditor } from '@/components/program-document-requirements-editor';
import { ProgramEligibilityFields, eligibilityPayloadFromForm } from '@/components/program-eligibility-fields';
import { ProgramFieldsEditor } from '@/components/program-fields-editor';
import { ServerPagination } from '@/components/server-pagination';
import { Button } from '@/components/ui/button';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ProgramDatePicker } from '@/components/user/programs/program-date-picker';
import {
    ProgramFolderCard,
    type ProgramListRow,
} from '@/pages/user/programs/program-folder-card';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
    store as storeProgram,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import type { DocumentTypeOption, ProgramDocumentRequirementInput } from '@/types/document';
import type { ProgramEligibilityFormValue } from '@/types/eligibility';
import { emptyProgramEligibility } from '@/types/eligibility';
import type { ProgramFieldDefinition } from '@/types/program-field';
import type { WorkflowOption } from '@/pages/user/programs/assistance-toolbar';
import {
    applyCreateDrawerOpenChange,
    useCreateDrawerTourLock,
} from '@/lib/tour-create-drawer';
import { cn } from '@/lib/utils';
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

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

type ProgramRow = ProgramListRow & {
    department?: DepartmentSummary | null;
};

type PaginatedPrograms = ServerPaginationMeta & {
    data: ProgramRow[];
};

type ProgramListFilters = {
    search: string;
    type: string[];
    status: string[];
    page?: number;
    per_page?: number;
};

const programTypeOptions = [
    { label: 'Individual', value: 'individual' },
    { label: 'Organization', value: 'organization' },
] as const;

const programStatusOptions = [
    { label: 'Open', value: 'open' },
    { label: 'Closed', value: 'closed' },
] as const;

function buildProgramsQuery(
    filters: ProgramListFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};
    const search = filters.search.trim();

    if (search !== '') {
        query.search = search;
    }

    if (filters.type.length > 0) {
        query.type = filters.type;
    }

    if (filters.status.length > 0) {
        query.status = filters.status;
    }

    if (filters.page !== undefined && filters.page > 1) {
        query.page = String(filters.page);
    }

    if (filters.per_page !== undefined && filters.per_page !== 12) {
        query.per_page = String(filters.per_page);
    }

    return query;
}
function formatDateForSubmit(date: Date | undefined): string | undefined {
    if (!date) {
        return undefined;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export default function UserProgramsIndex({
    programs,
    department,
    search: initialSearch,
    type: initialType,
    status: initialStatus,
    per_page: initialPerPage,
    funds,
    items,
    document_types = [],
    workflow_options = [],
}: {
    programs: PaginatedPrograms;
    department: DepartmentSummary | null;
    search: string;
    type: string[];
    status: string[];
    per_page: number;
    funds?: SelectOption[];
    items?: SelectOption[];
    document_types?: DocumentTypeOption[];
    workflow_options?: WorkflowOption[];
}) {
    const [searchQuery, setSearchQuery] = useState(initialSearch);
    const [createOpen, setCreateOpen] = useState(false);
    const createDrawerTourLocked = useCreateDrawerTourLock();
    const [startAtOpen, setStartAtOpen] = useState(false);
    const [startAt, setStartAt] = useState<Date | undefined>(undefined);
    const [endAtOpen, setEndAtOpen] = useState(false);
    const [endAt, setEndAt] = useState<Date | undefined>(undefined);
    const [selectedFundIds, setSelectedFundIds] = useState<string[]>([]);
    const [selectedItemIds, setSelectedItemIds] = useState<string[]>([]);
    const [fields, setFields] = useState<ProgramFieldDefinition[]>([]);
    const [documentRequirements, setDocumentRequirements] = useState<
        ProgramDocumentRequirementInput[]
    >([]);
    const [isOrganization, setIsOrganization] = useState(false);
    const [publicIntake, setPublicIntake] = useState(false);
    const [programKind, setProgramKind] = useState<'standalone' | 'scheme'>(
        'standalone',
    );
    const [createFirstBatch, setCreateFirstBatch] = useState(false);
    const [firstBatchPublicIntake, setFirstBatchPublicIntake] = useState(false);
    const [firstBatchName, setFirstBatchName] = useState('');
    const [firstBatchStartAt, setFirstBatchStartAt] = useState<
        Date | undefined
    >(undefined);
    const [firstBatchEndAt, setFirstBatchEndAt] = useState<Date | undefined>(
        undefined,
    );
    const [firstBatchStartAtOpen, setFirstBatchStartAtOpen] = useState(false);
    const [firstBatchEndAtOpen, setFirstBatchEndAtOpen] = useState(false);
    const [eligibility, setEligibility] = useState<ProgramEligibilityFormValue>(
        emptyProgramEligibility(),
    );
    const [workflowId, setWorkflowId] = useState('default');

    const fundOptions = (funds ?? []).map((fund) => ({
        value: String(fund.id),
        label: String(`${fund.name} (${fund.year})`),
    }));

    const itemOptions = (items ?? []).map((item) => ({
        value: String(item.id),
        label: String(`${item.name} (${item.unit})`),
    }));

    const resetCreateForm = () => {
        setStartAt(undefined);
        setEndAt(undefined);
        setStartAtOpen(false);
        setEndAtOpen(false);
        setSelectedFundIds([]);
        setSelectedItemIds([]);
        setFields([]);
        setDocumentRequirements([]);
        setIsOrganization(false);
        setPublicIntake(false);
        setProgramKind('standalone');
        setCreateFirstBatch(false);
        setFirstBatchPublicIntake(false);
        setFirstBatchName('');
        setFirstBatchStartAt(undefined);
        setFirstBatchEndAt(undefined);
        setFirstBatchStartAtOpen(false);
        setFirstBatchEndAtOpen(false);
        setEligibility(emptyProgramEligibility());
        setWorkflowId('default');
    };

    useEffect(() => {
        setSearchQuery(initialSearch);
    }, [initialSearch]);

    useEffect(() => {
        if (!createOpen) {
            resetCreateForm();
        }
    }, [createOpen]);

    const navigateWithFilters = useCallback(
        (overrides: Partial<ProgramListFilters> = {}) => {
            if (!department?.slug) {
                return;
            }

            const next: ProgramListFilters = {
                search: overrides.search ?? searchQuery,
                type: overrides.type ?? initialType,
                status: overrides.status ?? initialStatus,
                page: overrides.page ?? 1,
                per_page: overrides.per_page ?? initialPerPage,
            };

            router.get(
                departmentProgramsIndex.url(
                    { department: department.slug },
                    { query: buildProgramsQuery(next) },
                ),
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: [
                        'programs',
                        'search',
                        'type',
                        'status',
                        'per_page',
                        'department',
                    ],
                },
            );
        },
        [
            department?.slug,
            searchQuery,
            initialType,
            initialStatus,
            initialPerPage,
        ],
    );
    if (department?.slug) {
        const programsHref = departmentProgramsIndex.url(department.slug);
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Programs',
                    href: programsHref,
                },
            ] satisfies BreadcrumbItem[],
        });
    }

    useEffect(() => {
        const trimmed = searchQuery.trim();

        if (trimmed === initialSearch.trim() || !department?.slug) {
            return;
        }

        const handle = window.setTimeout(() => {
            navigateWithFilters({ search: trimmed });
        }, 400);

        return () => window.clearTimeout(handle);
    }, [searchQuery, initialSearch, department?.slug, navigateWithFilters]);

    const heading = department ? `${department.name} programs` : 'Programs';
    const canCreate = Boolean(department?.slug);
    const isFiltered =
        initialSearch.trim() !== '' ||
        initialType.length > 0 ||
        initialStatus.length > 0;

    return (
        <>
            <Head title="Department programs" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {department
                                ? 'Programs assigned to your department.'
                                : 'Your account is not linked to a department, so there is nothing to show.'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div
                        className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
                        data-tour="programs-filters"
                    >
                        <Input
                            type="text"
                            name="search"
                            autoComplete="off"
                            placeholder="Search by program name"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className={cn(
                                'max-w-md',
                                searchQuery.trim().length > 0 &&
                                    'border-primary bg-primary/5 ring-1 ring-primary/30',
                            )}
                        />
                        <DataTableFacetedFilter
                            filterValue={initialType}
                            title="Type"
                            options={[...programTypeOptions]}
                            onFilterChange={(values) =>
                                navigateWithFilters({ type: values })
                            }
                        />
                        <DataTableFacetedFilter
                            filterValue={initialStatus}
                            title="Status"
                            options={[...programStatusOptions]}
                            onFilterChange={(values) =>
                                navigateWithFilters({ status: values })
                            }
                        />
                        {isFiltered ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-8 px-2 lg:px-3"
                                onClick={() => {
                                    setSearchQuery('');
                                    navigateWithFilters({
                                        search: '',
                                        type: [],
                                        status: [],
                                    });
                                }}
                            >
                                Reset
                                <X className="ml-2 size-4" />
                            </Button>
                        ) : null}
                    </div>
                    <Button
                        type="button"
                        disabled={!canCreate}
                        onClick={() => setCreateOpen(true)}
                        data-tour="programs-create"
                    >
                        <Plus className="size-4" />
                        Create program
                    </Button>
                </div>
                <div data-tour="programs-list">
                    {programs.data.length === 0 ? (
                        <div className="flex flex-col items-start gap-1 border-t border-foreground/10 py-12">
                            <p className="text-sm font-medium">
                                No programs found
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Nothing matches your filters.
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4">
                            <div className="grid auto-rows-min gap-x-5 gap-y-7 md:grid-cols-2 lg:grid-cols-4">
                                {programs.data.map((program) =>
                                    department?.slug ? (
                                        <Link
                                            key={program.id}
                                            href={departmentProgramShow.url({
                                                department: department.slug,
                                                program: program.id,
                                            })}
                                            prefetch
                                            className="block h-full rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                        >
                                            <ProgramFolderCard
                                                program={program}
                                            />
                                        </Link>
                                    ) : (
                                        <div key={program.id}>
                                            <ProgramFolderCard
                                                program={program}
                                            />
                                        </div>
                                    ),
                                )}
                            </div>

                            <ServerPagination
                                pagination={programs}
                                onPageChange={(page) =>
                                    navigateWithFilters({ page })
                                }
                                onPerPageChange={(perPage) =>
                                    navigateWithFilters({
                                        per_page: perPage,
                                        page: 1,
                                    })
                                }
                            />
                        </div>
                    )}
                </div>
            </div>

            <Drawer
                open={createOpen}
                onOpenChange={(open) =>
                    applyCreateDrawerOpenChange(open, setCreateOpen)
                }
                dismissible={!createDrawerTourLocked}
                noBodyStyles={createDrawerTourLocked}
                direction="right"
            >
                <DrawerContent
                    className="data-[vaul-drawer-direction=right]:sm:max-w-3xl"
                    data-tour="programs-create-form"
                    onPointerDownOutside={(event) => {
                        if (createDrawerTourLocked) {
                            event.preventDefault();
                        }
                    }}
                >
                    <DrawerHeader>
                        <DrawerTitle>Create program</DrawerTitle>
                        <DrawerDescription>
                            Add a new program for {department?.name ?? 'your'}.
                        </DrawerDescription>
                    </DrawerHeader>
                    {canCreate && department && (
                        <Form
                            action={storeProgram.url({
                                department: department.slug,
                            })}
                            method="post"
                            disableWhileProcessing
                            resetOnSuccess
                            transform={(data) => ({
                                ...data,
                                kind: programKind,
                                start_at: formatDateForSubmit(startAt),
                                end_at: formatDateForSubmit(endAt),
                                fund_ids:
                                    programKind === 'scheme'
                                        ? []
                                        : selectedFundIds,
                                item_ids: selectedItemIds,
                                fields: fields.map((field, index) => ({
                                    ...field,
                                    sort_order: index,
                                })),
                                document_requirements: documentRequirements
                                    .filter(
                                        (requirement) =>
                                            requirement.document_type_id !==
                                            null,
                                    )
                                    .map((requirement, index) => ({
                                        ...requirement,
                                        sort_order: index,
                                    })),
                                is_organization: isOrganization,
                                public_intake:
                                    programKind === 'standalone' &&
                                    !isOrganization
                                        ? publicIntake
                                        : false,
                                ...(programKind === 'scheme' &&
                                createFirstBatch
                                    ? {
                                          first_batch: {
                                              batch_name: firstBatchName,
                                              start_at:
                                                  formatDateForSubmit(
                                                      firstBatchStartAt,
                                                  ),
                                              end_at: formatDateForSubmit(
                                                  firstBatchEndAt,
                                              ),
                                              fund_ids: selectedFundIds,
                                              public_intake:
                                                  !isOrganization &&
                                                  firstBatchPublicIntake,
                                          },
                                      }
                                    : {}),
                                ...eligibilityPayloadFromForm(
                                    eligibility,
                                    selectedItemIds,
                                ),
                                workflow_id:
                                    workflowId === 'default'
                                        ? null
                                        : Number(workflowId),
                            })}
                            onSuccess={() => {
                                resetCreateForm();
                                setCreateOpen(false);
                                toast.success('Program created successfully.');
                            }}
                            className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div
                                        className="space-y-2"
                                        data-tour="programs-create-name"
                                    >
                                        <Label htmlFor="program-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="program-name"
                                            name="name"
                                            placeholder="Program name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div
                                        className="space-y-2"
                                        data-tour="programs-create-description"
                                    >
                                        <Label htmlFor="program-descriptions">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="program-descriptions"
                                            name="descriptions"
                                            placeholder="Describe the program"
                                            rows={4}
                                        />
                                        <InputError
                                            message={errors.descriptions}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Program type</Label>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Button
                                                type="button"
                                                variant={
                                                    programKind ===
                                                    'standalone'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    setProgramKind(
                                                        'standalone',
                                                    )
                                                }
                                            >
                                                One-off program
                                            </Button>
                                            <Button
                                                type="button"
                                                variant={
                                                    programKind === 'scheme'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    setProgramKind('scheme')
                                                }
                                            >
                                                Program with batches
                                            </Button>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {programKind === 'scheme'
                                                ? 'Use a parent program for repeating aid. Staff encode into batches, not the parent.'
                                                : 'A single program staff encode into directly.'}
                                        </p>
                                        <InputError message={errors.kind} />
                                    </div>

                                    <div
                                        className="flex flex-col gap-4"
                                        data-tour="programs-create-dates"
                                    >
                                        <ProgramDatePicker
                                            id="program-start-at"
                                            label="Start date"
                                            selected={startAt}
                                            onSelect={setStartAt}
                                            open={startAtOpen}
                                            onOpenChange={setStartAtOpen}
                                            error={errors.start_at}
                                        />

                                        <ProgramDatePicker
                                            id="program-end-at"
                                            label="End date"
                                            selected={endAt}
                                            onSelect={setEndAt}
                                            open={endAtOpen}
                                            onOpenChange={setEndAtOpen}
                                            error={errors.end_at}
                                        />
                                    </div>

                                    {programKind === 'standalone' ? (
                                        <div
                                            className="space-y-2"
                                            data-tour="programs-create-funds"
                                        >
                                            <Label htmlFor="program-funds">
                                                Funds
                                            </Label>
                                            <MultiSelect
                                                options={fundOptions}
                                                selected={selectedFundIds}
                                                onChange={setSelectedFundIds}
                                                placeholder="Choose funds..."
                                                className="w-full"
                                            />
                                            <InputError
                                                message={errors.fund_ids}
                                            />
                                        </div>
                                    ) : (
                                        <div className="space-y-4 rounded-xl border border-border p-4">
                                            <div className="flex items-start gap-3">
                                                <Input
                                                    id="program-create-first-batch"
                                                    type="checkbox"
                                                    checked={createFirstBatch}
                                                    onChange={(event) =>
                                                        setCreateFirstBatch(
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                    className="mt-1 size-4 shrink-0 rounded border-input"
                                                />
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor="program-create-first-batch"
                                                        className="font-normal"
                                                    >
                                                        Create the first batch
                                                        now
                                                    </Label>
                                                    <p className="text-sm text-muted-foreground">
                                                        Copy items, custom
                                                        fields, and document
                                                        requirements into the
                                                        first run.
                                                    </p>
                                                </div>
                                            </div>
                                            {createFirstBatch ? (
                                                <div className="space-y-4">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="program-first-batch-name">
                                                            Batch name
                                                        </Label>
                                                        <Input
                                                            id="program-first-batch-name"
                                                            value={
                                                                firstBatchName
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                setFirstBatchName(
                                                                    event
                                                                        .target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Batch 1"
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    'first_batch.batch_name'
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                    <ProgramDatePicker
                                                        id="program-first-batch-start-at"
                                                        label="Batch start date"
                                                        selected={
                                                            firstBatchStartAt
                                                        }
                                                        onSelect={
                                                            setFirstBatchStartAt
                                                        }
                                                        open={
                                                            firstBatchStartAtOpen
                                                        }
                                                        onOpenChange={
                                                            setFirstBatchStartAtOpen
                                                        }
                                                        error={
                                                            errors[
                                                                'first_batch.start_at'
                                                            ]
                                                        }
                                                    />
                                                    <ProgramDatePicker
                                                        id="program-first-batch-end-at"
                                                        label="Batch end date"
                                                        selected={
                                                            firstBatchEndAt
                                                        }
                                                        onSelect={
                                                            setFirstBatchEndAt
                                                        }
                                                        open={
                                                            firstBatchEndAtOpen
                                                        }
                                                        onOpenChange={
                                                            setFirstBatchEndAtOpen
                                                        }
                                                        error={
                                                            errors[
                                                                'first_batch.end_at'
                                                            ]
                                                        }
                                                    />
                                                    <div className="space-y-2">
                                                        <Label htmlFor="program-first-batch-funds">
                                                            Batch funds
                                                        </Label>
                                                        <MultiSelect
                                                            options={
                                                                fundOptions
                                                            }
                                                            selected={
                                                                selectedFundIds
                                                            }
                                                            onChange={
                                                                setSelectedFundIds
                                                            }
                                                            placeholder="Choose funds..."
                                                            className="w-full"
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    'first_batch.fund_ids'
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                    {!isOrganization ? (
                                                        <div className="flex items-start gap-3">
                                                            <Input
                                                                id="program-first-batch-public-intake"
                                                                type="checkbox"
                                                                checked={
                                                                    firstBatchPublicIntake
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setFirstBatchPublicIntake(
                                                                        event
                                                                            .target
                                                                            .checked,
                                                                    )
                                                                }
                                                                className="mt-1 size-4 shrink-0 rounded border-input"
                                                            />
                                                            <div className="grid gap-1">
                                                                <Label
                                                                    htmlFor="program-first-batch-public-intake"
                                                                    className="font-normal"
                                                                >
                                                                    Enable
                                                                    public
                                                                    intake
                                                                </Label>
                                                                <p className="text-sm text-muted-foreground">
                                                                    Let
                                                                    residents
                                                                    apply to
                                                                    this batch
                                                                    from the
                                                                    public
                                                                    form.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ) : null}
                                                </div>
                                            ) : null}
                                            <InputError
                                                message={errors.first_batch}
                                            />
                                        </div>
                                    )}

                                    <div
                                        className="space-y-2"
                                        data-tour="programs-create-items"
                                    >
                                        <Label htmlFor="program-items">
                                            Items
                                        </Label>
                                        <MultiSelect
                                            options={itemOptions}
                                            selected={selectedItemIds}
                                            onChange={setSelectedItemIds}
                                            placeholder="Choose items..."
                                            className="w-full"
                                        />
                                        <InputError message={errors.item_ids} />
                                    </div>

                                    <div data-tour="programs-create-fields">
                                        <ProgramFieldsEditor
                                            fields={fields}
                                            onChange={setFields}
                                            errors={errors}
                                        />
                                    </div>

                                    <ProgramDocumentRequirementsEditor
                                        requirements={documentRequirements}
                                        documentTypes={document_types}
                                        onChange={setDocumentRequirements}
                                        errors={errors}
                                    />

                                    <ProgramEligibilityFields
                                        value={eligibility}
                                        onChange={setEligibility}
                                        items={items ?? []}
                                        selectedItemIds={selectedItemIds}
                                        isOrganization={isOrganization}
                                        errors={errors}
                                    />

                                    {workflow_options.length > 0 ? (
                                        <div className="space-y-2">
                                            <Label htmlFor="program-workflow">
                                                Workflow
                                            </Label>
                                            <Select
                                                value={workflowId}
                                                onValueChange={setWorkflowId}
                                            >
                                                <SelectTrigger id="program-workflow">
                                                    <SelectValue placeholder="Department default" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="default">
                                                        Use department default
                                                    </SelectItem>
                                                    {workflow_options.map(
                                                        (workflow) => (
                                                            <SelectItem
                                                                key={workflow.id}
                                                                value={String(
                                                                    workflow.id,
                                                                )}
                                                            >
                                                                {workflow.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <p className="text-sm text-muted-foreground">
                                                Leave as the department default
                                                unless this program needs a
                                                different pipeline.
                                            </p>
                                            <InputError
                                                message={errors.workflow_id}
                                            />
                                        </div>
                                    ) : null}

                                    <div
                                        className="flex items-start gap-3"
                                        data-tour="programs-create-organization"
                                    >
                                        <Input
                                            id="program-is-organization"
                                            type="checkbox"
                                            name="is_organization"
                                            value="1"
                                            checked={isOrganization}
                                            onChange={(event) => {
                                                setIsOrganization(
                                                    event.target.checked,
                                                );
                                                if (event.target.checked) {
                                                    setPublicIntake(false);
                                                    setFirstBatchPublicIntake(
                                                        false,
                                                    );
                                                }
                                            }}
                                            className="mt-1 size-4 shrink-0 rounded border-input"
                                        />
                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor="program-is-organization"
                                                className="font-normal"
                                            >
                                                Organization program
                                            </Label>
                                            <p className="text-sm text-muted-foreground">
                                                Enable when assistance is for
                                                organizations rather than
                                                individuals.
                                            </p>
                                        </div>
                                    </div>

                                    {programKind === 'standalone' &&
                                    !isOrganization ? (
                                        <div className="flex items-start gap-3">
                                            <Input
                                                id="program-public-intake"
                                                type="checkbox"
                                                checked={publicIntake}
                                                onChange={(event) =>
                                                    setPublicIntake(
                                                        event.target.checked,
                                                    )
                                                }
                                                className="mt-1 size-4 shrink-0 rounded border-input"
                                            />
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor="program-public-intake"
                                                    className="font-normal"
                                                >
                                                    Enable public intake
                                                </Label>
                                                <p className="text-sm text-muted-foreground">
                                                    Let residents apply from a
                                                    public or kiosk form. Staff
                                                    still verify inside CAIS.
                                                </p>
                                                <InputError
                                                    message={
                                                        errors.public_intake
                                                    }
                                                />
                                            </div>
                                        </div>
                                    ) : null}

                                    <DrawerFooter
                                        className="px-0"
                                        data-tour="programs-create-submit"
                                    >
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Creating...'
                                                : 'Create program'}
                                        </Button>
                                        <DrawerClose asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                            >
                                                Cancel
                                            </Button>
                                        </DrawerClose>
                                    </DrawerFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DrawerContent>
            </Drawer>
        </>
    );
}
