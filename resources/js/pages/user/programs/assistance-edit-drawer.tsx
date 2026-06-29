'use client';

import {
    BeneficiarySearchCombobox,
    type BeneficiarySearchOption,
} from '@/components/beneficiary-search-combobox';
import InputError from '@/components/input-error';
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
import { cn } from '@/lib/utils';
import type {
    AssistanceModeOption,
    AssistanceProgramItemOption,
} from '@/pages/user/programs/assistance-toolbar';
import {
    edit as editProgramAssistance,
    update as updateProgramAssistance,
} from '@/routes/user/programs/assistances';
import { Form } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

type AssistanceItemDetail = {
    quantity: string;
    specification: string;
};

type AssistanceEditPayload = {
    id: number;
    beneficiary_id: number | null;
    beneficiary: BeneficiarySearchOption | null;
    mode_of_request_id: number | null;
    remark: string | null;
    item_details: {
        item_id: number;
        quantity: number;
        specification: string | null;
    }[];
};

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

type AssistanceEditDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assistanceId: number;
    departmentSlug: string;
    programId: number;
    programName: string;
    isOrganization: boolean;
    modeOfRequestOptions: AssistanceModeOption[];
    programItems: AssistanceProgramItemOption[];
    onUpdated?: () => void;
};

export function AssistanceEditDrawer({
    open,
    onOpenChange,
    assistanceId,
    departmentSlug,
    programId,
    programName,
    isOrganization,
    modeOfRequestOptions,
    programItems,
    onUpdated,
}: AssistanceEditDrawerProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [formKey, setFormKey] = useState(0);
    const [selectedItemIds, setSelectedItemIds] = useState<string[]>([]);
    const [itemDetails, setItemDetails] = useState<
        Record<string, AssistanceItemDetail>
    >({});
    const [selectedBeneficiaryId, setSelectedBeneficiaryId] = useState<
        number | null
    >(null);
    const [beneficiaryInitialOption, setBeneficiaryInitialOption] =
        useState<BeneficiarySearchOption | null>(null);
    const [defaultModeOfRequestId, setDefaultModeOfRequestId] = useState('');
    const [defaultRemark, setDefaultRemark] = useState('');

    const programItemSelectOptions = programItems.map((item) => ({
        value: String(item.id),
        label: item.unit ? `${item.name} (${item.unit})` : item.name,
    }));

    const populateForm = (payload: AssistanceEditPayload) => {
        setSelectedBeneficiaryId(payload.beneficiary_id);
        setBeneficiaryInitialOption(payload.beneficiary);
        setDefaultModeOfRequestId(
            payload.mode_of_request_id !== null
                ? String(payload.mode_of_request_id)
                : '',
        );
        setDefaultRemark(payload.remark ?? '');
        setSelectedItemIds(
            payload.item_details.map((detail) => String(detail.item_id)),
        );
        setItemDetails(
            Object.fromEntries(
                payload.item_details.map((detail) => [
                    String(detail.item_id),
                    {
                        quantity: String(detail.quantity),
                        specification: detail.specification ?? '',
                    },
                ]),
            ),
        );
        setFormKey((key) => key + 1);
    };

    const resetForm = () => {
        setSelectedItemIds([]);
        setItemDetails({});
        setSelectedBeneficiaryId(null);
        setBeneficiaryInitialOption(null);
        setDefaultModeOfRequestId('');
        setDefaultRemark('');
        setLoadError(null);
    };

    useEffect(() => {
        if (!open) {
            resetForm();

            return;
        }

        const fetchAssistance = async () => {
            setIsLoading(true);
            setLoadError(null);

            try {
                const response = await fetch(
                    editProgramAssistance.url({
                        department: departmentSlug,
                        program: programId,
                        assistance: assistanceId,
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    throw new Error('Failed to load assistance');
                }

                const payload = (await response.json()) as {
                    data: AssistanceEditPayload;
                };

                populateForm(payload.data);
            } catch {
                setLoadError('Unable to load assistance details.');
            } finally {
                setIsLoading(false);
            }
        };

        void fetchAssistance();
    }, [open, assistanceId, departmentSlug, programId]);

    useEffect(() => {
        setItemDetails((current) => {
            const next: Record<string, AssistanceItemDetail> = {};

            selectedItemIds.forEach((itemId) => {
                next[itemId] = current[itemId] ?? {
                    quantity: '1',
                    specification: '',
                };
            });

            return next;
        });
    }, [selectedItemIds]);

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="w-full data-[vaul-drawer-direction=right]:w-full sm:max-w-full data-[vaul-drawer-direction=right]:sm:max-w-full lg:max-w-3xl data-[vaul-drawer-direction=right]:lg:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Edit assistance</DrawerTitle>
                    <DrawerDescription>
                        Update assistance record for {programName}.
                    </DrawerDescription>
                </DrawerHeader>

                {isLoading ? (
                    <div className="flex flex-1 items-center justify-center gap-2 py-12 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        Loading assistance...
                    </div>
                ) : loadError ? (
                    <p className="px-4 text-sm text-destructive">{loadError}</p>
                ) : (
                    <Form
                        key={formKey}
                        action={updateProgramAssistance.url({
                            department: departmentSlug,
                            program: programId,
                            assistance: assistanceId,
                        })}
                        method="put"
                        disableWhileProcessing
                        transform={(data) => ({
                            ...data,
                            item_details: selectedItemIds.map((itemId) => ({
                                item_id: Number(itemId),
                                quantity: Number(
                                    itemDetails[itemId]?.quantity ?? 1,
                                ),
                                specification:
                                    itemDetails[itemId]?.specification ?? '',
                            })),
                        })}
                        onSuccess={() => {
                            resetForm();
                            onOpenChange(false);
                            toast.success('Assistance updated successfully.');
                            onUpdated?.();
                        }}
                        className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                {isOrganization ? (
                                    <BeneficiarySearchCombobox
                                        beneficiaryType="organization"
                                        label="Organization"
                                        name="beneficiary_id"
                                        value={selectedBeneficiaryId}
                                        initialOption={beneficiaryInitialOption}
                                        onChange={(id) =>
                                            setSelectedBeneficiaryId(id)
                                        }
                                        error={errors.beneficiary_id}
                                    />
                                ) : (
                                    <BeneficiarySearchCombobox
                                        beneficiaryType="individual"
                                        name="beneficiary_id"
                                        value={selectedBeneficiaryId}
                                        initialOption={beneficiaryInitialOption}
                                        onChange={(id) =>
                                            setSelectedBeneficiaryId(id)
                                        }
                                        error={errors.beneficiary_id}
                                    />
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="edit-assistance-mode">
                                        Mode of request
                                    </Label>
                                    <Select
                                        name="mode_of_request_id"
                                        defaultValue={defaultModeOfRequestId}
                                    >
                                        <SelectTrigger
                                            id="edit-assistance-mode"
                                            className={selectClassName}
                                        >
                                            <SelectValue placeholder="Select mode of request" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {modeOfRequestOptions.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.id}
                                                        value={String(
                                                            option.id,
                                                        )}
                                                    >
                                                        {option.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.mode_of_request_id}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="edit-assistance-items">
                                        Items
                                    </Label>
                                    <MultiSelect
                                        options={programItemSelectOptions}
                                        selected={selectedItemIds}
                                        onChange={setSelectedItemIds}
                                        placeholder="Choose items..."
                                        className="w-full"
                                    />
                                    <InputError
                                        message={
                                            errors.item_details ??
                                            errors.item_ids
                                        }
                                    />
                                </div>

                                {selectedItemIds.length > 0 ? (
                                    <div className="space-y-3">
                                        <Label>Item details</Label>
                                        {selectedItemIds.map(
                                            (selectedItemId, index) => {
                                                const item = programItems.find(
                                                    ({ id }) =>
                                                        String(id) ===
                                                        selectedItemId,
                                                );

                                                if (!item) {
                                                    return null;
                                                }

                                                const detail = itemDetails[
                                                    selectedItemId
                                                ] ?? {
                                                    quantity: '1',
                                                    specification: '',
                                                };

                                                return (
                                                    <div
                                                        key={selectedItemId}
                                                        className="grid gap-3 rounded-xl border p-3"
                                                    >
                                                        <p className="text-sm font-medium">
                                                            {item.name}
                                                        </p>
                                                        <div className="grid gap-2 sm:grid-cols-2">
                                                            <div className="space-y-2">
                                                                <Label
                                                                    htmlFor={`edit-item-quantity-${selectedItemId}`}
                                                                >
                                                                    Quantity
                                                                </Label>
                                                                <Input
                                                                    id={`edit-item-quantity-${selectedItemId}`}
                                                                    type="number"
                                                                    min={1}
                                                                    step={1}
                                                                    value={
                                                                        detail.quantity
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setItemDetails(
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
                                                                            `item_details.${index}.quantity`
                                                                        ]
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label
                                                                    htmlFor={`edit-item-specification-${selectedItemId}`}
                                                                >
                                                                    Specification
                                                                </Label>
                                                                <Input
                                                                    id={`edit-item-specification-${selectedItemId}`}
                                                                    value={
                                                                        detail.specification
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setItemDetails(
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
                                                                            `item_details.${index}.specification`
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

                                <div className="space-y-2">
                                    <Label htmlFor="edit-assistance-remark">
                                        Remark
                                    </Label>
                                    <Textarea
                                        id="edit-assistance-remark"
                                        name="remark"
                                        defaultValue={defaultRemark}
                                        placeholder="Optional notes about this request"
                                        rows={3}
                                    />
                                    <InputError message={errors.remark} />
                                </div>

                                <DrawerFooter className="px-0">
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            programItems.length === 0
                                        }
                                    >
                                        {processing
                                            ? 'Saving...'
                                            : 'Save changes'}
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
                )}
            </DrawerContent>
        </Drawer>
    );
}
