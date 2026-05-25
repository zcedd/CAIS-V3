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
import { show as assistanceShow } from '@/routes/user/assistances';
import { Link } from '@inertiajs/react';
import { Row } from '@tanstack/react-table';
import { Copy, Eye, MoreHorizontal } from 'lucide-react';

interface AssistanceDataTableRowActionsProps {
    row: Row<UserProgramAssistanceRow>;
    departmentSlug: string;
    programId: number;
}

export function AssistanceDataTableRowActions({
    row,
    departmentSlug,
    programId,
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
