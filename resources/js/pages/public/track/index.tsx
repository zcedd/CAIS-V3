import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { lookup as trackLookup } from '@/routes/public/track';
import { Head, Form } from '@inertiajs/react';

export default function PublicTrackIndex() {
    return (
        <>
            <Head title="Track a request" />
            <div className="space-y-6">
                <div className="space-y-2">
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
                    className="space-y-4"
                    disableWhileProcessing
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="cais_number">CAIS number</Label>
                                <Input
                                    id="cais_number"
                                    name="cais_number"
                                    placeholder="PRO-2026-0001"
                                />
                                <InputError message={errors.cais_number} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="last_name">Last name</Label>
                                <Input id="last_name" name="last_name" />
                                <InputError message={errors.last_name} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Looking up...' : 'Track request'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
