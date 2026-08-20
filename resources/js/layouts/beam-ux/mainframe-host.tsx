import { usePage } from '@inertiajs/react';
import { createMainframeHost, useBeamUxEntry as useBeamUxEntryBase } from '@splicewire/beam-mainframe';
import type { HostEntryBody, RibbonRender } from '@splicewire/beam-mainframe';
import { lazy, Suspense } from 'react';
import { bodyClient } from '@/editor/transport';

/**
 * The beam-ux **Mainframe host** — the operator front-end's client, a THIN CONFIG over the promoted
 * `createMainframeHost` factory (`@splicewire/beam-mainframe`). The factory owns the generic wiring
 * (registries, mode state, the `?beam_entry` override, the kind-aware `main` fork, the author `can` gate,
 * entry-body load, `useBeamUxEntry`); this host supplies only the host-local pieces: the component→entry
 * map, the ribbon chrome, and the editor/inspector renderers (which mount the promoted visual editor).
 *
 * Two modes (child-swap-under-a-stable-host):
 *   - `domain` (read) — the page renders as the Mainframe `main` payload. `readMode: 'page'` means the
 *     real Inertia page renders unchanged (it carries its own chrome), so read mode is a no-op swap.
 *   - `window` (author WYSIWYG) — for a signed-in author (`ux.author`), `main` swaps to the in-place
 *     visual editor (@/editor).
 */

// The authoring renderers stay host-local (heavy, author-only), lazy-loaded only when authoring.
const VisualEditorMount = lazy(() => import('@/editor/mount').then((m) => ({ default: m.VisualEditorMount })));

/**
 * The structured chrome body of a `page` entry — what drives a reshelled page's heading/intro. A page
 * that opts in reads its own chrome FROM the entry, not a hardcoded constant.
 */
export type BeamUxPageBody = {
    heading?: string;
    intent?: string;
    content?: string;
};

/** Body-typed alias over the factory's generic `useBeamUxEntry`; returns `{slug, schema, body}` or null. */
export function useBeamUxEntry() {
    return useBeamUxEntryBase<BeamUxPageBody>();
}

/**
 * Inertia component name → beam-ux `page` entry slug. Explicit because the entry slug is the DOMAIN key,
 * which is not always the slash-swapped component path. A component with no mapping falls back to the
 * slash-swap.
 */
const COMPONENT_TO_ENTRY: Record<string, string> = {
    'site/home': 'home',
    // /dashboard renders the `account/home` component, but the seeded entry (DatabaseSeeder's
    // splicewire:beam:ux:seed-nav) is named `dashboard` (the route's own name) - the slash-swap
    // fallback would look for a nonexistent `account-home` entry instead.
    'account/home': 'dashboard',
};

// Components that embed `PageEditor` (`@splicewire/beam-ux/canvas`) directly and already listen to
// the SAME `beam-ux:mode`/`edit`/`exit` broadcast this factory uses, swapping their OWN internal
// canvas in place - `site/home.tsx` is the one page here that does. Overlaying the generic
// renderEditor/renderInspector on TOP of one of these double-mounts a second, competing editor
// instance for the same slug; since it loads independently and can render blank/stale before the
// real (working) PageEditor underneath, the visible symptom is "the editor works, then goes blank".
// Read via a plain module variable (not a prop threaded through renderEditor/renderInspector,
// which only ever receive `{slug}`) set during `usePageContext`'s own render, same bridge pattern
// rushing/audiostud's `os/authoring-context.ts` already uses for an equivalent cross-cutting need -
// calling `usePage()` again inside renderEditor/renderInspector themselves would violate the rules
// of hooks (they're plain functions, not components).
const SELF_MANAGED_COMPONENTS = new Set(['site/home']);
let currentComponent = '';

// No on-page ribbon by default — authoring is driven from the operator/OS chrome. `() => null` keeps the
// browsing AND editing surfaces free of beam-ux frame chrome; the editor itself is the only author surface.
const ribbon: RibbonRender = () => null;

/**
 * The entry-body transport — routes through the shared host body client (the SAME transport the editor
 * saves through), so there's one load path. `null` on any miss so the page falls back to its own copy.
 */
async function loadEntryBody(slug: string): Promise<HostEntryBody | null> {
    try {
        return (await bodyClient.loadBody(slug)) as HostEntryBody;
    } catch {
        return null;
    }
}

export default createMainframeHost({
    componentToEntry: COMPONENT_TO_ENTRY,
    // Every starter route page renders its OWN body (its layout chrome + scoped CSS). So read mode must
    // NOT swap the page for the bare entry body. Author (window) mode still opens the in-place editor.
    readMode: 'page',
    usePageContext: () => {
        const page = usePage<{ auth: { canAuthorUx?: boolean }; slug?: string }>();
        currentComponent = page.component;

        return {
            component: page.component,
            canAuthor: page.props.auth?.canAuthorUx === true,
            slug: typeof page.props.slug === 'string' && page.props.slug !== '' ? page.props.slug : null,
        };
    },
    loadEntryBody,
    ribbon,
    renderEditor: ({ slug }: { slug: string }) =>
        SELF_MANAGED_COMPONENTS.has(currentComponent) ? null : (
            <Suspense fallback={<div className="p-6 text-sm text-slate-500">Loading editor…</div>}>
                <VisualEditorMount slug={slug} />
            </Suspense>
        ),
    // readMode: 'page' means the read fork renders the real page, never this — so it's a no-op. Kept only
    // to satisfy the factory's renderer contract.
    renderRead: () => null,
    renderInspector: ({ slug }: { slug: string }) =>
        SELF_MANAGED_COMPONENTS.has(currentComponent) ? null : (
            <Suspense fallback={null}>
                <VisualEditorMount slug={slug} />
            </Suspense>
        ),
});
