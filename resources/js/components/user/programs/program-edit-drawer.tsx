import InputError from '@/components/input-error';
import { ProgramDocumentRequirementsEditor } from '@/components/program-document-requirements-editor';
import { ProgramEligibilityFields, eligibilityPayloadFromForm } from '@/components/program-eligibility-fields';
import { ProgramFieldsEditor } from '@/components/program-fields-editor';
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
import { show as publicApplyShow } from '@/routes/public/apply';
import { update as updateProgram } from '@/routes/user/programs';
import type { DocumentTypeOption, ProgramDocumentRequirementInput } from '@/types/document';
import type { ProgramEligibilityFormValue } from '@/types/eligibility';
import { emptyProgramEligibility } from '@/types/eligibility';
import type { ProgramFieldDefinition } from '@/types/program-field';
import type { WorkflowOption } from '@/pages/user/programs/assistance-toolbar';
import { Form } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
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

type ProgramDetail = {
    id: number;
    name: string;
    descriptions: string | null;
    is_closed: boolean | null;
    is_organization?: boolean | null;
    public_intake?: boolean | null;
    kind?: string | null;
    batch_name?: string | null;
    start_at_input: string | null;
    end_at_input: string | null;
    parent?: { id: number; name: string } | null;
};

type ProgramEligibilityPayload = {
    cooldown_days: number | null;
    require_pwd: boolean;
    require_4ps: boolean;
    require_solo_parent: boolean;
    require_indigenous: boolean;
    item_caps: Array<{ item_id: number; max_released_per_year: number }>;
};

type ProgramEditRelations = {
    fund_ids: number[];
    item_ids: number[];
    fields: ProgramFieldDefinition[];
    document_requirements?: ProgramDocumentRequirementInput[];
    eligibility?: ProgramEligibilityPayload;
    workflow_id?: number | null;
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

function parseProgramDateInput(
    value: string | null | undefined,
): Date | undefined {
    if (!value) {
        return undefined;
    }

    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function eligibilityFormFromPayload(
    payload?: ProgramEligibilityPayload,
): ProgramEligibilityFormValue {
    if (!payload) {
        return emptyProgramEligibility();
    }

    return {
        cooldown_days:
            payload.cooldown_days !== null ? String(payload.cooldown_days) : '',
        require_pwd: payload.require_pwd,
        require_4ps: payload.require_4ps,
        require_solo_parent: payload.require_solo_parent,
        require_indigenous: payload.require_indigenous,
        item_caps: Object.fromEntries(
            payload.item_caps.map((cap) => [
                String(cap.item_id),
                String(cap.max_released_per_year),
            ]),
        ),
    };
}

type ProgramEditDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    program: ProgramDetail;
    department: DepartmentSummary;
    programEdit?: ProgramEditRelations;
    funds?: SelectOption[];
    items?: SelectOption[];
    documentTypes?: DocumentTypeOption[];
    workflowOptions?: WorkflowOption[];
    formKey: number;
    onClose: () => void;
    lockOrganization?: boolean;
};

export function ProgramEditDrawer({
    open,
    onOpenChange,
    program,
    department,
    programEdit,
    funds = [],
    items = [],
    documentTypes = [],
    workflowOptions = [],
    formKey,
    onClose,
    lockOrganization = false,
}: ProgramEditDrawerProps) {
    const [startAtOpen, setStartAtOpen] = useState(false);
    const [startAt, setStartAt] = useState<Date | undefined>(() =>
        parseProgramDateInput(program.start_at_input),
    );
    const [endAtOpen, setEndAtOpen] = useState(false);
    const [endAt, setEndAt] = useState<Date | undefined>(() =>
        parseProgramDateInput(program.end_at_input),
    );
    const [selectedFundIds, setSelectedFundIds] = useState<string[]>(() =>
        (programEdit?.fund_ids ?? []).map(String),
    );
    const [selectedItemIds, setSelectedItemIds] = useState<string[]>(() =>
        (programEdit?.item_ids ?? []).map(String),
    );
    const [fields, setFields] = useState<ProgramFieldDefinition[]>(
        () => programEdit?.fields ?? [],
    );
    const [documentRequirements, setDocumentRequirements] = useState<
        ProgramDocumentRequirementInput[]
    >(() => programEdit?.document_requirements ?? []);
    const [eligibility, setEligibility] = useState<ProgramEligibilityFormValue>(
        () => eligibilityFormFromPayload(programEdit?.eligibility),
    );
    const [workflowId, setWorkflowId] = useState(
        programEdit?.workflow_id ? String(programEdit.workflow_id) : 'default',
    );

    useEffect(() => {
        setStartAt(parseProgramDateInput(program.start_at_input));
        setEndAt(parseProgramDateInput(program.end_at_input));
    }, [program.start_at_input, program.end_at_input]);

    useEffect(() => {
        if (programEdit === undefined) {
            return;
        }

        setSelectedFundIds(programEdit.fund_ids.map(String));
        setSelectedItemIds(programEdit.item_ids.map(String));
        setFields(programEdit.fields ?? []);
        setDocumentRequirements(programEdit.document_requirements ?? []);
        setEligibility(eligibilityFormFromPayload(programEdit.eligibility));
        setWorkflowId(
            programEdit.workflow_id ? String(programEdit.workflow_id) : 'default',
        );
    }, [programEdit]);

    const fundOptions = funds.map((fund) => ({
        value: String(fund.id),
        label: String(`${fund.name} (${fund.year})`),
    }));

    const itemOptions = items.map((item) => ({
        value: String(item.id),
        label: String(`${item.name} (${item.unit})`),
    }));

    const isScheme = program.kind === 'scheme';
    const isBatch = program.kind === 'batch';

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Edit program</DrawerTitle>
                    <DrawerDescription>
                        Update program details for {department.name}.
                    </DrawerDescription>
                </DrawerHeader>
                <Form
                    key={formKey}
                    {...updateProgram.form.put({
                        department: department.slug,
                        program: program.id,
                    })}
                    disableWhileProcessing
                    options={{
                        preserveScroll: true,
                        preserveState: false,
                    }}
                    transform={(data) => ({
                        ...data,
                        start_at: formatDateForSubmit(startAt),
                        end_at: formatDateForSubmit(endAt),
                        is_closed: isScheme
                            ? undefined
                            : data.is_closed === '1' || data.is_closed === true,
                        public_intake: isScheme
                            ? undefined
                            : program.is_organization
                              ? false
                              : data.public_intake === '1' ||
                                data.public_intake === true,
                        fund_ids: isScheme
                            ? []
                            : selectedFundIds.map(Number),
                        item_ids: selectedItemIds.map(Number),
                        fields: fields.map((field, index) => ({
                            ...field,
                            sort_order: index,
                        })),
                        document_requirements: documentRequirements
                            .filter(
                                (requirement) =>
                                    requirement.document_type_id !== null,
                            )
                            .map((requirement, index) => ({
                                ...requirement,
                                sort_order: index,
                            })),
                        ...(isBatch
                            ? {}
                            : eligibilityPayloadFromForm(
                                  eligibility,
                                  selectedItemIds,
                              )),
                        workflow_id:
                            workflowId === 'default' ? null : Number(workflowId),
                    })}
                    onSuccess={() => {
                        onClose();
                        toast.success('Program updated successfully.');
                    }}
                    onError={() => {
                        toast.error(
                            'Could not update the program. Please check the form for errors.',
                        );
                    }}
                    className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                >
                    {({ errors, processing }) => {
                        const isEditDataReady = programEdit !== undefined;

                        return (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="edit-program-name">
                                    {isBatch ? 'Batch name' : 'Name'}
                                </Label>
                                {isBatch ? (
                                    <Input
                                        id="edit-program-name"
                                        name="batch_name"
                                        defaultValue={
                                            program.batch_name ?? program.name
                                        }
                                        placeholder="Batch name"
                                    />
                                ) : (
                                    <Input
                                        id="edit-program-name"
                                        name="name"
                                        defaultValue={program.name}
                                        placeholder="Program name"
                                    />
                                )}
                                <InputError
                                    message={
                                        isBatch
                                            ? errors.batch_name
                                            : errors.name
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="edit-program-descriptions">
                                    Description
                                </Label>
                                <Textarea
                                    id="edit-program-descriptions"
                                    name="descriptions"
                                    defaultValue={program.descriptions ?? ''}
                                    placeholder="Describe the program"
                                    rows={4}
                                />
                                <InputError message={errors.descriptions} />
                            </div>

                            <ProgramDatePicker
                                id="edit-program-start-at"
                                label="Start date"
                                selected={startAt}
                                onSelect={setStartAt}
                                open={startAtOpen}
                                onOpenChange={setStartAtOpen}
                                error={errors.start_at}
                            />

                            <ProgramDatePicker
                                id="edit-program-end-at"
                                label="End date"
                                selected={endAt}
                                onSelect={setEndAt}
                                open={endAtOpen}
                                onOpenChange={setEndAtOpen}
                                error={errors.end_at}
                            />

                            {isScheme ? null : (
                            <div className="space-y-2">
                                <Label htmlFor="edit-program-funds">Funds</Label>
                                <MultiSelect
                                    options={fundOptions}
                                    selected={selectedFundIds}
                                    onChange={setSelectedFundIds}
                                    placeholder="Choose funds..."
                                    className="w-full"
                                />
                                <InputError message={errors.fund_ids} />
                            </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="edit-program-items">Items</Label>
                                <MultiSelect
                                    options={itemOptions}
                                    selected={selectedItemIds}
                                    onChange={setSelectedItemIds}
                                    placeholder="Choose items..."
                                    className="w-full"
                                />
                                <InputError message={errors.item_ids} />
                            </div>

                            <ProgramFieldsEditor
                                fields={fields}
                                onChange={setFields}
                                errors={errors}
                            />

                            <ProgramDocumentRequirementsEditor
                                requirements={documentRequirements}
                                documentTypes={documentTypes}
                                onChange={setDocumentRequirements}
                                errors={errors}
                            />

                            {isScheme && lockOrganization ? (
                                <p className="text-sm text-muted-foreground">
                                    Beneficiary type cannot change after batches
                                    exist.
                                </p>
                            ) : null}

                            {isBatch ? (
                                <p className="text-sm text-muted-foreground">
                                    Eligibility rules are managed on the parent
                                    program
                                    {program.parent ? ` (${program.parent.name})` : ''}.
                                </p>
                            ) : (
                            <ProgramEligibilityFields
                                value={eligibility}
                                onChange={setEligibility}
                                items={items}
                                selectedItemIds={selectedItemIds}
                                isOrganization={program.is_organization ?? false}
                                errors={errors}
                                idPrefix="edit-program-eligibility"
                            />
                            )}

                            {workflowOptions.length > 0 ? (
                                <div className="space-y-2">
                                    <Label htmlFor="edit-program-workflow">
                                        Workflow
                                    </Label>
                                    <Select
                                        value={workflowId}
                                        onValueChange={setWorkflowId}
                                    >
                                        <SelectTrigger id="edit-program-workflow">
                                            <SelectValue placeholder="Department default" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="default">
                                                Use department default
                                            </SelectItem>
                                            {workflowOptions.map((workflow) => (
                                                <SelectItem
                                                    key={workflow.id}
                                                    value={String(workflow.id)}
                                                >
                                                    {workflow.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-sm text-muted-foreground">
                                        Leave as the department default unless this
                                        program needs a different pipeline.
                                    </p>
                                    <InputError message={errors.workflow_id} />
                                </div>
                            ) : null}

                            {isScheme ? null : (
                            <div className="flex items-start gap-3">
                                <Input
                                    id="edit-program-is-closed"
                                    type="checkbox"
                                    name="is_closed"
                                    value="1"
                                    defaultChecked={program.is_closed ?? false}
                                    className="mt-1 size-4 shrink-0 rounded border-input"
                                />
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="edit-program-is-closed"
                                        className="font-normal"
                                    >
                                        Closed program
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Mark when the program is no longer
                                        accepting assistance.
                                    </p>
                                </div>
                            </div>
                            )}

                            {isScheme || program.is_organization ? null : (
                            <div className="space-y-3">
                                <div className="flex items-start gap-3">
                                    <Input
                                        id="edit-program-public-intake"
                                        type="checkbox"
                                        name="public_intake"
                                        value="1"
                                        defaultChecked={
                                            program.public_intake ?? false
                                        }
                                        className="mt-1 size-4 shrink-0 rounded border-input"
                                    />
                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor="edit-program-public-intake"
                                            className="font-normal"
                                        >
                                            Enable public intake
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Let residents apply from a public or
                                            kiosk form. Staff still verify
                                            inside CAIS.
                                        </p>
                                        <InputError
                                            message={errors.public_intake}
                                        />
                                    </div>
                                </div>
                                {program.public_intake && !program.is_closed ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            void navigator.clipboard.writeText(
                                                `${window.location.origin}${publicApplyShow.url(program.id)}`,
                                            );
                                            toast.success(
                                                'Public intake URL copied.',
                                            );
                                        }}
                                    >
                                        <Copy className="size-4" />
                                        Copy public URL
                                    </Button>
                                ) : null}
                            </div>
                            )}

                            <DrawerFooter className="px-0">
                                <Button
                                    type="submit"
                                    disabled={processing || !isEditDataReady}
                                >
                                    {processing
                                        ? 'Saving...'
                                        : isEditDataReady
                                          ? 'Save changes'
                                          : 'Loading program data...'}
                                </Button>
                                <DrawerClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DrawerClose>
                            </DrawerFooter>
                        </>
                        );
                    }}
                </Form>
            </DrawerContent>
        </Drawer>
    );
}
