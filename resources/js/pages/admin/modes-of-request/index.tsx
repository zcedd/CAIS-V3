import { Head, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    LookupManager,
    type LookupRow,
} from '@/components/admin/lookup-manager';
import { index, store, update, destroy } from '@/routes/admin/modes-of-request';

export default function ModesOfRequestIndex({ modes }: { modes: LookupRow[] }) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Modes of request', href: index.url() },
            ],
        });
    }, []);

    return (
        <>
            <Head title="Modes of request" />
            <LookupManager
                title="mode of request"
                description="How a request arrives, such as a letter, a call, or a walk-in."
                rows={modes}
                storeUrl={store.url()}
                updateUrl={(id) => update.url(id)}
                destroyUrl={(id) => destroy.url(id)}
            />
        </>
    );
}
