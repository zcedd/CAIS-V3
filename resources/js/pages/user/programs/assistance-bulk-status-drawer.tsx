'use client';

import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { AssistanceRequestSubStatusOption } from '@/pages/user/programs/assistance-toolbar';
import { bulkUpdate as bulkUpdateProgramAssistanceStatus } from '@/routes/user/programs/assistances/status';
import { Form } from '@inertiajs/react';
import { CalendarDays, ChevronDownIcon, RotateCcw } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

function formatDateForSubmit(date: Date | undefined): string | undefined {
    if (!date) {
        return undefined;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

type AssistanceBulkStatusDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assistanceIds: number[];
    departmentSlug: string;
    programId: number;
    programName: string;
    requestSubStatusOptions: AssistanceRequestSubStatusOption[];
    onUpdated?: () => void;
};

export function AssistanceBulkStatusDrawer({
    open,
    onOpenChange,
    assistanceIds,
    departmentSlug,
    programId,
    programName,
    requestSubStatusOptions,
    onUpdated,
}: AssistanceBulkStatusDrawerProps) {
    const [formKey, setFormKey] = useState(0);
    const [selectedSubStatusId, setSelectedSubStatusId] = useState('');
    const [recordedAt, setRecordedAt] = useState<Date | undefined>(undefined);
    const [recordedAtOpen, setRecordedAtOpen] = useState(false);

    const bulkStatusOptions = useMemo(
        () =>
            requestSubStatusOptions.filter(
                (option) => option.request_status !== 'Delivered',
            ),
        [requestSubStatusOptions],
    );

    const resetForm = () => {
        setSelectedSubStatusId('');
        setRecordedAt(undefined);
        setRecordedAtOpen(false);
    };

    const populateForm = () => {
        setSelectedSubStatusId('');
        setRecordedAt(new Date());
        setFormKey((key) => key + 1);
    };

    useEffect(() => {
        if (!open) {
            resetForm();

            return;
        }

        populateForm();
    }, [open, assistanceIds]);

    const selectedCount = assistanceIds.length;

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="w-full data-[vaul-drawer-direction=right]:w-full sm:max-w-full data-[vaul-drawer-direction=right]:sm:max-w-full lg:max-w-3xl data-[vaul-drawer-direction=right]:lg:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Bulk update status</DrawerTitle>
                    <DrawerDescription>
                        Apply the same status to {selectedCount} selected
                        assistance record{selectedCount === 1 ? '' : 's'} in{' '}
                        {programName}. Delivered status must be updated
                        individually.
                    </DrawerDescription>
                </DrawerHeader>

                <Form
                    key={formKey}
                    action={bulkUpdateProgramAssistanceStatus.url({
                        department: departmentSlug,
                        program: programId,
                    })}
                    method="patch"
                    disableWhileProcessing
                    transform={(data) => ({
                        ...data,
                        assistance_ids: assistanceIds,
                        request_sub_status_id: Number(selectedSubStatusId),
                        recorded_at: formatDateForSubmit(recordedAt),
                    })}
                    onSuccess={() => {
                        resetForm();
                        onOpenChange(false);
                        toast.success(
                            `${selectedCount} assistance record${selectedCount === 1 ? '' : 's'} updated successfully.`,
                        );
                        onUpdated?.();
                    }}
                    className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="bulk-assistance-status">
                                    Status
                                </Label>
                                <Select
                                    value={selectedSubStatusId}
                                    onValueChange={setSelectedSubStatusId}
                                >
                                    <SelectTrigger
                                        id="bulk-assistance-status"
                                        className={selectClassName}
                                    >
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {bulkStatusOptions.map((option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={String(option.id)}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.request_sub_status_id}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="bulk-assistance-status-recorded-at">
                                    Recorded at
                                </Label>
                                <Popover
                                    open={recordedAtOpen}
                                    onOpenChange={setRecordedAtOpen}
                                >
                                    <PopoverTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            id="bulk-assistance-status-recorded-at"
                                            className="w-full justify-between font-normal"
                                        >
                                            {recordedAt
                                                ? recordedAt.toLocaleDateString()
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
                                                onClick={() =>
                                                    setRecordedAt(new Date())
                                                }
                                                className="flex items-center gap-2 bg-transparent"
                                            >
                                                <CalendarDays className="size-4" />
                                                Today
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setRecordedAt(undefined)
                                                }
                                                className="flex items-center gap-2 bg-transparent"
                                            >
                                                <RotateCcw className="size-4" />
                                                Reset
                                            </Button>
                                        </div>
                                        <Calendar
                                            mode="single"
                                            selected={recordedAt}
                                            captionLayout="dropdown"
                                            onSelect={(date) => {
                                                setRecordedAt(date);
                                                setRecordedAtOpen(false);
                                            }}
                                        />
                                    </PopoverContent>
                                </Popover>
                                <InputError message={errors.recorded_at} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="bulk-assistance-status-remark">
                                    Remark
                                </Label>
                                <Textarea
                                    id="bulk-assistance-status-remark"
                                    name="remark"
                                    placeholder="Optional notes about this status change"
                                    rows={3}
                                />
                                <InputError message={errors.remark} />
                            </div>

                            <InputError message={errors.assistance_ids} />

                            <DrawerFooter className="px-0">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        !selectedSubStatusId ||
                                        !recordedAt ||
                                        selectedCount === 0
                                    }
                                >
                                    {processing
                                        ? 'Saving...'
                                        : `Update ${selectedCount} record${selectedCount === 1 ? '' : 's'}`}
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
    );
}
