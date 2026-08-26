import { createInertiaApp, Link } from '@inertiajs/react';
import { configureEntryPage } from '@splicewire/beam-ux/docs';
import { beamUxPages } from '@splicewire/beam-ux/pages';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { ComponentType } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import BeamAccountLayout from '@/layouts/beam-account-layout';
import MainframeHost from '@/layouts/beam-ux/mainframe-host';
import OsLayout from '@/layouts/os-layout';
import SettingsLayout from '@/layouts/settings/layout';
import SiteLayout from '@/layouts/site-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * One client for the whole app. Any `@splicewire/beam-ux` surface that owns its own data logic uses
 * react-query per the package's rule — `<ManifestTable>`, which the seeded `/docs/mcp` page renders,
 * is the first one a fresh install hits. `useQuery` throws outside a provider and offers no supported
 * way to detect one, so this has to be mounted by the host; the package ships `<ManifestTableView>`
 * as the pure escape hatch for SSR and provider-less embeds, not as a substitute for this.
 */
const queryClient = new QueryClient();

/**
 * The host half of the PACKAGED entry page (ADR-0213 §3). `pages/site/entry.tsx` used to live here —
 * 84 lines, byte-identical in all three starters and independently grown to 262 and 285 on two real
 * hosts. The page now comes from `@splicewire/beam-ux/pages`, and everything only a host has arrives
 * through this one call, because an Inertia page's props come from the server and there is no other
 * channel from here into it.
 *
 * `wrap` is this site's chrome. Every public entry — the docs pages, a marketing page, a legal page —
 * renders inside `<SiteLayout>`, which is a fact about this HOST rather than about any one entry, so
 * it belongs here and not in an entry's `layout` column. An entry that wants the docs rail declares
 * `layout: DocsLayout` and gets it NESTED inside this.
 *
 * Putting a file back at `resources/js/pages/site/entry.tsx` overrides the packaged page outright —
 * the resolver below checks this host's own glob first. That is the whole override mechanism.
 */
configureEntryPage({
    linkComponent: Link,
    wrap: (node) => <SiteLayout>{node}</SiteLayout>,
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    /**
     * Own glob first, the package's page map second (ADR-0213 §3). Written out rather than left to
     * `@inertiajs/vite`'s injected resolver, because the injected one throws on a name it cannot find
     * in `./pages` and a package-contributed page is by definition not there.
     */
    resolve: async (name: string) => {
        const own = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');
        const local = own[`./pages/${name}.tsx`];

        if (local) {
            return (await local()).default;
        }

        const packaged = beamUxPages[name];

        if (packaged) {
            return (await packaged()).default as ComponentType;
        }

        throw new Error(`Page not found: ${name}`);
    },
    layout: (name) => {
        switch (true) {
            // The OS-shell desktop is fully self-chromed (menu bar + dock + windows) — no wrapping layout,
            // never (re-)wrapped by the persistent OsLayout overlay either (it mounts its OWN operator
            // chrome — see os/shell-config.tsx).
            case name === 'os':
                return null;
            // Site-realm pages carry their own <SiteLayout> internally (the OOTB site chrome). Wrapped in
            // MainframeHost so an author (`ux.author`) can edit the page in place; a reader falls through
            // to the self-chromed page (readMode: 'page' is a no-op swap). OsLayout OUTERMOST: an
            // `os.enter` principal gets the persistent operator dock overlay on top of the real page.
            case name.startsWith('site/'):
                return [OsLayout, MainframeHost];
            // The OPERATOR front-end realm — framed by the promoted <MainframeHost> (beam-mainframe).
            case name.startsWith('operator/'):
                return [OsLayout, MainframeHost];
            // Account-realm pages mount the OOTB <AccountShell> via BeamAccountLayout, MainframeHost
            // INNERMOST (wraps just the page content, inside the AccountShell chrome) so every account
            // page is editable too — matches rushing/audiostud's own layout switch, which includes
            // MainframeHost in every case but the null `/os` one.
            case name.startsWith('account/'):
                return [OsLayout, BeamAccountLayout, MainframeHost];
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [OsLayout, AppLayout, SettingsLayout, MainframeHost];
            default:
                return [OsLayout, AppLayout, MainframeHost];
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <QueryClientProvider client={queryClient}>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </QueryClientProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
