'use client';

import { Link, router } from '@inertiajs/react';
import type { Row } from '@tanstack/react-table';
import {
    Check,
    Copy,
    Edit,
    Eye,
    MoreHorizontal,
    Trash,
    UserRound,
    ArrowRightLeft,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AssistanceEditDrawer } from '@/components/user/programs/assistance-edit-drawer';
import { AssistanceStatusDrawer } from '@/components/user/programs/assistance-status-drawer';
import { useCan } from '@/hooks/use-can';
import { EMPTY_CELL } from '@/lib/empty-cell';
import { Permission } from '@/lib/permissions';
import type { UserProgramAssistanceRow } from '@/pages/user/programs/assistance-columns';
import type {
    AssistanceModeOption,
    AssistanceProgramItemOption,
    AssistanceRequestSubStatusOption,
    AssistanceTransferProgramOption,
} from '@/pages/user/programs/assistance-toolbar';
import { AssistanceTransferDrawer } from '@/pages/user/programs/assistance-transfer-drawer';
import { show as assistanceShow } from '@/routes/user/assistances';
import { show as beneficiaryShow } from '@/routes/user/beneficiaries';
import { destroy as destroyProgramAssistance } from '@/routes/user/programs/assistances';
import type { ProgramDocumentRequirementInput } from '@/types/document';
import type { ProgramFieldOption } from '@/types/program-field';

interface AssistanceDataTableRowActionsProps {
    row: Row<UserProgramAssistanceRow>;
    departmentSlug: string;
    programId: number;
    programName: string;
    isOrganization: boolean;
    distributionMode?: 'individual' | 'batch' | 'both' | null;
    modeOfRequestOptions: AssistanceModeOption[];
    programItems: AssistanceProgramItemOption[];
    programFields: ProgramFieldOption[];
    requestSubStatusOptions: AssistanceRequestSubStatusOption[];
    transferProgramOptions: AssistanceTransferProgramOption[];
    canTransferAssistance: boolean;
    documentRequirements?: ProgramDocumentRequirementInput[];
    onAssistanceUpdated?: () => void;
}

export function AssistanceDataTableRowActions({
    row,
    departmentSlug,
    programId,
    programName,
    isOrganization,
    distributionMode = 'batch',
    modeOfRequestOptions,
    programItems,
    programFields,
    requestSubStatusOptions,
    transferProgramOptions,
    canTransferAssistance,
    documentRequirements = [],
    onAssistanceUpdated,
}: AssistanceDataTableRowActionsProps) {
    const record = row.original;
    const { can } = useCan();
    const canView = can(Permission.AssistanceView);
    const canViewBeneficiary = can(Permission.BeneficiaryView);
    const canEdit = can(Permission.AssistanceUpdate);
    const canDelete = can(Permission.AssistanceDelete);
    const canAdvance = record.can_advance === true;
    const showTransfer = canTransferAssistance && canEdit;
    const canCopyNumber = record.cais_number !== EMPTY_CELL;
    const canCopyName = record.beneficiary_name !== EMPTY_CELL;
    const hasMenuAction =
        canView ||
        (canViewBeneficiary && Boolean(record.beneficiary_id)) ||
        canEdit ||
        canAdvance ||
        showTransfer ||
        canCopyNumber ||
        canCopyName ||
        canDelete;
    const [editOpen, setEditOpen] = useState(false);
    const [statusOpen, setStatusOpen] = useState(false);
    const [transferOpen, setTransferOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        router.delete(
            destroyProgramAssistance.url({
                department: departmentSlug,
                program: programId,
                assistance: record.id,
            }),
            {
                preserveScroll: true,
                onStart: () => setIsDeleting(true),
                onFinish: () => setIsDeleting(false),
                onSuccess: () => {
                    setDeleteOpen(false);
                    toast.success('Assistance deleted successfully.');
                    onAssistanceUpdated?.();
                },
            },
        );
    };

    const deleteTargetLabel =
        record.beneficiary_name !== EMPTY_CELL
            ? record.beneficiary_name
            : record.cais_number !== EMPTY_CELL
              ? record.cais_number
              : 'this assistance';

    if (!hasMenuAction) {
        return null;
    }

    const showCopy = canCopyNumber || canCopyName;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        className="flex size-8 p-0 data-[state=open]:bg-muted"
                    >
                        <MoreHorizontal className="h-4 w-4" />
                        <span className="sr-only">Open menu</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-[180px]">
                    {canView ? (
                        <DropdownMenuItem asChild>
                            <Link
                                href={assistanceShow.url({
                                    department: departmentSlug,
                                    program: programId,
                                    assistance: record.id,
                                })}
                            >
                                <Eye className="mr-2 h-4 w-4" />
                                View profile
                            </Link>
                        </DropdownMenuItem>
                    ) : null}
                    {canViewBeneficiary && record.beneficiary_id ? (
                        <DropdownMenuItem asChild>
                            <Link
                                href={beneficiaryShow.url({
                                    department: departmentSlug,
                                    beneficiary: record.beneficiary_id,
                                })}
                            >
                                <UserRound className="mr-2 h-4 w-4" />
                                View beneficiary profile
                            </Link>
                        </DropdownMenuItem>
                    ) : null}
                    {canEdit ? (
                        <DropdownMenuItem onSelect={() => setEditOpen(true)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Assistance
                        </DropdownMenuItem>
                    ) : null}
                    {canAdvance ? (
                        <DropdownMenuItem onSelect={() => setStatusOpen(true)}>
                            <Check className="mr-2 h-4 w-4" />
                            Update Status
                        </DropdownMenuItem>
                    ) : null}
                    {showTransfer ? (
                        <DropdownMenuItem
                            onSelect={() => setTransferOpen(true)}
                        >
                            <ArrowRightLeft className="mr-2 h-4 w-4" />
                            Transfer program
                        </DropdownMenuItem>
                    ) : null}
                    {showCopy ? <DropdownMenuSeparator /> : null}
                    {canCopyNumber ? (
                        <DropdownMenuItem
                            onClick={() => {
                                void navigator.clipboard.writeText(
                                    record.cais_number,
                                );
                            }}
                        >
                            <Copy className="mr-2 h-4 w-4" />
                            Copy CAIS number
                        </DropdownMenuItem>
                    ) : null}
                    {canCopyName ? (
                        <DropdownMenuItem
                            onClick={() => {
                                void navigator.clipboard.writeText(
                                    record.beneficiary_name,
                                );
                            }}
                        >
                            <Copy className="mr-2 h-4 w-4" />
                            Copy name
                        </DropdownMenuItem>
                    ) : null}
                    {canDelete ? <DropdownMenuSeparator /> : null}
                    {canDelete ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={(event) => {
                                event.preventDefault();
                                setDeleteOpen(true);
                            }}
                        >
                            <Trash className="mr-2 h-4 w-4" />
                            Delete Assistance
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            <AssistanceEditDrawer
                open={editOpen}
                onOpenChange={setEditOpen}
                assistanceId={record.id}
                departmentSlug={departmentSlug}
                programId={programId}
                programName={programName}
                isOrganization={isOrganization}
                modeOfRequestOptions={modeOfRequestOptions}
                programItems={programItems}
                programFields={programFields}
                onUpdated={onAssistanceUpdated}
            />

            <AssistanceStatusDrawer
                open={statusOpen}
                onOpenChange={setStatusOpen}
                assistanceId={record.id}
                departmentSlug={departmentSlug}
                programId={programId}
                programName={programName}
                beneficiaryName={record.beneficiary_name}
                currentSubStatusId={record.request_sub_status_id}
                currentRecordedAt={record.request_sub_status_recorded_at}
                requestSubStatusOptions={requestSubStatusOptions}
                assistanceItems={record.items}
                programItems={programItems}
                slaState={record.sla_state}
                canAdvance={canAdvance}
                allowedRequestStatusIds={record.allowed_request_status_ids}
                documentRequirements={documentRequirements}
                distributionMode={distributionMode}
                onUpdated={onAssistanceUpdated}
            />

            {showTransfer ? (
                <AssistanceTransferDrawer
                    open={transferOpen}
                    onOpenChange={setTransferOpen}
                    assistanceId={record.id}
                    departmentSlug={departmentSlug}
                    programId={programId}
                    programName={programName}
                    beneficiaryName={record.beneficiary_name}
                    beneficiaryId={record.beneficiary_id}
                    itemDetails={record.items.map((item) => ({
                        item_id: item.item_id,
                        quantity: item.quantity ?? 1,
                    }))}
                    transferProgramOptions={transferProgramOptions}
                    onTransferred={onAssistanceUpdated}
                />
            ) : null}

            <Dialog
                open={deleteOpen}
                onOpenChange={(open) => {
                    if (!isDeleting) {
                        setDeleteOpen(open);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete assistance?</DialogTitle>
                        <DialogDescription>
                            This will remove the assistance record for{' '}
                            <span className="font-medium text-foreground">
                                {deleteTargetLabel}
                            </span>
                            . This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" disabled={isDeleting}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={isDeleting}
                            onClick={handleDelete}
                        >
                            Delete assistance
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
