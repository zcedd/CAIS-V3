import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { lookup as trackLookup } from '@/routes/public/track';

function queryValue(url: string, key: string): string {
    const query = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';

    return new URLSearchParams(query).get(key) ?? '';
}

export default function PublicTrackIndex() {
    const { url } = usePage();
    const caisNumber = queryValue(url, 'cais_number');

    return (
        <>
            <Head title="Track a request" />
            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Track a request
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Enter the CAIS number from your confirmation and your
                        last name.
                    </p>
                </div>
                <Form
                    {...trackLookup.form()}
                    className="flex flex-col gap-4"
                    disableWhileProcessing
                >
                    {({ errors, processing }) => (
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="cais_number">
                                    CAIS number
                                </FieldLabel>
                                <Input
                                    id="cais_number"
                                    name="cais_number"
                                    placeholder="PRO-2026-0001"
                                    defaultValue={caisNumber}
                                    key={caisNumber}
                                    aria-invalid={
                                        errors.cais_number ? true : undefined
                                    }
                                />
                                <InputError message={errors.cais_number} />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="last_name">
                                    Last name
                                </FieldLabel>
                                <Input
                                    id="last_name"
                                    name="last_name"
                                    aria-invalid={
                                        errors.last_name ? true : undefined
                                    }
                                />
                                <InputError message={errors.last_name} />
                            </Field>
                            <Field>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Looking up...'
                                        : 'Track request'}
                                </Button>
                            </Field>
                        </FieldGroup>
                    )}
                </Form>
            </div>
        </>
    );
}
