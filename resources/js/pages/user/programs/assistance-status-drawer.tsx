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
import { cn } from '@/lib/utils';
import type { UserProgramAssistanceItem } from '@/pages/user/programs/assistance-columns';
import type { AssistanceRequestSubStatusOption } from '@/pages/user/programs/assistance-toolbar';
import { update as updateProgramAssistanceStatus } from '@/routes/user/programs/assistances/status';
import { Form } from '@inertiajs/react';
import { CalendarDays, ChevronDownIcon, RotateCcw } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

type DeliveredItemDetail = {
    quantity: string;
    specification: string;
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
    const parts = [item.name];

    if (item.quantity !== null && item.unit) {
        parts.push(`× ${item.quantity} ${item.unit}`);
    } else if (item.quantity !== null) {
        parts.push(`× ${item.quantity}`);
    } else if (item.unit) {
        parts.push(item.unit);
    }

    return parts.join(' ');
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
    const [recordedAt, setRecordedAt] = useState<Date | undefined>(undefined);
    const [recordedAtOpen, setRecordedAtOpen] = useState(false);
    const [defaultRemark, setDefaultRemark] = useState('');

    const selectedSubStatus = requestSubStatusOptions.find(
        (option) => String(option.id) === selectedSubStatusId,
    );
    const isDeliveredStatus = selectedSubStatus?.request_status === 'Delivered';
    const undeliveredItems = useMemo(
        () => assistanceItems.filter((item) => !item.is_received),
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

    const resetForm = () => {
        setSelectedSubStatusId('');
        setSelectedDeliveredItemIds([]);
        setDeliveredItemDetails({});
        setRecordedAt(undefined);
        setRecordedAtOpen(false);
        setDefaultRemark('');
    };

    const populateForm = () => {
        setSelectedSubStatusId(
            currentSubStatusId !== null ? String(currentSubStatusId) : '',
        );
        setRecordedAt(parseRecordedAt(currentRecordedAt) ?? new Date());
        setDefaultRemark('');
        setSelectedDeliveredItemIds([]);
        setDeliveredItemDetails({});
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
            setSelectedDeliveredItemIds([]);
            setDeliveredItemDetails({});

            return;
        }

        if (undeliveredItems.length === 1) {
            const item = undeliveredItems[0];

            setSelectedDeliveredItemIds([String(item.id)]);
            setDeliveredItemDetails({
                [String(item.id)]: {
                    quantity: String(item.quantity ?? 1),
                    specification: item.specification ?? '',
                },
            });
        }
    }, [isDeliveredStatus, selectedSubStatusId, undeliveredItems]);

    useEffect(() => {
        setDeliveredItemDetails((current) => {
            const next: Record<string, DeliveredItemDetail> = {};

            selectedDeliveredItemIds.forEach((itemId) => {
                const assistanceItem = undeliveredItems.find(
                    (item) => String(item.id) === itemId,
                );

                next[itemId] = current[itemId] ?? {
                    quantity: String(assistanceItem?.quantity ?? 1),
                    specification: assistanceItem?.specification ?? '',
                };
            });

            return next;
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
                    transform={(data) => ({
                        ...data,
                        request_sub_status_id: Number(selectedSubStatusId),
                        recorded_at: formatDateForSubmit(recordedAt),
                        delivered_items: isDeliveredStatus
                            ? selectedDeliveredItemIds.map((itemId) => ({
                                  assistance_item_id: Number(itemId),
                                  quantity: Number(
                                      deliveredItemDetails[itemId]?.quantity ??
                                          1,
                                  ),
                                  specification:
                                      deliveredItemDetails[itemId]
                                          ?.specification ?? '',
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

                            {isDeliveredStatus ? (
                                <div className="space-y-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="assistance-status-delivered-items">
                                            Delivered items
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
                                                placeholder="Choose items delivered..."
                                                className="w-full"
                                            />
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                All items on this assistance
                                                have already been marked as
                                                delivered.
                                            </p>
                                        )}
                                        <InputError
                                            message={errors.delivered_items}
                                        />
                                    </div>

                                    {selectedDeliveredItemIds.length > 0 ? (
                                        <div className="space-y-3">
                                            <Label>Delivered item details</Label>
                                            {selectedDeliveredItemIds.map(
                                                (selectedItemId, index) => {
                                                    const item =
                                                        undeliveredItems.find(
                                                            ({ id }) =>
                                                                String(id) ===
                                                                selectedItemId,
                                                        );

                                                    if (!item) {
                                                        return null;
                                                    }

                                                    const detail =
                                                        deliveredItemDetails[
                                                            selectedItemId
                                                        ] ?? {
                                                            quantity: String(
                                                                item.quantity ??
                                                                    1,
                                                            ),
                                                            specification:
                                                                item.specification ??
                                                                '',
                                                        };

                                                    return (
                                                        <div
                                                            key={selectedItemId}
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
                                                                        Quantity
                                                                        {item.unit
                                                                            ? ` (${item.unit})`
                                                                            : ''}
                                                                    </Label>
                                                                    <Input
                                                                        id={`delivered-item-quantity-${selectedItemId}`}
                                                                        type="number"
                                                                        min={1}
                                                                        max={
                                                                            item.quantity ??
                                                                            undefined
                                                                        }
                                                                        step={1}
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
                                            (undeliveredItems.length === 0 ||
                                                selectedDeliveredItemIds.length ===
                                                    0))
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
