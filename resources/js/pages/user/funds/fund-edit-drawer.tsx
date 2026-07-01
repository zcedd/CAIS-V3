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
import { FundAmountField } from '@/pages/user/funds/fund-amount-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { FundRow } from '@/types/fund';
import { update as updateFund } from '@/routes/user/funds';
import { Form } from '@inertiajs/react';
import { toast } from 'sonner';

type FundEditDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    fund: FundRow | null;
    departmentSlug: string;
};

export function FundEditDrawer({
    open,
    onOpenChange,
    fund,
    departmentSlug,
}: FundEditDrawerProps) {
    if (!fund) {
        return null;
    }

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-lg">
                <DrawerHeader>
                    <DrawerTitle>Edit fund</DrawerTitle>
                    <DrawerDescription>
                        Update details for {fund.name}.
                    </DrawerDescription>
                </DrawerHeader>
                <Form
                    {...updateFund.form({
                        department: departmentSlug,
                        fund: fund.id,
                    })}
                    disableWhileProcessing
                    onSuccess={() => {
                        onOpenChange(false);
                        toast.success('Fund updated successfully.');
                    }}
                    className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="edit-fund-name">Name</Label>
                                <Input
                                    id="edit-fund-name"
                                    name="name"
                                    defaultValue={fund.name}
                                    placeholder="Fund name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="edit-fund-amount">Amount</Label>
                                <FundAmountField
                                    id="edit-fund-amount"
                                    defaultValue={fund.amount}
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="edit-fund-year">Year</Label>
                                <Input
                                    id="edit-fund-year"
                                    name="year"
                                    defaultValue={fund.year ?? ''}
                                    placeholder="e.g. 2026"
                                    maxLength={4}
                                />
                                <InputError message={errors.year} />
                            </div>

                            <div className="flex items-start gap-3">
                                <Input
                                    id="edit-fund-is-active"
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={fund.is_active ?? true}
                                    className="mt-1 size-4 shrink-0 rounded border-input"
                                />
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="edit-fund-is-active"
                                        className="font-normal"
                                    >
                                        Active fund
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Inactive funds remain on record but can
                                        be hidden from selection.
                                    </p>
                                </div>
                            </div>

                            <DrawerFooter className="px-0">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save changes'}
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
