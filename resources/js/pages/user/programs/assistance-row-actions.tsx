'use client';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { UserProgramAssistanceRow } from '@/pages/user/programs/assistance-columns';
import { AssistanceEditDrawer } from '@/pages/user/programs/assistance-edit-drawer';
import type {
    AssistanceModeOption,
    AssistanceProgramItemOption,
} from '@/pages/user/programs/assistance-toolbar';
import { show as assistanceShow } from '@/routes/user/assistances';
import { Link } from '@inertiajs/react';
import { Row } from '@tanstack/react-table';
import { Check, Copy, Edit, Eye, MoreHorizontal, Trash } from 'lucide-react';
import { useState } from 'react';

interface AssistanceDataTableRowActionsProps {
    row: Row<UserProgramAssistanceRow>;
    departmentSlug: string;
    programId: number;
    programName: string;
    isOrganization: boolean;
    modeOfRequestOptions: AssistanceModeOption[];
    programItems: AssistanceProgramItemOption[];
    onAssistanceUpdated?: () => void;
}

export function AssistanceDataTableRowActions({
    row,
    departmentSlug,
    programId,
    programName,
    isOrganization,
    modeOfRequestOptions,
    programItems,
    onAssistanceUpdated,
}: AssistanceDataTableRowActionsProps) {
    const record = row.original;
    const [editOpen, setEditOpen] = useState(false);

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
                    <DropdownMenuItem onSelect={() => setEditOpen(true)}>
                        <Edit className="mr-2 h-4 w-4" />
                        Edit Assistance
                    </DropdownMenuItem>
                    <DropdownMenuItem>
                        <Check className="mr-2 h-4 w-4" />
                        Update Status
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    {record.cais_number !== '—' ? (
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
                    {record.beneficiary_name !== '—' ? (
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
                    <DropdownMenuSeparator />
                    <DropdownMenuItem variant="destructive">
                        <Trash className="mr-2 h-4 w-4" />
                        Delete Assistance
                    </DropdownMenuItem>
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
                onUpdated={onAssistanceUpdated}
            />
        </>
    );
}
