import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
};

export default function Login({
    status,
    canResetPassword,
    canRegister,
}: Props) {
    const { name } = usePage().props;

    setLayoutProps({
        title: 'Welcome back',
        description: `Login to your ${name} account`,
    });

    return (
        <>
            <Head title="Log in" />
            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <FieldGroup>
                        {status ? (
                            <div className="text-center text-sm font-medium text-green-600">
                                {status}
                            </div>
                        ) : null}

                        <Field>
                            <FieldLabel htmlFor="email">Email</FieldLabel>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="m@example.com"
                            />
                            <InputError message={errors.email} />
                        </Field>

                        <Field>
                            <div className="flex items-center">
                                <FieldLabel htmlFor="password">
                                    Password
                                </FieldLabel>
                                {canResetPassword ? (
                                    <TextLink
                                        href={request()}
                                        className="ml-auto text-sm underline-offset-2 hover:underline"
                                        tabIndex={5}
                                    >
                                        Forgot your password?
                                    </TextLink>
                                ) : null}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                            />
                            <InputError message={errors.password} />
                        </Field>

                        <Field orientation="horizontal">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            <FieldLabel htmlFor="remember">
                                Remember me
                            </FieldLabel>
                        </Field>

                        <Field>
                            <Button
                                type="submit"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Login
                            </Button>
                        </Field>

                        {canRegister ? (
                            <FieldDescription className="text-center">
                                Don&apos;t have an account?{' '}
                                <TextLink href={register()} tabIndex={6}>
                                    Sign up
                                </TextLink>
                            </FieldDescription>
                        ) : null}
                    </FieldGroup>
                )}
            </Form>
        </>
    );
}

Login.layout = ({ name }: { name: string }) => ({
    title: 'Welcome back',
    description: `Login to your ${name} account`,
});
