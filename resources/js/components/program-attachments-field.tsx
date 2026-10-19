import { useId } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ProgramAttachment = {
    id: number;
    original_name: string;
    size: number;
    url: string;
};

const ACCEPT =
    '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf';
const MAX_FILES = 10;

type ProgramAttachmentsFieldProps = {
    files: File[];
    onFilesChange: (files: File[]) => void;
    existing?: ProgramAttachment[];
    removedIds?: number[];
    onRemovedIdsChange?: (ids: number[]) => void;
    errors?: Record<string, string | undefined>;
};

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function ProgramAttachmentsField({
    files,
    onFilesChange,
    existing = [],
    removedIds = [],
    onRemovedIdsChange,
    errors = {},
}: ProgramAttachmentsFieldProps) {
    const inputId = useId();
    const keptExisting = existing.filter(
        (attachment) => !removedIds.includes(attachment.id),
    );
    const attachmentError =
        errors.attachments ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('attachments.'),
        )?.[1];

    function addFiles(list: FileList | null): void {
        if (!list) {
            return;
        }

        const next = [...files];

        for (const file of Array.from(list)) {
            if (keptExisting.length + next.length >= MAX_FILES) {
                break;
            }

            next.push(file);
        }

        onFilesChange(next);
    }

    return (
        <div className="space-y-2">
            <Label htmlFor={inputId}>Attachments</Label>
            <Input
                id={inputId}
                type="file"
                accept={ACCEPT}
                multiple
                onChange={(event) => {
                    addFiles(event.target.files);
                    event.target.value = '';
                }}
            />
            <p className="text-sm text-muted-foreground">
                PDF or image files. Up to 10 files, 10 MB each.
            </p>
            {keptExisting.length > 0 || files.length > 0 ? (
                <ul className="space-y-1">
                    {keptExisting.map((attachment) => (
                        <li
                            key={attachment.id}
                            className="flex items-center justify-between gap-2 text-sm"
                        >
                            <span className="min-w-0 truncate">
                                {attachment.original_name}
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {formatFileSize(attachment.size)}
                                </span>
                            </span>
                            {onRemovedIdsChange ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        onRemovedIdsChange([
                                            ...removedIds,
                                            attachment.id,
                                        ])
                                    }
                                >
                                    Remove
                                </Button>
                            ) : null}
                        </li>
                    ))}
                    {files.map((file, index) => (
                        <li
                            key={`${file.name}-${file.size}-${index}`}
                            className="flex items-center justify-between gap-2 text-sm"
                        >
                            <span className="min-w-0 truncate">
                                {file.name}
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {formatFileSize(file.size)}
                                </span>
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    onFilesChange(
                                        files.filter(
                                            (_, fileIndex) =>
                                                fileIndex !== index,
                                        ),
                                    )
                                }
                            >
                                Remove
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : null}
            {removedIds.length > 0 && onRemovedIdsChange ? (
                <ul className="space-y-1">
                    {existing
                        .filter((attachment) =>
                            removedIds.includes(attachment.id),
                        )
                        .map((attachment) => (
                            <li
                                key={attachment.id}
                                className="flex items-center justify-between gap-2 text-sm text-muted-foreground"
                            >
                                <span className="min-w-0 truncate line-through">
                                    {attachment.original_name}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        onRemovedIdsChange(
                                            removedIds.filter(
                                                (id) => id !== attachment.id,
                                            ),
                                        )
                                    }
                                >
                                    Undo
                                </Button>
                            </li>
                        ))}
                </ul>
            ) : null}
            <InputError message={attachmentError} />
        </div>
    );
}

export function ProgramAttachmentList({
    attachments,
    showHeading = true,
}: {
    attachments?: ProgramAttachment[];
    showHeading?: boolean;
}) {
    if (!attachments || attachments.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            {showHeading ? (
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Attachments
                </p>
            ) : null}
            <ul className="space-y-1">
                {attachments.map((attachment) => (
                    <li key={attachment.id} className="text-sm">
                        <a
                            href={attachment.url}
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            {attachment.original_name}
                        </a>
                        <span className="text-muted-foreground">
                            {' '}
                            · {formatFileSize(attachment.size)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
