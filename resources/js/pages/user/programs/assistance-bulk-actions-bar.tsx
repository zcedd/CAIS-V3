'use client';

import { Button } from '@/components/ui/button';
import type { UserProgramAssistanceRow } from '@/pages/user/programs/assistance-columns';
import { AssistanceBulkStatusDrawer } from '@/pages/user/programs/assistance-bulk-status-drawer';
import { AssistanceBulkTransferDrawer } from '@/pages/user/programs/assistance-bulk-transfer-drawer';
import type {
    AssistanceRequestSubStatusOption,
    AssistanceTransferProgramOption,
} from '@/pages/user/programs/assistance-toolbar';
import { ArrowRightLeft, ListChecks } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { RowSelectionState, Table } from '@tanstack/react-table';

type AssistanceBulkActionsBarProps = {
    table: Table<UserProgramAssistanceRow>;
    rowSelection: RowSelectionState;
    selectedCount: number;
    departmentSlug: string;
    programId: number;
    programName: string;
    requestSubStatusOptions: AssistanceRequestSubStatusOption[];
    transferProgramOptions: AssistanceTransferProgramOption[];
    canTransferAssistance: boolean;
    onBulkStatusUpdated?: () => void;
};

export function AssistanceBulkActionsBar({
    table,
    rowSelection,
    selectedCount,
    departmentSlug,
    programId,
    programName,
    requestSubStatusOptions,
    transferProgramOptions,
    canTransferAssistance,
    onBulkStatusUpdated,
}: AssistanceBulkActionsBarProps) {
    const [bulkStatusOpen, setBulkStatusOpen] = useState(false);
    const [bulkTransferOpen, setBulkTransferOpen] = useState(false);

    const selectedAssistanceIds = useMemo(() => {
        return Object.entries(rowSelection)
            .filter(([, isSelected]) => Boolean(isSelected))
            .map(([rowId]) => table.getRow(rowId)?.original.id)
            .filter((id): id is number => typeof id === 'number');
    }, [rowSelection, table]);

    const handleBulkStatusUpdated = () => {
        table.resetRowSelection();
        onBulkStatusUpdated?.();
    };

    const handleBulkTransferUpdated = () => {
        table.resetRowSelection();
        onBulkStatusUpdated?.();
    };

    if (selectedCount === 0) {
        return null;
    }

    return (
        <>
            <div
                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-muted/40 px-3 py-2"
                data-tour="program-assistance-bulk-actions"
            >
                <p className="text-sm text-muted-foreground">
                    {selectedCount} assistance record
                    {selectedCount === 1 ? '' : 's'} selected
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="default"
                        className="h-8"
                        onClick={() => setBulkStatusOpen(true)}
                    >
                        <ListChecks className="size-4" />
                        Update status
                    </Button>
                    {canTransferAssistance ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="h-8"
                            onClick={() => setBulkTransferOpen(true)}
                        >
                            <ArrowRightLeft className="size-4" />
                            Transfer program
                        </Button>
                    ) : null}
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-8"
                        onClick={() => table.resetRowSelection()}
                    >
                        Clear selection
                    </Button>
                </div>
            </div>

            <AssistanceBulkStatusDrawer
                open={bulkStatusOpen}
                onOpenChange={setBulkStatusOpen}
                assistanceIds={selectedAssistanceIds}
                departmentSlug={departmentSlug}
                programId={programId}
                programName={programName}
                requestSubStatusOptions={requestSubStatusOptions}
                onUpdated={handleBulkStatusUpdated}
            />

            {canTransferAssistance ? (
                <AssistanceBulkTransferDrawer
                    open={bulkTransferOpen}
                    onOpenChange={setBulkTransferOpen}
                    assistanceIds={selectedAssistanceIds}
                    departmentSlug={departmentSlug}
                    programId={programId}
                    programName={programName}
                    transferProgramOptions={transferProgramOptions}
                    onTransferred={handleBulkTransferUpdated}
                />
            ) : null}
        </>
    );
}
