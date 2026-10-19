import {
    Form,
    Head,
    router,
    setLayoutProps,
    WhenVisible,
} from '@inertiajs/react';
import { Building2, Pencil, Plus, Send, UserRound, Users } from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ProgramAttachmentList } from '@/components/program-attachments-field';
import type { ProgramAttachment } from '@/components/program-attachments-field';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { ProgramAnalyticsDialog } from '@/components/user/program-analytics-dialog';
import {
    ProgramCard,
    ProgramCardSkeleton,
} from '@/components/user/program-card';
import type {
    ProgramCardMetric,
    ProgramCardModel,
} from '@/components/user/program-card';
import { ProgramDatePicker } from '@/components/user/programs/program-date-picker';
import { ProgramDecisionDialog } from '@/components/user/programs/program-decision-dialog';
import { useCan } from '@/hooks/use-can';
import { formatProgramPeriod } from '@/lib/format-program-period';
import { Permission } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import type { WorkflowOption } from '@/pages/user/programs/assistance-toolbar';
import {
    ProgramKpiCards,
    ProgramKpiCardsSkeleton,
} from '@/pages/user/programs/kpi-cards';
import {
    ProgramStatusBreakdown,
    ProgramStatusBreakdownSkeleton,
} from '@/pages/user/programs/status-breakdown';
import {
    analytics as programAnalytics,
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import { store as submitProgramApproval } from '@/routes/user/programs/approval';
import { exportMethod as exportAssistances } from '@/routes/user/programs/assistances';
import { store as storeProgramBatch } from '@/routes/user/programs/batches';
import type { BreadcrumbItem } from '@/types';
import type {
    DocumentTypeOption,
    ProgramDocumentRequirementInput,
} from '@/types/document';
import type {
    ProgramStatusBreakdownPoint,
    ProgramSummary,
} from '@/types/program';
import type { ProgramFieldDefinition } from '@/types/program-field';

const ProgramEditDrawer = lazy(() =>
    import('@/components/user/programs/program-edit-drawer').then((module) => ({
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
    approval_label?: string | null;
    can_submit_approval?: boolean;
    department_id: number;
    attachments?: ProgramAttachment[];
};

type ProgramEditRelations = {
    fund_ids: number[];
    item_ids: number[];
    fields: ProgramFieldDefinition[];
    document_requirements?: ProgramDocumentRequirementInput[];
    workflow_id?: number | null;
};

type SchemeBatchRow = ProgramCardModel & {
    batch_number: number | null;
    total_requests: number;
    can_submit_approval?: boolean;
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

export default function UserProgramScheme({
    program,
    summary,
    status_breakdown,
    batches,
    batch_metrics = {},
    department,
    program_edit,
    funds,
    items,
    document_types = [],
    workflow_options = [],
}: {
    program: ProgramDetail;
    summary?: ProgramSummary;
    status_breakdown?: ProgramStatusBreakdownPoint[];
    batches: SchemeBatchRow[];
    batch_metrics?: Record<string, ProgramCardMetric>;
    department: DepartmentSummary | null;
    program_edit?: ProgramEditRelations;
    funds?: SelectOption[];
    items?: SelectOption[];
    document_types?: DocumentTypeOption[];
    workflow_options?: WorkflowOption[];
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
    const [publicIntake, setPublicIntake] = useState(false);
    const [analyticsProgram, setAnalyticsProgram] =
        useState<SchemeBatchRow | null>(null);
    const [endorseProgram, setEndorseProgram] = useState<SchemeBatchRow | null>(
        null,
    );

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
            (funds !== undefined &&
                items !== undefined &&
                program_edit !== undefined)
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
        setPublicIntake(false);
    }, [addBatchOpen]);

    const heading = program.name;
    const { can } = useCan();
    const canEdit = Boolean(department?.slug) && can(Permission.ProgramUpdate);
    const canExportAssistance = can(Permission.ProgramDownloadAssistance);
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
                            {program.approval_label ? (
                                <Badge variant="outline">
                                    {program.approval_label}
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit ? (
                            <Button
                                type="button"
                                onClick={() => setEditOpen(true)}
                            >
                                <Pencil className="size-4" />
                                Edit program
                            </Button>
                        ) : null}
                        {canEdit && department ? (
                            <Button
                                type="button"
                                onClick={() => setAddBatchOpen(true)}
                            >
                                <Plus className="size-4" />
                                Add batch
                            </Button>
                        ) : null}
                    </div>
                </div>

                <WhenVisible
                    data="summary"
                    buffer={200}
                    fallback={<ProgramKpiCardsSkeleton />}
                >
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
                        <ProgramAttachmentList
                            attachments={program.attachments}
                        />
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {formatProgramPeriod(
                                program.start_at,
                                program.end_at,
                            )}
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
                                Submit each batch separately for executive
                                approval.
                            </p>
                        </div>
                        {batches.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No batches yet. Add a batch to start encoding
                                assistance.
                            </p>
                        ) : (
                            <WhenVisible
                                data="batch_metrics"
                                buffer={200}
                                fallback={<BatchCardsSkeleton />}
                            >
                                <div className="grid items-stretch gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {batches.map((batch) => (
                                        <ProgramCard
                                            key={batch.id}
                                            program={batch}
                                            metrics={
                                                batch_metrics[String(batch.id)]
                                            }
                                            href={
                                                department?.slug
                                                    ? departmentProgramShow.url(
                                                          {
                                                              department:
                                                                  department.slug,
                                                              program: batch.id,
                                                          },
                                                      )
                                                    : undefined
                                            }
                                            onInspect={() =>
                                                setAnalyticsProgram(batch)
                                            }
                                            actions={
                                                batch.can_submit_approval &&
                                                department?.slug &&
                                                (batch.approval_status ===
                                                    'draft' ||
                                                    batch.approval_status ===
                                                        'returned') ? (
                                                    <Button
                                                        type="button"
                                                        className="w-full"
                                                        onClick={() =>
                                                            setEndorseProgram(
                                                                batch,
                                                            )
                                                        }
                                                    >
                                                        <Send data-icon="inline-start" />
                                                        Submit for
                                                        recommendation
                                                    </Button>
                                                ) : null
                                            }
                                        />
                                    ))}
                                </div>
                            </WhenVisible>
                        )}
                    </div>
                </section>
            </div>

            <ProgramDecisionDialog
                open={endorseProgram !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEndorseProgram(null);
                    }
                }}
                programName={endorseProgram?.name ?? ''}
                action="submit"
                url={
                    endorseProgram && department?.slug
                        ? submitProgramApproval.url({
                              department: department.slug,
                              program: endorseProgram.id,
                          })
                        : ''
                }
            />
            <ProgramAnalyticsDialog
                program={analyticsProgram}
                open={analyticsProgram !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAnalyticsProgram(null);
                    }
                }}
                url={
                    analyticsProgram && department?.slug
                        ? programAnalytics.url({
                              department: department.slug,
                              program: analyticsProgram.id,
                          })
                        : null
                }
                exportUrl={
                    canExportAssistance && analyticsProgram && department?.slug
                        ? exportAssistances.url({
                              department: department.slug,
                              program: analyticsProgram.id,
                          })
                        : null
                }
            />

            {canEdit && department ? (
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
                        workflowOptions={workflow_options}
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
                                public_intake: program.is_organization
                                    ? false
                                    : publicIntake,
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
                                        <InputError
                                            message={errors.batch_name}
                                        />
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
                                    {program.is_organization ? null : (
                                        <div className="flex items-start gap-3">
                                            <Input
                                                id="scheme-batch-public-intake"
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
                                                    htmlFor="scheme-batch-public-intake"
                                                    className="font-normal"
                                                >
                                                    Enable public intake
                                                </Label>
                                                <p className="text-sm text-muted-foreground">
                                                    Let residents apply to this
                                                    batch from the public form.
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                    <DrawerFooter className="px-0">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Creating...'
                                                : 'Create batch'}
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
                    </DrawerContent>
                </Drawer>
            ) : null}
        </>
    );
}

function BatchCardsSkeleton() {
    return (
        <div className="grid items-stretch gap-4 md:grid-cols-2 xl:grid-cols-3">
            {Array.from({ length: 3 }, (_, index) => (
                <ProgramCardSkeleton key={index} />
            ))}
        </div>
    );
}
