'use client';

import InputError from '@/components/input-error';
import { UnspscCodeCombobox } from '@/components/unspsc-code-combobox';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { update as updateDepartmentItem } from '@/routes/user/items';
import {
    ITEM_KIND_OPTIONS,
    tracksInventory,
    type ItemKind,
} from '@/types/item';
import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import type { UserDepartmentItemRow } from '@/pages/user/items/item-columns';
import type { UnitMeasurementOption } from '@/pages/user/items/item-toolbar';

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

type ItemEditDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: UserDepartmentItemRow;
    departmentSlug: string;
    unitMeasurements: UnitMeasurementOption[];
    onUpdated?: () => void;
};

export function ItemEditDrawer({
    open,
    onOpenChange,
    item,
    departmentSlug,
    unitMeasurements,
    onUpdated,
}: ItemEditDrawerProps) {
    const [formKey, setFormKey] = useState(0);
    const [defaultUnitId, setDefaultUnitId] = useState('');
    const [kind, setKind] = useState<ItemKind>('goods');
    const [unspscCodeId, setUnspscCodeId] = useState<number | null>(null);
    const [isPerishable, setIsPerishable] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        setDefaultUnitId(
            item.item_unit_measurement_id !== null
                ? String(item.item_unit_measurement_id)
                : '',
        );
        setKind(item.kind);
        setUnspscCodeId(item.unspsc_code_id);
        setIsPerishable(item.is_perishable);
        setFormKey((key) => key + 1);
    }, [open, item]);

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Edit item</DrawerTitle>
                    <DrawerDescription>
                        Change the catalog item, UNSPSC code, and stock
                        settings.
                    </DrawerDescription>
                </DrawerHeader>

                <Form
                    key={formKey}
                    action={updateDepartmentItem.url({
                        department: departmentSlug,
                        item: item.id,
                    })}
                    method="put"
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => {
                        onOpenChange(false);
                        toast.success('Item updated successfully.');
                        onUpdated?.();
                    }}
                    className="space-y-4 px-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor={`edit-item-name-${item.id}`}>
                                    Name
                                </Label>
                                <Input
                                    id={`edit-item-name-${item.id}`}
                                    name="name"
                                    defaultValue={item.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={`edit-item-kind-${item.id}`}>
                                    Kind
                                </Label>
                                <Select
                                    name="kind"
                                    value={kind}
                                    onValueChange={(value) => {
                                        const nextKind = value as ItemKind;
                                        setKind(nextKind);

                                        if (nextKind === 'cash') {
                                            const phpUnit =
                                                unitMeasurements.find(
                                                    (unit) =>
                                                        unit.name.toLowerCase() ===
                                                        'php',
                                                );

                                            if (phpUnit) {
                                                setDefaultUnitId(
                                                    String(phpUnit.id),
                                                );
                                            }
                                        }

                                        if (nextKind !== 'goods') {
                                            setIsPerishable(false);
                                        }
                                    }}
                                    required
                                >
                                    <SelectTrigger
                                        id={`edit-item-kind-${item.id}`}
                                        className={selectClassName}
                                    >
                                        <SelectValue placeholder="Select kind" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ITEM_KIND_OPTIONS.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.kind} />
                            </div>

                            <div className="space-y-2">
                                <Label
                                    htmlFor={`edit-item-unit-${item.id}`}
                                >
                                    Unit of measurement
                                </Label>
                                <Select
                                    key={defaultUnitId}
                                    name="item_unit_measurement_id"
                                    value={defaultUnitId}
                                    onValueChange={setDefaultUnitId}
                                    required
                                    disabled={kind === 'cash'}
                                >
                                    <SelectTrigger
                                        id={`edit-item-unit-${item.id}`}
                                        className={selectClassName}
                                    >
                                        <SelectValue placeholder="Select unit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {unitMeasurements.map((unit) => (
                                            <SelectItem
                                                key={unit.id}
                                                value={String(unit.id)}
                                            >
                                                {unit.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {kind === 'cash' && defaultUnitId ? (
                                    <input
                                        type="hidden"
                                        name="item_unit_measurement_id"
                                        value={defaultUnitId}
                                    />
                                ) : null}
                                <InputError
                                    message={errors.item_unit_measurement_id}
                                />
                            </div>

                            <UnspscCodeCombobox
                                departmentSlug={departmentSlug}
                                value={unspscCodeId}
                                initialOption={
                                    item.unspsc_code_id && item.unspsc_code
                                        ? {
                                              id: item.unspsc_code_id,
                                              code: item.unspsc_code,
                                              title: item.unspsc_title ?? item.unspsc_code,
                                              path: item.unspsc_title ?? item.unspsc_code,
                                              is_curated: true,
                                          }
                                        : null
                                }
                                onChange={(next) => setUnspscCodeId(next)}
                                error={errors.unspsc_code_id}
                            />

                            {tracksInventory(kind) ? (
                                <>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="hidden"
                                            name="is_perishable"
                                            value={isPerishable ? '1' : '0'}
                                        />
                                        <Checkbox
                                            id={`edit-item-perishable-${item.id}`}
                                            checked={isPerishable}
                                            onCheckedChange={(checked) =>
                                                setIsPerishable(
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={`edit-item-perishable-${item.id}`}
                                        >
                                            Perishable (require batch and
                                            expiry)
                                        </Label>
                                    </div>
                                    <InputError
                                        message={errors.is_perishable}
                                    />

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`edit-item-threshold-${item.id}`}
                                        >
                                            Low-stock threshold
                                        </Label>
                                        <Input
                                            id={`edit-item-threshold-${item.id}`}
                                            name="low_stock_threshold"
                                            type="number"
                                            min={0}
                                            defaultValue={
                                                item.low_stock_threshold ?? ''
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.low_stock_threshold
                                            }
                                        />
                                    </div>
                                </>
                            ) : null}

                            <DrawerFooter className="px-0">
                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>
                                <DrawerClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
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
    );
}
