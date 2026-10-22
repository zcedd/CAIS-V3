import { Head, setLayoutProps } from '@inertiajs/react';
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
} from '@/routes/admin/unit-measurements';

export default function UnitMeasurementsIndex({
    units,
}: {
    units: LookupRow[];
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Unit measurements', href: index.url() }],
        });
    }, []);

    return (
        <>
            <Head title="Unit measurements" />
            <LookupManager
                title="unit"
                description="Units used when an item is counted or paid. The php unit stays, because cash items depend on it."
                rows={units}
                storeUrl={store.url()}
                updateUrl={(id) => update.url(id)}
                destroyUrl={(id) => destroy.url(id)}
            />
        </>
    );
}
