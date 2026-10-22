import { Head, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    LookupManager,
    type LookupRow,
} from '@/components/admin/lookup-manager';
import { destroy, index, store, update } from '@/routes/admin/sectors';

export default function SectorsIndex({ sectors }: { sectors: LookupRow[] }) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Sectors', href: index.url() }],
        });
    }, []);

    return (
        <>
            <Head title="Sectors" />
            <LookupManager
                title="sector"
                description="Sectors a department can be grouped under."
                rows={sectors}
                storeUrl={store.url()}
                updateUrl={(id) => update.url(id)}
                destroyUrl={(id) => destroy.url(id)}
            />
        </>
    );
}
