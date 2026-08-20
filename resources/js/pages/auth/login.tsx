import { Form, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/passkey-verify';
/* @end-chisel-passkeys */

type DemoAccount = { key: string; label: string; url: string };

type Props = {
    status?: string;
    canResetPassword: boolean;
    // Controller-provided (FortifyServiceProvider → the OOTB beam-accounts login-as affordance). Empty in
    // production / when demo is off, so the block below doesn't render. Each `url` is a signed login-as link.
    demoAccounts?: DemoAccount[];
};

// theme-entries-and-authoring STR-03: a sealed island (editor/registry.tsx), no longer the top-level
// Inertia page — these are FortifyServiceProvider's per-request props, still flowing exactly as
// before, read via usePage() instead of received as direct component props.
export default function Login() {
    const {
        status,
        canResetPassword,
        demoAccounts = [],
    } = usePage<Props>().props;

    return (
        <>
            {/* @chisel-passkeys */}
            <PasskeyVerify />
            {/* @end-chisel-passkeys */}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot your password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        {/* @chisel-registration */}
                        <div className="text-center text-sm text-muted-foreground">
                            Don't have an account?{' '}
                            <TextLink href={register()} tabIndex={5}>
                                Sign up
                            </TextLink>
                        </div>
                        {/* @end-chisel-registration */}
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {demoAccounts.length > 0 && (
                <div className="mt-2 flex flex-col gap-3">
                    <div className="relative text-center text-xs text-muted-foreground uppercase">
                        <span className="relative z-10 bg-background px-2">
                            Or try a demo account
                        </span>
                        <span
                            className="absolute inset-x-0 top-1/2 border-t"
                            aria-hidden
                        />
                    </div>
                    <div className="grid gap-2">
                        {demoAccounts.map((account) => (
                            // A signed OOTB login-as link (beam-accounts `account/login-as/{subject}`); a full
                            // GET so the server signs you in and redirects. Guarded server-side by Demo::enabled().
                            <a
                                key={account.key}
                                href={account.url}
                                className="w-full"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full"
                                >
                                    Sign in as {account.label}
                                </Button>
                            </a>
                        ))}
                    </div>
                    <p className="text-center text-xs text-muted-foreground">
                        Dev only — hidden in production.
                    </p>
                </div>
            )}
        </>
    );
}
