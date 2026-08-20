import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PageEditor from '@/editor/page-editor';

/**
 * theme-entries-and-authoring STR-03: the ONE Inertia page every promoted auth route renders through —
 * `FortifyServiceProvider`'s 7 view closures all resolve here, differentiated by `slug` (which
 * `BeamUxEntry` the tree/body belongs to). The real Fortify-bound form (`Login`/`Register`/etc,
 * unchanged, still at `pages/auth/{slug}.tsx`) is a SEALED island inside the tree
 * (`editor/registry.tsx`) — position/delete-only in the visual editor, never decomposed. Everything
 * else in the tree is ordinary composable content an author holding `ux.auth.author` can edit.
 *
 * `name.startsWith('auth/')` still matches this page's name (`auth/entry`), so `app.tsx`'s layout
 * resolver still wraps it in the existing `<AuthLayout>` automatically — no new layout-selection case
 * needed.
 *
 * **The edit-mode trigger.** Every other promoted realm (`site/`, `operator/`) is wrapped in
 * `@splicewire/beam-mainframe`'s `MainframeHost`, whose OS-dock "Edit content" button dispatches
 * `beam-ux:edit` (MainframeHost translates that + `beam-ux:exit` into the `beam-ux:mode` event
 * `PageEditor`'s `useEditMode()` actually listens for). `AuthLayout` deliberately does NOT wrap in
 * MainframeHost (auth pages need to stay a clean, minimal, un-chromed flow, not gain a full OS dock) —
 * so there is no external dock to fire `beam-ux:edit` here. This component is its own minimal bridge:
 * an inline "Edit content" button (author-only) dispatches `beam-ux:mode` directly, and listens for
 * `PageEditor`'s own "Exit" button (`beam-ux:exit`) to leave edit mode — the same two events
 * MainframeHost bridges, just triggered locally instead of from an external dock.
 *
 * **Title/description are NOT pushed here for 6 of the 7 pages.** They're genuinely composable now —
 * ordinary `h1`/`p` blocks at the front of each entry's tree (`AuthPagesSeeder`), carrying the same
 * `className`s `layouts/auth/auth-simple-layout.tsx`'s own `<h1>`/`<p>` used to render them with, so
 * promoting them into the tree is visually a no-op — `AuthLayout`'s own title/description slot is left
 * blank (its defaults are already `''`). `two-factor-challenge` is the one exception: its title toggles
 * between "Authentication code"/"Recovery code" based on LOCAL SESSION STATE, not editorial copy, so its
 * tree is the sealed island alone and its own component keeps calling `setLayoutProps` itself.
 */
const HEAD_TITLES: Record<string, string> = {
    login: 'Log in',
    register: 'Register',
    'forgot-password': 'Forgot password',
    'reset-password': 'Reset password',
    'confirm-password': 'Confirm password',
    'two-factor-challenge': 'Two-factor authentication',
    'verify-email': 'Email verification',
};

function AuthEntryForSlug({ slug, body }: { slug: string; body?: unknown }) {
    const canAuthorUx =
        usePage<{ auth?: { canAuthorUx?: boolean } }>().props.auth
            ?.canAuthorUx ?? false;
    const [editing, setEditing] = useState(false);

    useEffect(() => {
        const exit = () => setEditing(false);
        window.addEventListener('beam-ux:exit', exit);

        return () => window.removeEventListener('beam-ux:exit', exit);
    }, []);

    useEffect(() => {
        window.dispatchEvent(
            new CustomEvent('beam-ux:mode', {
                detail: { mode: editing ? 'window' : 'domain' },
            }),
        );
    }, [editing]);

    return (
        <>
            <Head title={HEAD_TITLES[slug] ?? 'Account'} />
            {canAuthorUx && !editing && (
                <button
                    type="button"
                    onClick={() => setEditing(true)}
                    className="fixed top-3 right-3 z-40 rounded-lg border bg-background px-3 py-1.5 text-sm shadow-sm"
                >
                    Edit content
                </button>
            )}
            <PageEditor slug={slug} body={body} />
        </>
    );
}

export default function AuthEntry({
    slug,
    body,
}: {
    slug: string;
    body?: unknown;
}) {
    // key={slug}: every route renders this SAME Inertia page name, so a client-side (SPA) navigation
    // between two auth pages does NOT remount AuthEntry — Inertia just re-renders it with new props. A
    // `key` assigned to something a component RETURNS does not reset THAT component's own hooks (only
    // its children's) — so `editing` (owned by AuthEntryForSlug) and PageEditor's `doc` state (seeded
    // via `useState(initial)` ONCE at mount — package behavior, not host-patchable here) both need
    // AuthEntryForSlug itself to remount. Keying IT from the outside, here, is what actually does that
    // — React's standard "reset state via key" pattern.
    return <AuthEntryForSlug key={slug} slug={slug} body={body} />;
}
