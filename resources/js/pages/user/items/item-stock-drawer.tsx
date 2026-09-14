'use client';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type { UserDepartmentItemRow } from '@/pages/user/items/item-columns';
import { store as storeItemAdjustment } from '@/routes/user/items/stock/adjustments';
import { store as storeItemAllocation } from '@/routes/user/items/stock/allocations';
import { store as storeItemReceipt } from '@/routes/user/items/stock/receipts';
import { show as itemStockShow } from '@/routes/user/items/stock';
import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

type StockLotRow = {
    id: number;
    batch_number: string | null;
    expires_at: string | null;
    received_at: string | null;
    on_hand: number;
    is_expired: boolean;
};

type StockMovementRow = {
    id: number;
    type: string;
    quantity: number;
    program_name: string | null;
    batch_number: string | null;
    reason: string | null;
    user_name: string | null;
    occurred_at: string | null;
};

type ProgramOption = {
    id: number;
    name: string;
};

type ItemStockDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: UserDepartmentItemRow;
    departmentSlug: string;
    onUpdated?: () => void;
};

export function ItemStockDrawer({
    open,
    onOpenChange,
    item,
    departmentSlug,
    onUpdated,
}: ItemStockDrawerProps) {
    const [lots, setLots] = useState<StockLotRow[]>([]);
    const [movements, setMovements] = useState<StockMovementRow[]>([]);
    const [programs, setPrograms] = useState<ProgramOption[]>([]);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [formKey, setFormKey] = useState(0);

    useEffect(() => {
        if (!open) {
            return;
        }

        setFormKey((key) => key + 1);
        setLoadError(null);

        const controller = new AbortController();

        void fetch(
            itemStockShow.url({
                department: departmentSlug,
                item: item.id,
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Failed to load stock');
                }

                return response.json() as Promise<{
                    lots: StockLotRow[];
                    movements: StockMovementRow[];
                    programs: ProgramOption[];
                }>;
            })
            .then((payload) => {
                setLots(payload.lots);
                setMovements(payload.movements);
                setPrograms(payload.programs);
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                setLoadError('Unable to load stock details.');
            });

        return () => controller.abort();
    }, [open, departmentSlug, item.id]);

    const handleSuccess = (message: string) => {
        toast.success(message);
        onUpdated?.();
        onOpenChange(false);
    };

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Stock · {item.name}</DrawerTitle>
                    <DrawerDescription>
                        Receive, allocate, and review movements. On hand{' '}
                        {item.on_hand}
                        {item.unit ? ` ${item.unit}` : ''}, available{' '}
                        {item.available}.
                    </DrawerDescription>
                </DrawerHeader>

                <div className="space-y-4 px-4 pb-4">
                    {loadError ? (
                        <p className="text-sm text-destructive">{loadError}</p>
                    ) : null}

                    <Tabs defaultValue="receive">
                        <TabsList variant="line" className="w-full">
                            <TabsTrigger value="receive">Receive</TabsTrigger>
                            <TabsTrigger value="allocate">Allocate</TabsTrigger>
                            <TabsTrigger value="adjust">Adjust</TabsTrigger>
                            <TabsTrigger value="history">History</TabsTrigger>
                        </TabsList>

                        <TabsContent value="receive" className="pt-4">
                            <Form
                                key={`receive-${formKey}`}
                                action={storeItemReceipt.url({
                                    department: departmentSlug,
                                    item: item.id,
                                })}
                                method="post"
                                options={{ preserveScroll: true }}
                                onSuccess={() => handleSuccess('Stock received.')}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor={`receive-qty-${item.id}`}>
                                                Quantity
                                            </Label>
                                            <Input
                                                id={`receive-qty-${item.id}`}
                                                name="quantity"
                                                type="number"
                                                min={1}
                                                required
                                            />
                                            <InputError message={errors.quantity} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`receive-type-${item.id}`}>
                                                Type
                                            </Label>
                                            <Select
                                                name="type"
                                                defaultValue="opening_balance"
                                                required
                                            >
                                                <SelectTrigger
                                                    id={`receive-type-${item.id}`}
                                                    className={selectClassName}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="opening_balance">
                                                        Opening balance
                                                    </SelectItem>
                                                    <SelectItem value="receipt">
                                                        Receipt
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.type} />
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor={`receive-batch-${item.id}`}
                                                >
                                                    Batch number
                                                    {item.is_perishable
                                                        ? ''
                                                        : ' (optional)'}
                                                </Label>
                                                <Input
                                                    id={`receive-batch-${item.id}`}
                                                    name="batch_number"
                                                    required={item.is_perishable}
                                                />
                                                <InputError
                                                    message={errors.batch_number}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor={`receive-expiry-${item.id}`}
                                                >
                                                    Expiry date
                                                    {item.is_perishable
                                                        ? ''
                                                        : ' (optional)'}
                                                </Label>
                                                <Input
                                                    id={`receive-expiry-${item.id}`}
                                                    name="expires_at"
                                                    type="date"
                                                    required={item.is_perishable}
                                                />
                                                <InputError
                                                    message={errors.expires_at}
                                                />
                                            </div>
                                        </div>
                                        <DrawerFooter className="px-0">
                                            <Button type="submit" disabled={processing}>
                                                Receive stock
                                            </Button>
                                        </DrawerFooter>
                                    </>
                                )}
                            </Form>
                        </TabsContent>

                        <TabsContent value="allocate" className="pt-4">
                            <Form
                                key={`allocate-${formKey}`}
                                action={storeItemAllocation.url({
                                    department: departmentSlug,
                                    item: item.id,
                                })}
                                method="post"
                                options={{ preserveScroll: true }}
                                onSuccess={() =>
                                    handleSuccess('Program allocation updated.')
                                }
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`allocate-program-${item.id}`}
                                            >
                                                Program
                                            </Label>
                                            <Select name="program_id" required>
                                                <SelectTrigger
                                                    id={`allocate-program-${item.id}`}
                                                    className={selectClassName}
                                                >
                                                    <SelectValue placeholder="Select program" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {programs.map((program) => (
                                                        <SelectItem
                                                            key={program.id}
                                                            value={String(program.id)}
                                                        >
                                                            {program.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.program_id} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`allocate-type-${item.id}`}>
                                                Action
                                            </Label>
                                            <Select
                                                name="type"
                                                defaultValue="allocate"
                                                required
                                            >
                                                <SelectTrigger
                                                    id={`allocate-type-${item.id}`}
                                                    className={selectClassName}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="allocate">
                                                        Allocate
                                                    </SelectItem>
                                                    <SelectItem value="deallocate">
                                                        Deallocate
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.type} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`allocate-qty-${item.id}`}>
                                                Quantity
                                            </Label>
                                            <Input
                                                id={`allocate-qty-${item.id}`}
                                                name="quantity"
                                                type="number"
                                                min={1}
                                                required
                                            />
                                            <InputError message={errors.quantity} />
                                        </div>
                                        <DrawerFooter className="px-0">
                                            <Button type="submit" disabled={processing}>
                                                Save allocation
                                            </Button>
                                        </DrawerFooter>
                                    </>
                                )}
                            </Form>
                        </TabsContent>

                        <TabsContent value="adjust" className="pt-4">
                            <Form
                                key={`adjust-${formKey}`}
                                action={storeItemAdjustment.url({
                                    department: departmentSlug,
                                    item: item.id,
                                })}
                                method="post"
                                options={{ preserveScroll: true }}
                                onSuccess={() => handleSuccess('Stock adjusted.')}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor={`adjust-lot-${item.id}`}>
                                                Lot
                                            </Label>
                                            <Select name="stock_lot_id" required>
                                                <SelectTrigger
                                                    id={`adjust-lot-${item.id}`}
                                                    className={selectClassName}
                                                >
                                                    <SelectValue placeholder="Select lot" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {lots.map((lot) => (
                                                        <SelectItem
                                                            key={lot.id}
                                                            value={String(lot.id)}
                                                        >
                                                            {(lot.batch_number ??
                                                                `Lot #${lot.id}`) +
                                                                ` · ${lot.on_hand} on hand`}
                                                            {lot.is_expired
                                                                ? ' · expired'
                                                                : ''}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.stock_lot_id}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`adjust-type-${item.id}`}>
                                                Type
                                            </Label>
                                            <Select
                                                name="type"
                                                defaultValue="adjustment_in"
                                                required
                                            >
                                                <SelectTrigger
                                                    id={`adjust-type-${item.id}`}
                                                    className={selectClassName}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="adjustment_in">
                                                        Adjustment in
                                                    </SelectItem>
                                                    <SelectItem value="adjustment_out">
                                                        Adjustment out
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.type} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`adjust-qty-${item.id}`}>
                                                Quantity
                                            </Label>
                                            <Input
                                                id={`adjust-qty-${item.id}`}
                                                name="quantity"
                                                type="number"
                                                min={1}
                                                required
                                            />
                                            <InputError message={errors.quantity} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`adjust-reason-${item.id}`}
                                            >
                                                Reason
                                            </Label>
                                            <Input
                                                id={`adjust-reason-${item.id}`}
                                                name="reason"
                                                required
                                            />
                                            <InputError message={errors.reason} />
                                        </div>
                                        <DrawerFooter className="px-0">
                                            <Button type="submit" disabled={processing}>
                                                Save adjustment
                                            </Button>
                                        </DrawerFooter>
                                    </>
                                )}
                            </Form>
                        </TabsContent>

                        <TabsContent value="history" className="space-y-4 pt-4">
                            <div className="space-y-2">
                                <p className="text-sm font-medium">Lots</p>
                                {lots.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No lots yet. Receive an opening balance first.
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {lots.map((lot) => (
                                            <li
                                                key={lot.id}
                                                className="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        {lot.batch_number ??
                                                            `Lot #${lot.id}`}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {lot.expires_at
                                                            ? `Expires ${lot.expires_at}`
                                                            : 'No expiry'}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    {lot.is_expired ? (
                                                        <Badge variant="destructive">
                                                            Expired
                                                        </Badge>
                                                    ) : null}
                                                    <span className="tabular-nums">
                                                        {lot.on_hand}
                                                    </span>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            <div className="space-y-2">
                                <p className="text-sm font-medium">Movements</p>
                                {movements.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No movements recorded.
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {movements.map((movement) => (
                                            <li
                                                key={movement.id}
                                                className="rounded-lg border px-3 py-2 text-sm"
                                            >
                                                <p className="font-medium">
                                                    {movement.type.replaceAll('_', ' ')}{' '}
                                                    · {movement.quantity}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {[
                                                        movement.program_name,
                                                        movement.batch_number,
                                                        movement.user_name,
                                                        movement.occurred_at,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            <DrawerFooter className="px-0">
                                <DrawerClose asChild>
                                    <Button type="button" variant="outline">
                                        Close
                                    </Button>
                                </DrawerClose>
                            </DrawerFooter>
                        </TabsContent>
                    </Tabs>
                </div>
            </DrawerContent>
        </Drawer>
    );
}
