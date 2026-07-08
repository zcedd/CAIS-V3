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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { AssistanceTransferProgramOption } from '@/pages/user/programs/assistance-toolbar';
import { transfer as transferProgramAssistance } from '@/routes/user/programs/assistances';
import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

type AssistanceTransferDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assistanceId: number;
    departmentSlug: string;
    programId: number;
    programName: string;
    beneficiaryName: string;
    transferProgramOptions: AssistanceTransferProgramOption[];
    onTransferred?: () => void;
};

export function AssistanceTransferDrawer({
    open,
    onOpenChange,
    assistanceId,
    departmentSlug,
    programId,
    programName,
    beneficiaryName,
    transferProgramOptions,
    onTransferred,
}: AssistanceTransferDrawerProps) {
    const [formKey, setFormKey] = useState(0);
    const [selectedProgramId, setSelectedProgramId] = useState('');

    const resetForm = () => {
        setSelectedProgramId('');
    };

    useEffect(() => {
        if (!open) {
            resetForm();

            return;
        }

        setFormKey((key) => key + 1);
        resetForm();
    }, [open, assistanceId]);

    const selectedProgram = transferProgramOptions.find(
        (option) => String(option.id) === selectedProgramId,
    );

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="w-full data-[vaul-drawer-direction=right]:w-full sm:max-w-full data-[vaul-drawer-direction=right]:sm:max-w-full lg:max-w-3xl data-[vaul-drawer-direction=right]:lg:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Transfer assistance</DrawerTitle>
                    <DrawerDescription>
                        Move the assistance record for {beneficiaryName} from{' '}
                        {programName} to another open program in this office.
                    </DrawerDescription>
                </DrawerHeader>

                <Form
                    key={formKey}
                    action={transferProgramAssistance.url({
                        department: departmentSlug,
                        program: programId,
                        assistance: assistanceId,
                    })}
                    method="patch"
                    disableWhileProcessing
                    transform={() => ({
                        target_program_id: Number(selectedProgramId),
                    })}
                    onSuccess={() => {
                        resetForm();
                        onOpenChange(false);
                        toast.success('Assistance transferred successfully.');
                        onTransferred?.();
                    }}
                    className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="assistance-transfer-program">
                                    Target program
                                </Label>
                                {transferProgramOptions.length > 0 ? (
                                    <Select
                                        value={selectedProgramId}
                                        onValueChange={setSelectedProgramId}
                                    >
                                        <SelectTrigger
                                            id="assistance-transfer-program"
                                            className={selectClassName}
                                        >
                                            <SelectValue placeholder="Select program" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {transferProgramOptions.map(
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
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No other open programs are available in
                                        this office.
                                    </p>
                                )}
                                <InputError
                                    message={errors.target_program_id}
                                />
                            </div>

                            {selectedProgram ? (
                                <p className="text-sm text-muted-foreground">
                                    This assistance will be moved to{' '}
                                    <span className="font-medium text-foreground">
                                        {selectedProgram.name}
                                    </span>
                                    .
                                </p>
                            ) : null}

                            <DrawerFooter className="px-0">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        !selectedProgramId ||
                                        transferProgramOptions.length === 0
                                    }
                                >
                                    {processing
                                        ? 'Transferring...'
                                        : 'Transfer assistance'}
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
