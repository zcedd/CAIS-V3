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
import { Input } from '@/components/ui/input';
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

function formatDateTimeForSubmit(date: Date | undefined): string | undefined {
    if (!date) {
        return undefined;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');

    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

function formatTimeForInput(date: Date): string {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
}

function formatRecordedAtDisplay(date: Date): string {
    return date.toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function applyTimeToDate(date: Date, timeValue: string): Date {
    const [hours, minutes] = timeValue.split(':').map(Number);
    const next = new Date(date);

    next.setHours(hours ?? 0, minutes ?? 0, 0, 0);

    return next;
}

type AssistanceBulkStatusDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assistanceIds: number[];
    departmentSlug: string;
    programId: number;
    programName: string;
    requestSubStatusOptions: AssistanceRequestSubStatusOption[];
    canAdvance?: boolean;
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
    canAdvance = true,
    onUpdated,
}: AssistanceBulkStatusDrawerProps) {
    const [formKey, setFormKey] = useState(0);
    const [selectedSubStatusId, setSelectedSubStatusId] = useState('');
    const [recordedAt, setRecordedAt] = useState<Date | undefined>(undefined);
    const [recordedAtOpen, setRecordedAtOpen] = useState(false);

    const bulkStatusOptions = useMemo(
        () =>
            requestSubStatusOptions.filter(
                (option) =>
                    option.request_status_code !== 'delivered' &&
                    option.request_status !== 'Delivered',
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
    const selectedSubStatus = bulkStatusOptions.find(
        (option) => String(option.id) === selectedSubStatusId,
    );
    const isVerifiedStatus = selectedSubStatus?.name === 'Verified';

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
                    options={{
                        preserveScroll: true,
                        preserveState: true,
                    }}
                    transform={(data) => ({
                        ...data,
                        assistance_ids: assistanceIds,
                        request_sub_status_id: Number(selectedSubStatusId),
                        recorded_at: formatDateTimeForSubmit(recordedAt),
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

                            {isVerifiedStatus ? (
                                <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    Required documents must be attached on each
                                    assistance profile before bulk Verified can
                                    proceed.
                                </p>
                            ) : null}

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
                                                ? formatRecordedAtDisplay(
                                                      recordedAt,
                                                  )
                                                : 'Select date and time'}
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
                                                Now
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
                                                if (!date) {
                                                    setRecordedAt(undefined);

                                                    return;
                                                }

                                                const current =
                                                    recordedAt ?? new Date();

                                                setRecordedAt(
                                                    applyTimeToDate(
                                                        date,
                                                        formatTimeForInput(
                                                            current,
                                                        ),
                                                    ),
                                                );
                                            }}
                                        />
                                        <div className="border-t p-3">
                                            <Label htmlFor="bulk-assistance-status-recorded-at-time">
                                                Time
                                            </Label>
                                            <Input
                                                id="bulk-assistance-status-recorded-at-time"
                                                type="time"
                                                className="mt-2 bg-background"
                                                value={
                                                    recordedAt
                                                        ? formatTimeForInput(
                                                              recordedAt,
                                                          )
                                                        : ''
                                                }
                                                onChange={(event) => {
                                                    const base =
                                                        recordedAt ??
                                                        new Date();

                                                    setRecordedAt(
                                                        applyTimeToDate(
                                                            base,
                                                            event.target.value,
                                                        ),
                                                    );
                                                }}
                                            />
                                        </div>
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

                            {!canAdvance ? (
                                <p className="text-sm text-muted-foreground">
                                    One or more selected records can only be
                                    updated by their assignee.
                                </p>
                            ) : null}

                            <DrawerFooter className="px-0">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        !canAdvance ||
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
