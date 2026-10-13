import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Forgot password" />

            <Form {...email.form()}>
                {({ processing, errors }) => (
                    <FieldGroup>
                        {status ? (
                            <div className="text-center text-sm font-medium text-green-600">
                                {status}
                            </div>
                        ) : null}

                        <Field>
                            <FieldLabel htmlFor="email">
                                Email address
                            </FieldLabel>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="off"
                                autoFocus
                                placeholder="email@example.com"
                            />
                            <InputError message={errors.email} />
                        </Field>

                        <Field>
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                Email password reset link
                            </Button>
                        </Field>

                        <FieldDescription className="text-center">
                            <span>Or, return to </span>
                            <TextLink href={login()}>log in</TextLink>
                        </FieldDescription>
                    </FieldGroup>
                )}
            </Form>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Forgot password',
    description: 'Enter your email and we will send a reset link.',
};
