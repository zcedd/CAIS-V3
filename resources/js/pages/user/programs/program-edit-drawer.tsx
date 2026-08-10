import InputError from '@/components/input-error';
import { ProgramFieldsEditor } from '@/components/program-fields-editor';
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
import { Textarea } from '@/components/ui/textarea';
import { update as updateProgram } from '@/routes/user/programs';
import type { ProgramFieldDefinition } from '@/types/program-field';
import { Form } from '@inertiajs/react';
import { CalendarDays, ChevronDownIcon, RotateCcw } from 'lucide-react';
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
    start_at_input: string | null;
    end_at_input: string | null;
};

type ProgramEditRelations = {
    fund_ids: number[];
    item_ids: number[];
    fields: ProgramFieldDefinition[];
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
                        {selected
                            ? selected.toLocaleDateString()
                            : 'Select date'}
                        <ChevronDownIcon className="size-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-auto overflow-hidden p-0"
                    align="start"
                >
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

type ProgramEditDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    program: ProgramDetail;
    department: DepartmentSummary;
    programEdit?: ProgramEditRelations;
    funds?: SelectOption[];
    items?: SelectOption[];
    formKey: number;
    onClose: () => void;
};

export function ProgramEditDrawer({
    open,
    onOpenChange,
    program,
    department,
    programEdit,
    funds = [],
    items = [],
    formKey,
    onClose,
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
    }, [programEdit]);

    const fundOptions = funds.map((fund) => ({
        value: String(fund.id),
        label: String(`${fund.name} (${fund.year})`),
    }));

    const itemOptions = items.map((item) => ({
        value: String(item.id),
        label: String(`${item.name} (${item.unit})`),
    }));

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
                        is_closed: data.is_closed === '1' || data.is_closed === true,
                        fund_ids: selectedFundIds.map(Number),
                        item_ids: selectedItemIds.map(Number),
                        fields: fields.map((field, index) => ({
                            ...field,
                            sort_order: index,
                        })),
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
                                <Label htmlFor="edit-program-name">Name</Label>
                                <Input
                                    id="edit-program-name"
                                    name="name"
                                    defaultValue={program.name}
                                    placeholder="Program name"
                                />
                                <InputError message={errors.name} />
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
