'use client';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    destroy as destroyDocument,
    show as showDocument,
    store as storeDocument,
} from '@/routes/user/assistances/documents';
import type {
    AssistanceDocumentFile,
    AssistanceDocumentsPayload,
    DocumentTypeOption,
} from '@/types/document';
import { Form, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Circle,
    FileText,
    ImageIcon,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function DocumentFileRow({
    document,
    departmentSlug,
    programId,
    assistanceId,
}: {
    document: AssistanceDocumentFile;
    departmentSlug: string;
    programId: number;
    assistanceId: number;
}) {
    const href = showDocument.url({
        department: departmentSlug,
        program: programId,
        assistance: assistanceId,
        document: document.id,
    });

    const remove = () => {
        if (!window.confirm(`Remove ${document.original_name}?`)) {
            return;
        }

        router.delete(
            destroyDocument.url({
                department: departmentSlug,
                program: programId,
                assistance: assistanceId,
                document: document.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Document removed successfully.'),
                onError: () => toast.error('Could not remove the document.'),
            },
        );
    };

    return (
        <li className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border p-3">
            <div className="min-w-0 space-y-1">
                <a
                    href={href}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                >
                    {document.is_image ? (
                        <ImageIcon className="size-3.5" aria-hidden />
                    ) : (
                        <FileText className="size-3.5" aria-hidden />
                    )}
                    {document.original_name}
                </a>
                <p className="text-xs text-muted-foreground">
                    {formatFileSize(document.size)}
                    {document.uploaded_by_name
                        ? ` · ${document.uploaded_by_name}`
                        : ''}
                </p>
                {document.notes?.trim() ? (
                    <p className="text-xs text-muted-foreground">
                        {document.notes}
                    </p>
                ) : null}
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 text-muted-foreground"
                onClick={remove}
                aria-label={`Remove ${document.original_name}`}
            >
                <Trash2 className="size-4" />
            </Button>
        </li>
    );
}

export function AssistanceDocumentsSection({
    departmentSlug,
    programId,
    assistanceId,
    documents,
    documentTypes,
}: {
    departmentSlug: string;
    programId: number;
    assistanceId: number;
    documents: AssistanceDocumentsPayload;
    documentTypes: DocumentTypeOption[];
}) {
    const [documentTypeId, setDocumentTypeId] = useState(
        documentTypes[0] ? String(documentTypes[0].id) : '',
    );

    const missing = [
        ...documents.missing_for_verified.map((name) => ({
            name,
            stage: 'Verified',
        })),
        ...documents.missing_for_delivered
            .filter((name) => !documents.missing_for_verified.includes(name))
            .map((name) => ({ name, stage: 'Delivered' })),
    ];

    return (
        <section
            data-tour="assistance-documents"
            className="rounded-xl border border-border bg-card"
        >
            <div className="flex flex-col gap-4 p-4">
                <div className="space-y-1">
                    <h2 className="text-[15px] font-semibold tracking-tight">
                        Documents and proof
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        IDs, indigency certificates, delivery photos, and signed
                        acknowledgments for this request
                    </p>
                </div>

                {missing.length > 0 ? (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        Missing required documents:{' '}
                        {missing
                            .map((item) => `${item.name} (before ${item.stage})`)
                            .join(', ')}
                        .
                    </div>
                ) : null}

                {documents.checklist.length > 0 ? (
                    <ul className="space-y-4">
                        {documents.checklist.map((item) => (
                            <li key={item.id} className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    {item.is_complete ? (
                                        <CheckCircle2
                                            className="size-4 text-emerald-600"
                                            aria-hidden
                                        />
                                    ) : (
                                        <Circle
                                            className="size-4 text-muted-foreground"
                                            aria-hidden
                                        />
                                    )}
                                    <p className="text-sm font-medium">
                                        {item.document_type_name}
                                    </p>
                                    {item.is_required ? (
                                        <Badge variant="outline">
                                            Required before{' '}
                                            {item.required_before === 'verified'
                                                ? 'Verified'
                                                : 'Delivered'}
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            Optional
                                        </Badge>
                                    )}
                                </div>
                                {item.documents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground/70 italic">
                                        No file attached yet
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {item.documents.map((document) => (
                                            <DocumentFileRow
                                                key={document.id}
                                                document={document}
                                                departmentSlug={departmentSlug}
                                                programId={programId}
                                                assistanceId={assistanceId}
                                            />
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        This program has no checklist. You can still attach
                        files below.
                    </p>
                )}

                {documents.additional_documents.length > 0 ? (
                    <div className="space-y-2">
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Additional files
                        </p>
                        <ul className="space-y-2">
                            {documents.additional_documents.map((document) => (
                                <DocumentFileRow
                                    key={document.id}
                                    document={document}
                                    departmentSlug={departmentSlug}
                                    programId={programId}
                                    assistanceId={assistanceId}
                                />
                            ))}
                        </ul>
                    </div>
                ) : null}

                <Form
                    {...storeDocument.form({
                        department: departmentSlug,
                        program: programId,
                        assistance: assistanceId,
                    })}
                    encType="multipart/form-data"
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    transform={(data) => ({
                        ...data,
                        document_type_id: Number(documentTypeId),
                    })}
                    onSuccess={() =>
                        toast.success('Document uploaded successfully.')
                    }
                    onError={() =>
                        toast.error(
                            'Could not upload the document. Check the file type and size.',
                        )
                    }
                    className="space-y-3 rounded-xl border border-dashed p-3"
                >
                    {({ errors, processing, progress }) => (
                        <>
                            <div className="flex items-center gap-2">
                                <Upload className="size-4 text-muted-foreground" />
                                <p className="text-sm font-medium">
                                    Upload a document
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="assistance-document-type">
                                        Type
                                    </Label>
                                    <Select
                                        value={documentTypeId}
                                        onValueChange={setDocumentTypeId}
                                    >
                                        <SelectTrigger
                                            id="assistance-document-type"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {documentTypes.map((type) => (
                                                <SelectItem
                                                    key={type.id}
                                                    value={String(type.id)}
                                                >
                                                    {type.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.document_type_id}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="assistance-document-file">
                                        File
                                    </Label>
                                    <Input
                                        id="assistance-document-file"
                                        name="file"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                                    />
                                    <InputError message={errors.file} />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="assistance-document-notes">
                                    Notes
                                </Label>
                                <Textarea
                                    id="assistance-document-notes"
                                    name="notes"
                                    rows={2}
                                    placeholder="Optional notes about this file"
                                />
                                <InputError message={errors.notes} />
                            </div>
                            {progress ? (
                                <p
                                    className={cn(
                                        'text-xs tabular-nums text-muted-foreground',
                                    )}
                                >
                                    Uploading {progress.percentage}%
                                </p>
                            ) : null}
                            <Button
                                type="submit"
                                disabled={processing || documentTypeId === ''}
                            >
                                {processing ? 'Uploading...' : 'Upload'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </section>
    );
}
