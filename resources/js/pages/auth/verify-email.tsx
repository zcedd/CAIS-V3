import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Email verification" />

            <Form {...send.form()} className="text-center">
                {({ processing }) => (
                    <FieldGroup>
                        {status === 'verification-link-sent' ? (
                            <div className="text-center text-sm font-medium text-green-600">
                                A new verification link has been sent to the
                                email address you provided during registration.
                            </div>
                        ) : null}

                        <Field>
                            <Button disabled={processing} variant="secondary">
                                {processing && <Spinner />}
                                Resend verification email
                            </Button>
                        </Field>

                        <TextLink href={logout()} className="text-sm">
                            Log out
                        </TextLink>
                    </FieldGroup>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verify email',
    description: 'Click the link we emailed you to verify your address.',
};
