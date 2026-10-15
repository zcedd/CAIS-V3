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
import { DeliveredDistributionNotice } from '@/components/user/programs/delivered-distribution-notice';
import { slaLabel } from '@/components/user/sla-badge';
import { formatDateTimeForSubmit, parseAppDateTime } from '@/lib/app-datetime';
import { cn } from '@/lib/utils';
import type { UserProgramAssistanceItem } from '@/pages/user/programs/assistance-columns';
import {
    workflowAssignedStatusOptions,
    type AssistanceProgramItemOption,
    type AssistanceRequestSubStatusOption,
} from '@/pages/user/programs/assistance-toolbar';
import { show as assistanceShow } from '@/routes/user/assistances';
import { update as updateProgramAssistanceStatus } from '@/routes/user/programs/assistances/status';
import {
    programRequiresDocumentsBefore,
    type ProgramDocumentRequirementInput,
} from '@/types/document';
import { Form, Link } from '@inertiajs/react';
import { CalendarDays, ChevronDownIcon, RotateCcw } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

type DeliveredItemDetail = {
    quantity: string;
    specification: string;
};

/**
 * A line that was handed over but never applied for: either an extra item, or a stand-in for
 * a requested item that could not be released.
 */
type ExtraItemDraft = {
    key: string;
    origin: 'additional' | 'substitute';
    itemId: string;
    quantity: string;
    specification: string;
    fulfillmentReason: string;
    substitutedForId: string;
};

function substitutedAssistanceItemIds(extraItems: ExtraItemDraft[]): string[] {
    return extraItems
        .filter(
            (draft) =>
                draft.origin === 'substitute' &&
                Boolean(draft.substitutedForId),
        )
        .map((draft) => draft.substitutedForId);
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

function parseRecordedAt(value: string | null): Date | undefined {
    if (!value) {
        return undefined;
    }

    const recorded = parseAppDateTime(value);

    if (Number.isNaN(recorded.getTime())) {
        return undefined;
    }

    return recorded;
}

type AssistanceStatusDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assistanceId: number;
    departmentSlug: string;
    programId: number;
    programName: string;
    beneficiaryName: string;
    currentSubStatusId: number | null;
    currentRecordedAt: string | null;
    requestSubStatusOptions: AssistanceRequestSubStatusOption[];
    assistanceItems: UserProgramAssistanceItem[];
    programItems: AssistanceProgramItemOption[];
    slaState?: string | null;
    canAdvance?: boolean;
    allowedRequestStatusIds?: number[];
    documentRequirements?: ProgramDocumentRequirementInput[];
    distributionMode?: 'individual' | 'batch' | 'both' | null;
    onUpdated?: () => void;
};

export function AssistanceStatusDrawer({
    open,
    onOpenChange,
    assistanceId,
    departmentSlug,
    programId,
    programName,
    beneficiaryName,
    currentSubStatusId,
    currentRecordedAt,
    requestSubStatusOptions,
    assistanceItems,
    slaState = null,
    canAdvance = true,
    allowedRequestStatusIds,
    documentRequirements = [],
    distributionMode = 'batch',
    onUpdated,
}: AssistanceStatusDrawerProps) {
    const [formKey, setFormKey] = useState(0);
    const [selectedSubStatusId, setSelectedSubStatusId] = useState('');
    const [selectedDeliveredItemIds, setSelectedDeliveredItemIds] = useState<
        string[]
    >([]);
    const [deliveredItemDetails, setDeliveredItemDetails] = useState<
        Record<string, DeliveredItemDetail>
    >({});
    const [extraItems, setExtraItems] = useState<ExtraItemDraft[]>([]);
    const [recordedAt, setRecordedAt] = useState<Date | undefined>(undefined);
    const [recordedAtOpen, setRecordedAtOpen] = useState(false);
    const [defaultRemark, setDefaultRemark] = useState('');
    const statusOptions = useMemo(
        () =>
            workflowAssignedStatusOptions(
                requestSubStatusOptions,
                allowedRequestStatusIds,
            ),
        [allowedRequestStatusIds, requestSubStatusOptions],
    );

    const selectedSubStatus = statusOptions.find(
        (option) => String(option.id) === selectedSubStatusId,
    );
    const isDeliveredStatus =
        selectedSubStatus?.request_status_code === 'delivered' ||
        selectedSubStatus?.request_status === 'Delivered';
    const isVerifiedStatus = selectedSubStatus?.name === 'Verified';
    const requiresDocuments = isDeliveredStatus
        ? programRequiresDocumentsBefore(documentRequirements, 'delivered')
        : isVerifiedStatus
          ? programRequiresDocumentsBefore(documentRequirements, 'verified')
          : false;
    const undeliveredItems = useMemo(
        () =>
            assistanceItems.filter(
                (item) =>
                    item.origin === 'requested' &&
                    !item.is_received &&
                    !item.is_substituted,
            ),
        [assistanceItems],
    );
    const substitutedItemIds = useMemo(
        () => substitutedAssistanceItemIds(extraItems),
        [extraItems],
    );
    const wasDeliveredStatus = useRef(false);

    const updateExtraItem = (key: string, changes: Partial<ExtraItemDraft>) => {
        setExtraItems((current) =>
            current.map((draft) =>
                draft.key === key ? { ...draft, ...changes } : draft,
            ),
        );
    };

    const resetForm = () => {
        wasDeliveredStatus.current = false;
        setSelectedSubStatusId('');
        setSelectedDeliveredItemIds([]);
        setDeliveredItemDetails({});
        setExtraItems([]);
        setRecordedAt(undefined);
        setRecordedAtOpen(false);
        setDefaultRemark('');
    };

    const populateForm = () => {
        wasDeliveredStatus.current = false;
        setSelectedSubStatusId(
            currentSubStatusId !== null ? String(currentSubStatusId) : '',
        );
        setRecordedAt(parseRecordedAt(currentRecordedAt) ?? new Date());
        setDefaultRemark('');
        setSelectedDeliveredItemIds([]);
        setDeliveredItemDetails({});
        setExtraItems([]);
        setFormKey((key) => key + 1);
    };

    useEffect(() => {
        if (!open) {
            resetForm();

            return;
        }

        populateForm();
    }, [open, currentSubStatusId, currentRecordedAt]);

    useEffect(() => {
        if (!isDeliveredStatus) {
            if (wasDeliveredStatus.current) {
                setSelectedDeliveredItemIds([]);
                setDeliveredItemDetails({});
                setExtraItems([]);
            }

            wasDeliveredStatus.current = false;

            return;
        }

        const switchedToDelivered = !wasDeliveredStatus.current;
        wasDeliveredStatus.current = true;

        if (!switchedToDelivered || undeliveredItems.length !== 1) {
            return;
        }

        const item = undeliveredItems[0];

        setSelectedDeliveredItemIds([String(item.id)]);
        setDeliveredItemDetails({
            [String(item.id)]: {
                quantity: String(item.quantity ?? 1),
                specification: item.specification ?? '',
            },
        });
    }, [isDeliveredStatus, undeliveredItems]);

    useEffect(() => {
        if (substitutedItemIds.length === 0) {
            return;
        }

        setSelectedDeliveredItemIds((current) => {
            const next = current.filter(
                (itemId) => !substitutedItemIds.includes(itemId),
            );

            return next.length === current.length ? current : next;
        });
    }, [substitutedItemIds]);

    useEffect(() => {
        setDeliveredItemDetails((current) => {
            const next: Record<string, DeliveredItemDetail> = {};
            const selectedIds = new Set(selectedDeliveredItemIds);
            let changed =
                Object.keys(current).length !== selectedDeliveredItemIds.length;

            selectedDeliveredItemIds.forEach((itemId) => {
                const assistanceItem = undeliveredItems.find(
                    (item) => String(item.id) === itemId,
                );

                next[itemId] = current[itemId] ?? {
                    quantity: String(assistanceItem?.quantity ?? 1),
                    specification: assistanceItem?.specification ?? '',
                };

                if (current[itemId] === undefined) {
                    changed = true;
                }
            });

            Object.keys(current).forEach((itemId) => {
                if (!selectedIds.has(itemId)) {
                    changed = true;
                }
            });

            return changed ? next : current;
        });
    }, [selectedDeliveredItemIds, undeliveredItems]);

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="w-full data-[vaul-drawer-direction=right]:w-full sm:max-w-full data-[vaul-drawer-direction=right]:sm:max-w-full lg:max-w-3xl data-[vaul-drawer-direction=right]:lg:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Update status</DrawerTitle>
                    <DrawerDescription>
                        Record a new status for {beneficiaryName} in{' '}
                        {programName}.
                    </DrawerDescription>
                </DrawerHeader>

                <Form
                    key={formKey}
                    action={updateProgramAssistanceStatus.url({
                        department: departmentSlug,
                        program: programId,
                        assistance: assistanceId,
                    })}
                    method="patch"
                    disableWhileProcessing
                    options={{
                        preserveScroll: true,
                        preserveState: true,
                    }}
                    transform={(data) => ({
                        ...data,
                        request_sub_status_id: Number(selectedSubStatusId),
                        recorded_at: formatDateTimeForSubmit(recordedAt),
                        delivered_items: isDeliveredStatus
                            ? selectedDeliveredItemIds
                                  .filter(
                                      (itemId) =>
                                          !substitutedItemIds.includes(itemId),
                                  )
                                  .map((itemId) => ({
                                      assistance_item_id: Number(itemId),
                                      quantity: Number(
                                          deliveredItemDetails[itemId]
                                              ?.quantity ?? 1,
                                      ),
                                      specification:
                                          deliveredItemDetails[itemId]
                                              ?.specification ?? '',
                                  }))
                            : undefined,
                        extra_items: isDeliveredStatus
                            ? extraItems.map((draft) => ({
                                  origin: draft.origin,
                                  item_id: Number(draft.itemId),
                                  quantity: Number(draft.quantity || 1),
                                  specification: draft.specification,
                                  fulfillment_reason:
                                      draft.fulfillmentReason.trim(),
                                  substituted_for_assistance_item_id:
                                      draft.origin === 'substitute' &&
                                      draft.substitutedForId
                                          ? Number(draft.substitutedForId)
                                          : null,
                              }))
                            : undefined,
                    })}
                    onSuccess={() => {
                        resetForm();
                        onOpenChange(false);
                        toast.success(
                            'Assistance status updated successfully.',
                        );
                        onUpdated?.();
                    }}
                    onError={() => {
                        toast.error(
                            'Could not update the status. Check the highlighted fields.',
                        );
                    }}
                    className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="assistance-status">
                                    Status
                                </Label>
                                <Select
                                    value={selectedSubStatusId}
                                    onValueChange={setSelectedSubStatusId}
                                >
                                    <SelectTrigger
                                        id="assistance-status"
                                        className={selectClassName}
                                    >
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statusOptions.map((option) => (
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

                            {slaState && slaState !== 'none' ? (
                                <p className="text-sm text-muted-foreground">
                                    Current SLA: {slaLabel(slaState)}
                                </p>
                            ) : null}

                            {!canAdvance ? (
                                <p className="text-sm text-muted-foreground">
                                    Only the assignee can update the status.
                                </p>
                            ) : statusOptions.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    This stage has no statuses assigned on the
                                    workflow.
                                </p>
                            ) : null}

                            {requiresDocuments ? (
                                <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    Required documents must be attached on the{' '}
                                    <Link
                                        href={assistanceShow.url({
                                            department: departmentSlug,
                                            program: programId,
                                            assistance: assistanceId,
                                        })}
                                        className="font-medium underline underline-offset-2"
                                    >
                                        assistance profile
                                    </Link>{' '}
                                    before this request can be marked as{' '}
                                    {isDeliveredStatus
                                        ? 'Delivered'
                                        : 'Verified'}
                                    .
                                </p>
                            ) : null}

                            {isDeliveredStatus ? (
                                <DeliveredDistributionNotice
                                    departmentSlug={departmentSlug}
                                    programId={programId}
                                    assistanceId={assistanceId}
                                    distributionMode={distributionMode}
                                />
                            ) : null}

                            <div className="space-y-2">
                                <Label htmlFor="assistance-status-recorded-at">
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
                                            id="assistance-status-recorded-at"
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
                                            <Label htmlFor="assistance-status-recorded-at-time">
                                                Time
                                            </Label>
                                            <Input
                                                id="assistance-status-recorded-at-time"
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
                                <Label htmlFor="assistance-status-remark">
                                    Remark
                                </Label>
                                <Textarea
                                    id="assistance-status-remark"
                                    name="remark"
                                    defaultValue={defaultRemark}
                                    placeholder="Optional notes about this status change"
                                    rows={3}
                                />
                                <InputError message={errors.remark} />
                            </div>

                            <DrawerFooter className="px-0">
                                {isDeliveredStatus ? null : (
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            !canAdvance ||
                                            !selectedSubStatusId ||
                                            !recordedAt
                                        }
                                    >
                                        {processing
                                            ? 'Saving...'
                                            : 'Update status'}
                                    </Button>
                                )}
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
