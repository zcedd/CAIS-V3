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
import { Row } from '@tanstack/react-table';
import { Copy, MoreHorizontal } from 'lucide-react';

interface AssistanceDataTableRowActionsProps {
    row: Row<UserProgramAssistanceRow>;
}

export function AssistanceDataTableRowActions({
    row,
}: AssistanceDataTableRowActionsProps) {
    const record = row.original;

    return (
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
                <DropdownMenuItem
                    onClick={() => {
                        void navigator.clipboard.writeText(record.cais_number);
                    }}
                >
                    <Copy className="mr-2 h-4 w-4" />
                    Copy CAIS number
                </DropdownMenuItem>
                {record.remark?.trim() ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => {
                                void navigator.clipboard.writeText(
                                    record.remark ?? '',
                                );
                            }}
                        >
                            <Copy className="mr-2 h-4 w-4" />
                            Copy remark
                        </DropdownMenuItem>
                    </>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
