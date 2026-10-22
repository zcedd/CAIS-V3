import { Head, setLayoutProps } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useEffect } from 'react';
import {
    LookupManager,
    type LookupRow,
} from '@/components/admin/lookup-manager';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/admin/document-types';

const documentTypeColumns: ColumnDef<LookupRow>[] = [
    {
        accessorKey: 'slug',
        header: 'Slug',
    },
];

export default function DocumentTypesIndex({
    document_types,
}: {
    document_types: LookupRow[];
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Document types', href: index.url() }],
        });
    }, []);

    return (
        <>
            <Head title="Document types" />
            <LookupManager
                title="document type"
                description="Types staff can require on a program or attach to a request. System types keep their slug."
                rows={document_types}
                extraColumns={documentTypeColumns}
                storeUrl={store.url()}
                updateUrl={(id) => update.url(id)}
                destroyUrl={(id) => destroy.url(id)}
            />
        </>
    );
}
