import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import BeamAccountLayout from '@/layouts/beam-account-layout';
import MainframeHost from '@/layouts/beam-ux/mainframe-host';
import OsLayout from '@/layouts/os-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
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
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
