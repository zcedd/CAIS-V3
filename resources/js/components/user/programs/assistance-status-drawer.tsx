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
import { MultiSelect } from '@/components/ui/multi-select';
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
import { slaLabel } from '@/components/user/sla-badge';
import { cn } from '@/lib/utils';
import type { UserProgramAssistanceItem } from '@/pages/user/programs/assistance-columns';
import type {
    AssistanceProgramItemOption,
    AssistanceRequestSubStatusOption,
    DepartmentStaffOption,
} from '@/pages/user/programs/assistance-toolbar';
import { show as assistanceShow } from '@/routes/user/assistances';
import { update as updateProgramAssistanceStatus } from '@/routes/user/programs/assistances/status';
import { formatItemQuantity } from '@/types/assistance-item';
import { itemQuantityFieldLabel, tracksInventory } from '@/types/item';
import { Form, Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDownIcon,
    Plus,
    RotateCcw,
    X,
} from 'lucide-react';
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

function createExtraItemDraft(): ExtraItemDraft {
    return {
        key:
            typeof crypto !== 'undefined' && 'randomUUID' in crypto
                ? crypto.randomUUID()
                : String(Date.now() + Math.random()),
        origin: 'additional',
        itemId: '',
        quantity: '1',
        specification: '',
        fulfillmentReason: '',
        substitutedForId: '',
    };
}

function isExtraItemDraftComplete(draft: ExtraItemDraft): boolean {
    if (!draft.itemId || !draft.quantity || !draft.fulfillmentReason.trim()) {
        return false;
    }

    return draft.origin !== 'substitute' || Boolean(draft.substitutedForId);
}

function remainingForProgramItem(
    programItems: AssistanceProgramItemOption[],
    itemId: number,
): number | null {
    const remaining = programItems.find(
        (programItem) => programItem.id === itemId,
    )?.remaining;

    return remaining === undefined ? 0 : remaining;
}

function programItemKind(
    programItems: AssistanceProgramItemOption[],
    itemId: number,
) {
    return programItems.find((programItem) => programItem.id === itemId)?.kind;
}

function substitutedAssistanceItemIds(extraItems: ExtraItemDraft[]): string[] {
    return extraItems
        .filter(
            (draft) =>
                draft.origin === 'substitute' &&
                Boolean(draft.substitutedForId),
        )
        .map((draft) => draft.substitutedForId);
}

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

function parseRecordedAt(value: string | null): Date | undefined {
    if (!value) {
        return undefined;
    }

    const recorded = new Date(value);

    if (Number.isNaN(recorded.getTime())) {
        return undefined;
    }

    return recorded;
}

function formatAssistanceItemLabel(item: UserProgramAssistanceItem): string {
    const amount = formatItemQuantity(item.quantity, item.unit, item.kind);

    return `${item.name} ${amount}`.trim();
}

function formatProgramItemLabel(item: AssistanceProgramItemOption): string {
    if (item.kind === 'cash') {
        return item.name;
    }

    return item.unit ? `${item.name} (${item.unit})` : item.name;
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
    staffOptions?: DepartmentStaffOption[];
    assignedToId?: number | null;
    slaState?: string | null;
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
    programItems,
    staffOptions = [],
    assignedToId = null,
    slaState = null,
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
    const [selectedAssigneeId, setSelectedAssigneeId] = useState(
        assignedToId !== null ? String(assignedToId) : 'unassigned',
    );

    const selectedSubStatus = requestSubStatusOptions.find(
        (option) => String(option.id) === selectedSubStatusId,
    );
    const isDeliveredStatus =
        selectedSubStatus?.request_status_code === 'delivered' ||
        selectedSubStatus?.request_status === 'Delivered';
    const isVerifiedStatus = selectedSubStatus?.name === 'Verified';
    const requiresDocuments = isVerifiedStatus || isDeliveredStatus;
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
    const undeliveredItemOptions = useMemo(
        () =>
            undeliveredItems.map((item) => ({
                value: String(item.id),
                label: formatAssistanceItemLabel(item),
            })),
        [undeliveredItems],
    );
    const hasIncompleteExtraItem = extraItems.some(
        (draft) => !isExtraItemDraftComplete(draft),
    );
    const exceedsProgramStock = useMemo(() => {
        const needed: Record<number, number> = {};

        for (const selectedId of selectedDeliveredItemIds) {
            const assistanceItem = undeliveredItems.find(
                (item) => String(item.id) === selectedId,
            );

            if (!assistanceItem) {
                continue;
            }

            const quantity = Number.parseInt(
                deliveredItemDetails[selectedId]?.quantity ?? '0',
                10,
            );

            if (!Number.isFinite(quantity) || quantity < 1) {
                continue;
            }

            needed[assistanceItem.item_id] =
                (needed[assistanceItem.item_id] ?? 0) + quantity;
        }

        for (const draft of extraItems) {
            const itemId = Number.parseInt(draft.itemId, 10);
            const quantity = Number.parseInt(draft.quantity, 10);

            if (!Number.isFinite(itemId) || !Number.isFinite(quantity) || quantity < 1) {
                continue;
            }

            needed[itemId] = (needed[itemId] ?? 0) + quantity;
        }

        return Object.entries(needed).some(([itemId, quantity]) => {
            const remaining = remainingForProgramItem(
                programItems,
                Number(itemId),
            );

            return remaining !== null && quantity > remaining;
        });
    }, [
        selectedDeliveredItemIds,
        undeliveredItems,
        deliveredItemDetails,
        extraItems,
        programItems,
    ]);
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
        setSelectedAssigneeId('unassigned');
    };

    const populateForm = () => {
        wasDeliveredStatus.current = false;
        setSelectedSubStatusId(
            currentSubStatusId !== null ? String(currentSubStatusId) : '',
        );
        setSelectedAssigneeId(
            assignedToId !== null ? String(assignedToId) : 'unassigned',
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
    }, [open, currentSubStatusId, currentRecordedAt, assignedToId]);

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
                        assigned_to_id:
                            selectedAssigneeId === 'unassigned'
                                ? null
                                : Number(selectedAssigneeId),
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
                                        {requestSubStatusOptions.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={String(option.id)}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
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

                            {staffOptions.length > 0 ? (
                                <div className="space-y-2">
                                    <Label htmlFor="assistance-assignee">
                                        Assignee
                                    </Label>
                                    <Select
                                        value={selectedAssigneeId}
                                        onValueChange={setSelectedAssigneeId}
                                    >
                                        <SelectTrigger
                                            id="assistance-assignee"
                                            className={selectClassName}
                                        >
                                            <SelectValue placeholder="Unassigned" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="unassigned">
                                                Unassigned
                                            </SelectItem>
                                            {staffOptions.map((staff) => (
                                                <SelectItem
                                                    key={staff.id}
                                                    value={String(staff.id)}
                                                >
                                                    {staff.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.assigned_to_id}
                                    />
                                </div>
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
                                <>
                                    <div className="space-y-3">
                                        <div className="space-y-2">
                                            <Label htmlFor="assistance-status-delivered-items">
                                                Requested items released
                                            </Label>
                                            {undeliveredItems.length > 0 ? (
                                                <MultiSelect
                                                    options={
                                                        undeliveredItemOptions
                                                    }
                                                    selected={
                                                        selectedDeliveredItemIds
                                                    }
                                                    onChange={
                                                        setSelectedDeliveredItemIds
                                                    }
                                                    placeholder="Choose requested items handed over..."
                                                    className="w-full"
                                                />
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    Nothing is still owed on
                                                    this request. Anything else
                                                    handed over goes below as an
                                                    additional item.
                                                </p>
                                            )}
                                            {substitutedItemIds.length > 0 ? (
                                                <p className="text-xs text-muted-foreground">
                                                    Requested lines chosen as a
                                                    substitute below are
                                                    released as a replacement,
                                                    not as the original item.
                                                </p>
                                            ) : null}
                                            <InputError
                                                message={errors.delivered_items}
                                            />
                                        </div>

                                        {selectedDeliveredItemIds.length > 0 ? (
                                            <div className="space-y-3">
                                                <Label>
                                                    Released item details
                                                </Label>
                                                {selectedDeliveredItemIds.map(
                                                    (selectedItemId, index) => {
                                                        const item =
                                                            undeliveredItems.find(
                                                                ({ id }) =>
                                                                    String(
                                                                        id,
                                                                    ) ===
                                                                    selectedItemId,
                                                            );

                                                        if (!item) {
                                                            return null;
                                                        }

                                                        const detail =
                                                            deliveredItemDetails[
                                                                selectedItemId
                                                            ] ?? {
                                                                quantity:
                                                                    String(
                                                                        item.quantity ??
                                                                            1,
                                                                    ),
                                                                specification:
                                                                    item.specification ??
                                                                    '',
                                                            };

                                                        return (
                                                            <div
                                                                key={
                                                                    selectedItemId
                                                                }
                                                                className="grid gap-3 rounded-xl border p-3"
                                                            >
                                                                <p className="text-sm font-medium">
                                                                    {formatAssistanceItemLabel(
                                                                        item,
                                                                    )}
                                                                </p>
                                                                <div className="grid gap-2 sm:grid-cols-2">
                                                                    <div className="space-y-2">
                                                                        <Label
                                                                            htmlFor={`delivered-item-quantity-${selectedItemId}`}
                                                                        >
                                                                            {itemQuantityFieldLabel(
                                                                                item.kind,
                                                                            )}
                                                                            {item.kind !==
                                                                                'cash' &&
                                                                            item.unit
                                                                                ? ` (${item.unit})`
                                                                                : ''}
                                                                        </Label>
                                                                        <Input
                                                                            id={`delivered-item-quantity-${selectedItemId}`}
                                                                            type="number"
                                                                            min={
                                                                                1
                                                                            }
                                                                            max={
                                                                                item.quantity ??
                                                                                undefined
                                                                            }
                                                                            step={
                                                                                1
                                                                            }
                                                                            value={
                                                                                detail.quantity
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setDeliveredItemDetails(
                                                                                    (
                                                                                        current,
                                                                                    ) => ({
                                                                                        ...current,
                                                                                        [selectedItemId]:
                                                                                            {
                                                                                                ...detail,
                                                                                                quantity:
                                                                                                    event
                                                                                                        .target
                                                                                                        .value,
                                                                                            },
                                                                                    }),
                                                                                )
                                                                            }
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                errors[
                                                                                    `delivered_items.${index}.quantity`
                                                                                ]
                                                                            }
                                                                        />
                                                                        <p className="text-xs text-muted-foreground">
                                                                            Still
                                                                            requested:{' '}
                                                                            {item.kind ===
                                                                            'cash'
                                                                                ? formatItemQuantity(
                                                                                      item.quantity,
                                                                                      item.unit,
                                                                                      item.kind,
                                                                                  )
                                                                                : (item.quantity ??
                                                                                  0)}
                                                                            .
                                                                            {tracksInventory(
                                                                                programItemKind(
                                                                                    programItems,
                                                                                    item.item_id,
                                                                                ),
                                                                            ) ? (
                                                                                <>
                                                                                    {' '}
                                                                                    Program
                                                                                    stock
                                                                                    remaining:{' '}
                                                                                    {remainingForProgramItem(
                                                                                        programItems,
                                                                                        item.item_id,
                                                                                    )}
                                                                                    .
                                                                                </>
                                                                            ) : null}{' '}
                                                                            Record
                                                                            anything
                                                                            beyond
                                                                            the
                                                                            request
                                                                            as
                                                                            an
                                                                            additional
                                                                            item.
                                                                        </p>
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        <Label
                                                                            htmlFor={`delivered-item-specification-${selectedItemId}`}
                                                                        >
                                                                            Specification
                                                                        </Label>
                                                                        <Input
                                                                            id={`delivered-item-specification-${selectedItemId}`}
                                                                            value={
                                                                                detail.specification
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setDeliveredItemDetails(
                                                                                    (
                                                                                        current,
                                                                                    ) => ({
                                                                                        ...current,
                                                                                        [selectedItemId]:
                                                                                            {
                                                                                                ...detail,
                                                                                                specification:
                                                                                                    event
                                                                                                        .target
                                                                                                        .value,
                                                                                            },
                                                                                    }),
                                                                                )
                                                                            }
                                                                            placeholder="Optional specification"
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                errors[
                                                                                    `delivered_items.${index}.specification`
                                                                                ]
                                                                            }
                                                                        />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        ) : null}
                                    </div>

                                    <div className="space-y-3">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="space-y-1">
                                                <Label>
                                                    Additional or substitute
                                                    items
                                                </Label>
                                                <p className="text-xs text-muted-foreground">
                                                    Record what was handed over
                                                    but never applied for. The
                                                    original request is left
                                                    untouched.
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    programItems.length === 0
                                                }
                                                onClick={() =>
                                                    setExtraItems((current) => [
                                                        ...current,
                                                        createExtraItemDraft(),
                                                    ])
                                                }
                                            >
                                                <Plus className="size-4" />
                                                Add item
                                            </Button>
                                        </div>
                                        <InputError
                                            message={errors.extra_items}
                                        />

                                        {extraItems.map((draft, index) => {
                                            const substituteTargets =
                                                undeliveredItems.filter(
                                                    (item) =>
                                                        String(item.id) ===
                                                            draft.substitutedForId ||
                                                        !extraItems.some(
                                                            (other) =>
                                                                other.key !==
                                                                    draft.key &&
                                                                other.substitutedForId ===
                                                                    String(
                                                                        item.id,
                                                                    ),
                                                        ),
                                                );

                                            return (
                                                <div
                                                    key={draft.key}
                                                    className="grid gap-3 rounded-xl border p-3"
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="text-sm font-medium">
                                                            {draft.origin ===
                                                            'substitute'
                                                                ? 'Substitute item'
                                                                : 'Additional item'}
                                                        </p>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setExtraItems(
                                                                    (current) =>
                                                                        current.filter(
                                                                            (
                                                                                other,
                                                                            ) =>
                                                                                other.key !==
                                                                                draft.key,
                                                                        ),
                                                                )
                                                            }
                                                        >
                                                            <X className="size-4" />
                                                            <span className="sr-only">
                                                                Remove line
                                                            </span>
                                                        </Button>
                                                    </div>

                                                    <div className="grid gap-2 sm:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label
                                                                htmlFor={`extra-item-origin-${draft.key}`}
                                                            >
                                                                Line type
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    draft.origin
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    updateExtraItem(
                                                                        draft.key,
                                                                        {
                                                                            origin: value as ExtraItemDraft['origin'],
                                                                            substitutedForId:
                                                                                '',
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger
                                                                    id={`extra-item-origin-${draft.key}`}
                                                                    className={
                                                                        selectClassName
                                                                    }
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="additional">
                                                                        Additional
                                                                        — given
                                                                        on top
                                                                        of the
                                                                        request
                                                                    </SelectItem>
                                                                    <SelectItem
                                                                        value="substitute"
                                                                        disabled={
                                                                            undeliveredItems.length ===
                                                                            0
                                                                        }
                                                                    >
                                                                        Substitute
                                                                        — given
                                                                        in place
                                                                        of a
                                                                        requested
                                                                        item
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `extra_items.${index}.origin`
                                                                    ]
                                                                }
                                                            />
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label
                                                                htmlFor={`extra-item-id-${draft.key}`}
                                                            >
                                                                Item released
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    draft.itemId
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    updateExtraItem(
                                                                        draft.key,
                                                                        {
                                                                            itemId: value,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger
                                                                    id={`extra-item-id-${draft.key}`}
                                                                    className={
                                                                        selectClassName
                                                                    }
                                                                >
                                                                    <SelectValue placeholder="Select an item" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {programItems.map(
                                                                        (
                                                                            item,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    item.id
                                                                                }
                                                                                value={String(
                                                                                    item.id,
                                                                                )}
                                                                            >
                                                                                {formatProgramItemLabel(
                                                                                    item,
                                                                                )}
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `extra_items.${index}.item_id`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    {draft.origin ===
                                                    'substitute' ? (
                                                        <div className="space-y-2">
                                                            <Label
                                                                htmlFor={`extra-item-substituted-for-${draft.key}`}
                                                            >
                                                                Replaces
                                                                requested item
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    draft.substitutedForId
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    updateExtraItem(
                                                                        draft.key,
                                                                        {
                                                                            substitutedForId:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger
                                                                    id={`extra-item-substituted-for-${draft.key}`}
                                                                    className={
                                                                        selectClassName
                                                                    }
                                                                >
                                                                    <SelectValue placeholder="Select the requested item it replaces" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {substituteTargets.map(
                                                                        (
                                                                            item,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    item.id
                                                                                }
                                                                                value={String(
                                                                                    item.id,
                                                                                )}
                                                                            >
                                                                                {formatAssistanceItemLabel(
                                                                                    item,
                                                                                )}
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `extra_items.${index}.substituted_for_assistance_item_id`
                                                                    ]
                                                                }
                                                            />
                                                            <p className="text-xs text-muted-foreground">
                                                                The requested
                                                                line is kept and
                                                                marked
                                                                substituted,
                                                                never deleted.
                                                            </p>
                                                        </div>
                                                    ) : null}

                                                    <div className="grid gap-2 sm:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label
                                                                htmlFor={`extra-item-quantity-${draft.key}`}
                                                            >
                                                                {itemQuantityFieldLabel(
                                                                    programItemKind(
                                                                        programItems,
                                                                        Number(
                                                                            draft.itemId,
                                                                        ),
                                                                    ),
                                                                )}
                                                            </Label>
                                                            <Input
                                                                id={`extra-item-quantity-${draft.key}`}
                                                                type="number"
                                                                min={1}
                                                                step={1}
                                                                value={
                                                                    draft.quantity
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateExtraItem(
                                                                        draft.key,
                                                                        {
                                                                            quantity:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `extra_items.${index}.quantity`
                                                                    ]
                                                                }
                                                            />
                                                            {draft.itemId &&
                                                            tracksInventory(
                                                                programItemKind(
                                                                    programItems,
                                                                    Number(
                                                                        draft.itemId,
                                                                    ),
                                                                ),
                                                            ) ? (
                                                                <p className="text-xs text-muted-foreground">
                                                                    Program
                                                                    stock
                                                                    remaining:{' '}
                                                                    {remainingForProgramItem(
                                                                        programItems,
                                                                        Number(
                                                                            draft.itemId,
                                                                        ),
                                                                    )}
                                                                </p>
                                                            ) : null}
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label
                                                                htmlFor={`extra-item-specification-${draft.key}`}
                                                            >
                                                                Specification
                                                            </Label>
                                                            <Input
                                                                id={`extra-item-specification-${draft.key}`}
                                                                value={
                                                                    draft.specification
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateExtraItem(
                                                                        draft.key,
                                                                        {
                                                                            specification:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="Optional specification"
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `extra_items.${index}.specification`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label
                                                            htmlFor={`extra-item-reason-${draft.key}`}
                                                        >
                                                            Reason
                                                        </Label>
                                                        <Textarea
                                                            id={`extra-item-reason-${draft.key}`}
                                                            value={
                                                                draft.fulfillmentReason
                                                            }
                                                            onChange={(event) =>
                                                                updateExtraItem(
                                                                    draft.key,
                                                                    {
                                                                        fulfillmentReason:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            rows={2}
                                                            placeholder="Why was this released? e.g. leftover pack, on-site assessment, medical add-on"
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `extra_items.${index}.fulfillment_reason`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </>
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
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        !selectedSubStatusId ||
                                        !recordedAt ||
                                        (isDeliveredStatus &&
                                            (hasIncompleteExtraItem ||
                                                exceedsProgramStock ||
                                                (selectedDeliveredItemIds.length ===
                                                    0 &&
                                                    extraItems.length === 0)))
                                    }
                                >
                                    {processing ? 'Saving...' : 'Update status'}
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
