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
import { bulkTransfer as bulkTransferProgramAssistance } from '@/routes/user/programs/assistances';
import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

const selectClassName = cn(
    'h-9 w-full min-w-0 rounded-4xl border border-input bg-input/30 px-3 py-1 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
);

type AssistanceBulkTransferDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assistanceIds: number[];
    departmentSlug: string;
    programId: number;
    programName: string;
    transferProgramOptions: AssistanceTransferProgramOption[];
    onTransferred?: () => void;
};

export function AssistanceBulkTransferDrawer({
    open,
    onOpenChange,
    assistanceIds,
    departmentSlug,
    programId,
    programName,
    transferProgramOptions,
    onTransferred,
}: AssistanceBulkTransferDrawerProps) {
    const [formKey, setFormKey] = useState(0);
    const [selectedProgramId, setSelectedProgramId] = useState('');

    const selectedCount = assistanceIds.length;

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
    }, [open, assistanceIds]);

    const selectedProgram = transferProgramOptions.find(
        (option) => String(option.id) === selectedProgramId,
    );

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="w-full data-[vaul-drawer-direction=right]:w-full sm:max-w-full data-[vaul-drawer-direction=right]:sm:max-w-full lg:max-w-3xl data-[vaul-drawer-direction=right]:lg:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Bulk transfer assistance</DrawerTitle>
                    <DrawerDescription>
                        Move {selectedCount} selected assistance record
                        {selectedCount === 1 ? '' : 's'} from {programName} to
                        another open program in this office.
                    </DrawerDescription>
                </DrawerHeader>

                <Form
                    key={formKey}
                    action={bulkTransferProgramAssistance.url({
                        department: departmentSlug,
                        program: programId,
                    })}
                    method="patch"
                    disableWhileProcessing
                    transform={() => ({
                        assistance_ids: assistanceIds,
                        target_program_id: Number(selectedProgramId),
                    })}
                    onSuccess={() => {
                        resetForm();
                        onOpenChange(false);
                        toast.success(
                            `${selectedCount} assistance record${selectedCount === 1 ? '' : 's'} transferred successfully.`,
                        );
                        onTransferred?.();
                    }}
                    className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="bulk-assistance-transfer-program">
                                    Target program
                                </Label>
                                {transferProgramOptions.length > 0 ? (
                                    <Select
                                        value={selectedProgramId}
                                        onValueChange={setSelectedProgramId}
                                    >
                                        <SelectTrigger
                                            id="bulk-assistance-transfer-program"
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
                                    Selected records will be moved to{' '}
                                    <span className="font-medium text-foreground">
                                        {selectedProgram.name}
                                    </span>
                                    .
                                </p>
                            ) : null}

                            <InputError message={errors.assistance_ids} />

                            <DrawerFooter className="px-0">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        !selectedProgramId ||
                                        selectedCount === 0 ||
                                        transferProgramOptions.length === 0
                                    }
                                >
                                    {processing
                                        ? 'Transferring...'
                                        : `Transfer ${selectedCount} record${selectedCount === 1 ? '' : 's'}`}
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
