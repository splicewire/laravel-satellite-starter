// Default body per entry slug — the starting content the editor + read view use when an entry has no
// saved body yet. The demo islands load as opaque islands (real components), so the editor opens WITH
// content (reorder / add / delete around them) and the read view renders the same tree. A save persists a
// real body that overrides this default. Host config only — the machinery lives in the packages.
//
// The body is a `JsonDoc` (a `JsonNode[]` — the promoted BlockDoc JSON projection). A single-root page
// body is `[root]`.
import type { JsonBlock, JsonDoc } from '@splicewire/beam-ux/blockdoc/json';

const island = (name: string): JsonBlock => ({
    kind: 'block',
    name,
    isComponent: true,
    props: [],
    children: [],
    dynamic: false,
});

const str = (name: string, value: string) => ({
    name,
    kind: 'string' as const,
    value,
});

// theme-entries-and-authoring STR-03: `[heading, description, island]` — mirrors
// `database/seeders/AuthPagesSeeder.php`'s `body()` shape exactly, so the client fallback (an
// unseeded/fresh-DB install) and the DB-seeded body stay interchangeable. `text-center` on the
// heading compensates for the lost `layouts/auth/auth-simple-layout.tsx` parent wrapper that used to
// center it; `text-xl font-medium`/`text-center text-sm text-muted-foreground` match that layout's own
// original `<h1>`/`<p>` classes.
const authPage = (
    heading: string,
    description: string,
    islandName: string,
): JsonDoc => [
    {
        kind: 'block',
        name: 'h1',
        isComponent: false,
        dynamic: false,
        props: [str('className', 'text-xl font-medium text-center')],
        children: [{ kind: 'text', value: heading }],
    },
    {
        kind: 'block',
        name: 'p',
        isComponent: false,
        dynamic: false,
        props: [str('className', 'text-center text-sm text-muted-foreground')],
        children: [{ kind: 'text', value: description }],
    },
    island(islandName),
];

export const DEFAULT_TREES: Record<string, JsonDoc> = {
    // The public site home — a demo hero island + a copy paragraph + a feature-row island.
    home: [
        {
            kind: 'block',
            name: 'div',
            isComponent: false,
            dynamic: false,
            props: [
                str('className', 'st-editable-home'),
                str('style', 'max-width:900px;margin:0 auto;padding:24px'),
            ],
            children: [
                island('DemoHero'),
                {
                    kind: 'block',
                    name: 'p',
                    isComponent: false,
                    dynamic: false,
                    props: [
                        str(
                            'style',
                            'font-size:16px;line-height:1.6;color:#475569;margin:28px 0',
                        ),
                    ],
                    children: [
                        {
                            kind: 'text',
                            value: 'This page is editable in place through the promoted @splicewire/beam-ux/canvas. Everything you see is one JsonDoc body — the same tree the read view renders and the editor edits.',
                        },
                    ],
                },
                island('DemoFeatureRow'),
            ],
        },
    ],

    // theme-entries-and-authoring STR-03: the 7 promoted auth pages — a heading + description (ordinary
    // editable text blocks, matching AuthPagesSeeder's DB-seeded shape exactly) followed by the sealed
    // island (the real Fortify-bound form). An author holding `ux.auth.author` can retext/reorder/add
    // content around the island via the visual editor; the form itself is never decomposed.
    // `two-factor-challenge` carries no heading/description — its title toggles on local session state
    // (authentication-code ⇄ recovery-code), not editorial copy; its own island sets it directly.
    login: authPage(
        'Log in to your account',
        'Enter your email and password below to log in',
        'AuthLogin',
    ),
    register: authPage(
        'Create an account',
        'Enter your details below to create your account',
        'AuthRegister',
    ),
    'forgot-password': authPage(
        'Forgot password',
        'Enter your email to receive a password reset link',
        'AuthForgotPassword',
    ),
    'reset-password': authPage(
        'Reset password',
        'Please enter your new password below',
        'AuthResetPassword',
    ),
    'confirm-password': authPage(
        'Confirm password',
        'This is a secure area of the application. Please confirm your password before continuing.',
        'AuthConfirmPassword',
    ),
    'two-factor-challenge': [island('AuthTwoFactorChallenge')],
    'verify-email': authPage(
        'Email verification',
        'Please verify your email address by clicking on the link we just emailed to you.',
        'AuthVerifyEmail',
    ),
};

export function defaultTreeFor(slug: string): JsonDoc | null {
    return DEFAULT_TREES[slug] ?? null;
}
