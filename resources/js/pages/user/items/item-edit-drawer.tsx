'use client';

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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { update as updateDepartmentItem } from '@/routes/user/items';
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

    useEffect(() => {
        if (!open) {
            return;
        }

        setDefaultUnitId(
            item.item_unit_measurement_id !== null
                ? String(item.item_unit_measurement_id)
                : '',
        );
        setFormKey((key) => key + 1);
    }, [open, item]);

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Edit item</DrawerTitle>
                    <DrawerDescription>
                        Update the item name and unit of measurement.
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
                                <Label
                                    htmlFor={`edit-item-unit-${item.id}`}
                                >
                                    Unit of measurement
                                </Label>
                                <Select
                                    key={defaultUnitId}
                                    name="item_unit_measurement_id"
                                    defaultValue={defaultUnitId}
                                    required
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
                                <InputError
                                    message={errors.item_unit_measurement_id}
                                />
                            </div>

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
