// beam-starter · OS-shell HOST config (frontend-surfaces.md, desktop/OS shell). HOST CONFIG only, not
// machinery: the realm→surface bindings (`SURFACE_MAP`), NEUTRAL `--shell-*` theme tokens, and the roster
// wiring. The generic desktop CHROME (dock / launcher / clock / window manager) is promoted to
// `@schemastud/mainframe/os` (`buildDesktopChrome`, realm-agnostic); the realm-aware roster builder
// (`buildAppsFromManifest`) is promoted to `@splicewire/beam-ux/shell`. This file imports both and
// supplies only what's host-specific.
//
// Topology (the tier boundary is load-bearing — no beam/realm type enters the schemastud chrome):
//  - the generic chrome lives realm-agnostic in schemastud; it reads a flat `DesktopApp[]`.
//  - beam-ux/shell computes that `DesktopApp[]` from the server realm manifest (gating + auto-surface).
//  - the host binds realm keys → real surfaces (`SURFACE_MAP`) + supplies the theme (CSS below).
//  - each app window is a NESTED Mainframe scope framing the REAL page component. The recursion.
import { Link, router, usePage } from '@inertiajs/react';
import '@schemastud/mainframe/os/shell.css';
import { MainframeOutlet, MainframeProvider, createMainframeRegistry, createSlotRegistry } from '@schemastud/mainframe';
import type { Mainframe, MainframeInjection } from '@schemastud/mainframe';
import type { PersistedWorkspace } from '@schemastud/mainframe/os';
import { buildAppsFromManifest, buildDesktopChrome, Clock } from '@splicewire/beam-ux/shell';
import type { DesktopApp, RealmManifestEntry, RealmSurfaceBinding } from '@splicewire/beam-ux/shell';
import { Component, Suspense, lazy } from 'react';
import type { ErrorInfo, ReactNode } from 'react';

// The REAL realm surfaces — their actual page components (WYSIWYG). Each mounted inside a window reads
// shared props (auth/accountShell) from the /os Inertia context; page-specific props fall back to designed
// defaults. NEVER a mock, NEVER an iframe.
//
// LAZY-imported (not static) on purpose: the per-page `@vite("pages/{component}.tsx")` directive resolves
// each page against the build manifest, so a page must stay its OWN chunk. A static import here would
// INLINE the page into the shell chunk (the "INEFFECTIVE_DYNAMIC_IMPORT" collision), dropping its manifest
// entry — so a DIRECT visit to `/operator` 500s ("not in Vite manifest") in a build. Lazy keeps each page a
// standalone entry, reachable both directly and framed in an OS window.
import BeamAccountLayout from '@/layouts/beam-account-layout';

const AccountHome = lazy(() => import('@/pages/account/home'));
const OperatorDashboard = lazy(() => import('@/pages/operator/dashboard'));
const SiteHome = lazy(() => import('@/pages/site/home'));

export type { RealmManifestEntry } from '@splicewire/beam-ux/shell';

/**
 * theme-entries-and-authoring ticket `str-01`: a THIRD, theme-driven `--shell-*` remap, layered
 * ADDITIVELY after {@see OS_SHELL_CSS} (same selector/specificity — CSS cascade means this wins by
 * source order, never by touching `OS_SHELL_CSS` itself). Reads `page.props.theme.shell` (the
 * `ThemeResolver` cascade — package default → central → tenant); renders nothing when the prop is
 * absent (a stale build / degrade path), leaving `OS_SHELL_CSS`'s own neutral defaults as the floor.
 */
function ThemeShellStyle() {
    const page = usePage<{ theme?: { shell?: ShellThemeTokens } }>();
    const shell = page.props.theme?.shell;

    if (!shell) {
        return null;
    }

    return <style dangerouslySetInnerHTML={{ __html: shellThemeCss(shell) }} />;
}

interface ShellThemeTokens {
    surface: string;
    surfaceRaised: string;
    fg: string;
    fgMuted: string;
    accent: string;
    edge: string;
    radius: string;
    shadow: string;
    font: string;
    fontMono: string;
}

function shellThemeCss(shell: ShellThemeTokens): string {
    return `.st-os-root [data-shell-root]{
  --shell-surface:${shell.surface};
  --shell-surface-raised:${shell.surfaceRaised};
  --shell-fg:${shell.fg};
  --shell-fg-muted:${shell.fgMuted};
  --shell-accent:${shell.accent};
  --shell-edge:${shell.edge};
  --shell-radius:${shell.radius};
  --shell-shadow:${shell.shadow};
  --shell-font:${shell.font};
  --shell-font-mono:${shell.fontMono};
}`;
}

// ── NEUTRAL CHROME theme (host-local `--shell-*` remap) ─────────────────────────────────────────────
// A plain slate/neutral set (NOT a brand palette). A second host restyles ONLY these `--shell-*` tokens.
export const OS_SHELL_CSS = `
.st-os-root{position:fixed;inset:0;overflow:hidden}
.st-os-root [data-shell-root]{
  --shell-surface:#0f172a;
  --shell-surface-raised:rgba(15,23,42,.88);
  --shell-fg:#f1f5f9;
  --shell-fg-muted:#94a3b8;
  --shell-accent:#3b82f6;
  --shell-edge:#334155;
  --shell-radius:14px;
  --shell-shadow:0 40px 90px -40px rgba(0,0,0,.7);
  --shell-font:system-ui,sans-serif;
  --shell-font-mono:ui-monospace,monospace;
  -webkit-font-smoothing:antialiased;
  background:
    radial-gradient(1100px 640px at 80% -10%, rgba(59,130,246,.12), transparent 60%),
    #020617;
}
.st-os-root .os-menubar{backdrop-filter:blur(14px);height:34px}
.st-os-root .os-brand{display:flex;align-items:center;gap:9px}
.st-os-root .osname{font-family:var(--shell-font-mono);font-size:12px;letter-spacing:.02em;color:var(--shell-fg)}
.st-os-root .osname b{color:var(--shell-accent)}
.st-os-root .clock{font-family:var(--shell-font-mono);font-size:11.5px;color:var(--shell-fg);letter-spacing:.06em;min-width:66px;text-align:right}
.st-os-root .wallmark{position:absolute;right:5vw;top:16vh;width:min(46vw,560px);opacity:.05;pointer-events:none;font-family:system-ui,sans-serif;font-weight:700;font-size:clamp(56px,11vw,140px);line-height:.9;color:var(--shell-fg)}
.st-os-root .hint{position:absolute;left:22px;top:16px;max-width:260px;color:var(--shell-fg-muted);font-size:11px;line-height:1.6;font-family:var(--shell-font-mono);letter-spacing:.04em}
.st-os-root .hint b{color:var(--shell-accent)}
.st-os-root .os-window .os-window-body{animation:st-winopen .4s cubic-bezier(.16,1,.3,1)}
@keyframes st-winopen{from{opacity:0;transform:scale(.98) translateY(6px)}to{opacity:1;transform:none}}
.st-os-root .os-window-titlebar{background:linear-gradient(#f8fafc,#eef2f7);color:#475569;font-family:var(--shell-font-mono);font-size:11px;letter-spacing:.08em;text-transform:uppercase}
.st-os-root .os-dock{backdrop-filter:blur(16px);left:auto;right:14px;bottom:14px;transform:none;padding:8px 12px;gap:8px}
.st-os-root .dock-launch{display:flex;align-items:center;gap:9px;padding:9px 15px;border-radius:11px;background:var(--shell-accent);color:#fff;border:none;cursor:pointer;font-family:var(--shell-font-mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase;font-weight:500}
.st-os-root .dock-app{display:flex;align-items:center;gap:8px;padding:7px 12px;border-radius:10px;color:#cbd5e1;background:none;border:1px solid transparent;cursor:pointer;font:inherit;font-size:12px}
.st-os-root .dock-app:hover{background:rgba(255,255,255,.06);color:var(--shell-fg)}
.st-os-root .dock-app.open{background:rgba(255,255,255,.05);border-color:var(--shell-edge);color:var(--shell-fg)}
.st-os-root .dock-app .glyph{width:16px;height:16px;border-radius:20%;flex:none}
.st-os-root .launcher-scrim{position:absolute;inset:0;background:rgba(2,6,23,.42);backdrop-filter:blur(2px)}
.st-os-root .launcher-pop{position:absolute;right:14px;bottom:70px;width:360px;max-height:70vh;overflow:auto;background:rgba(15,23,42,.96);backdrop-filter:blur(20px);border:1px solid var(--shell-edge);border-radius:16px;box-shadow:var(--shell-shadow);padding:16px}
.st-os-root .launcher-heading{font-family:var(--shell-font-mono);font-size:9.5px;letter-spacing:.16em;text-transform:uppercase;color:#64748b;margin:6px 4px 8px}
.st-os-root .launcher-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.st-os-root .launcher-app{display:flex;align-items:center;gap:11px;padding:11px;border-radius:12px;border:1px solid transparent;text-align:left;width:100%;background:none;cursor:pointer;font:inherit}
.st-os-root .launcher-app:hover{background:rgba(255,255,255,.05);border-color:var(--shell-edge)}
.st-os-root .launcher-app .glyph{width:34px;height:34px;border-radius:22%;flex:none}
.st-os-root .launcher-app .name{display:block;font-family:var(--shell-font);font-size:13px;color:var(--shell-fg);font-weight:500}
.st-os-root .launcher-app .realm{display:block;font-family:var(--shell-font-mono);font-size:8.5px;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;margin-top:2px}
.st-os-root .os-statusbus{backdrop-filter:blur(14px)}
.st-app-root{position:fixed;inset:0;overflow:auto;background:#f8fafc}
`;

const Mark = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 64 64" width={16} height={16}>
        <rect width="64" height="64" rx="15" fill="#3b82f6" />
        <g fill="#0f172a">
            <rect x="14" y="20" width="5" height="24" rx="2.5" />
            <rect x="24" y="14" width="5" height="36" rx="2.5" />
            <rect x="34" y="24" width="5" height="16" rx="2.5" />
            <rect x="44" y="18" width="5" height="28" rx="2.5" />
        </g>
    </svg>
);

// ── A per-window error boundary ─────────────────────────────────────────────────────────────────────
class SurfaceBoundary extends Component<{ title: string; route: string; children: ReactNode }, { error: Error | null }> {
    state = { error: null as Error | null };
    static getDerivedStateFromError(error: Error) {
        return { error };
    }
    componentDidCatch(error: Error, info: ErrorInfo) {
        console.error(`[os] surface "${this.props.title}" threw`, error, info);
    }
    render() {
        if (this.state.error) {
            return (
                <div style={{ padding: 28, fontFamily: 'ui-monospace,monospace', fontSize: 12, lineHeight: 1.6, color: '#475569', background: '#f8fafc', height: '100%' }}>
                    <div style={{ color: '#dc2626', letterSpacing: '.1em', textTransform: 'uppercase', marginBottom: 10 }}>Surface needs its page props</div>
                    <p style={{ maxWidth: '46ch' }}>
                        The real <b>{this.props.title}</b> component crashed without its page-specific Inertia props. Open the live route:
                    </p>
                    <Link href={this.props.route} style={{ color: '#2563eb' }}>open {this.props.route} ↗</Link>
                    <pre style={{ marginTop: 14, whiteSpace: 'pre-wrap', color: '#b91c1c' }}>{String(this.state.error.message)}</pre>
                </div>
            );
        }

        return this.props.children;
    }
}

// ── The nested-window scope factory (the recursion) ─────────────────────────────────────────────────
const surfaceMainframe: Mainframe = ({ slots }) => (
    <div style={{ height: '100%', minHeight: 0, display: 'flex', flexDirection: 'column' }}>{slots.main()}</div>
);

function surfaceInjection(title: string, route: string, render: () => ReactNode): MainframeInjection {
    const slots = createSlotRegistry();
    const mainframes = createMainframeRegistry();
    mainframes.register('surface', surfaceMainframe);
    slots.contribute({
        slot: 'main',
        key: `surface:${title}`,
        render: () => (
            <SurfaceBoundary title={title} route={route}>
                {render()}
            </SurfaceBoundary>
        ),
    });

    return { slots, mainframes };
}

// ── The realm-key ↔ surface MAP (host config) ───────────────────────────────────────────────────────
// The manifest emits beam's BASE realm keys (operator/tenant/site/user); the /os surfaces bind them:
//   site → SiteHome ·  user → AccountHome (wrapped in the account shell) ·  operator → OperatorDashboard
//   tenant → EXCLUDED (a page/tool you navigate to, not a launchable realm)
// AUTO-SURFACE: an unmapped realm key falls through to a legible "no surface bound" placeholder window,
// so registering a NEW realm PHP-side surfaces a dock tile + placeholder with ZERO edits here.
export const SURFACE_MAP: Record<string, RealmSurfaceBinding> = {
    site: {
        label: 'Site',
        route: '/',
        subtitle: 'Public · marketing',
        accent: '#3b82f6',
        geometry: { x: 60, y: 70, width: 640, height: 540 },
        render: () => <Suspense fallback={null}><SiteHome /></Suspense>,
    },
    user: {
        label: 'Account',
        route: '/account-home',
        subtitle: 'Authed · account',
        accent: '#10b981',
        geometry: { x: 180, y: 150, width: 760, height: 500 },
        render: () => (
            <Suspense fallback={null}>
                <BeamAccountLayout>
                    <AccountHome />
                </BeamAccountLayout>
            </Suspense>
        ),
    },
    operator: {
        label: 'Operator',
        route: '/operator',
        subtitle: 'Frame · back-office',
        accent: '#f59e0b',
        geometry: { x: 220, y: 90, width: 720, height: 540 },
        render: () => <Suspense fallback={null}><OperatorDashboard /></Suspense>,
    },
};

// The CURRENT-ROUTE → REALM-KEY map (used by the switcher/active-tile highlight).
export const COMPONENT_REALM: Record<string, string> = {
    'site/home': 'site',
    welcome: 'site',
    'account/home': 'user',
    dashboard: 'user',
    'operator/dashboard': 'operator',
};

export function realmForComponent(name: string): string | undefined {
    return COMPONENT_REALM[name];
}

// ── Auto-surface fallback ───────────────────────────────────────────────────────────────────────────
function genericSurface(entry: RealmManifestEntry): ReactNode {
    return (
        <div style={{ padding: 28, fontFamily: 'ui-monospace,monospace', fontSize: 12, lineHeight: 1.6, color: '#475569', background: '#f8fafc', height: '100%' }}>
            <div style={{ color: '#2563eb', letterSpacing: '.1em', textTransform: 'uppercase', marginBottom: 10 }}>Realm auto-surfaced</div>
            <p style={{ maxWidth: '46ch' }}>
                The <b>{entry.title}</b> realm (<code>{entry.key}</code>) is registered PHP-side and appears in the manifest, but no /os surface is bound to its key yet. Bind one by adding a <code>SURFACE_MAP[&apos;{entry.key}&apos;]</code> row.
            </p>
            <Link href={entry.routeBase || '/'} style={{ color: '#2563eb' }}>open {entry.routeBase || '/'} ↗</Link>
        </div>
    );
}

const GENERIC_BINDING = (entry: RealmManifestEntry): RealmSurfaceBinding => ({
    label: entry.title,
    route: entry.routeBase || '/',
    subtitle: 'Realm · unbound surface',
    accent: '#64748b',
    geometry: { x: 240, y: 130, width: 640, height: 480 },
    render: () => genericSurface(entry),
});

// `tenant` = a page/tool reached via nav, not a launchable realm.
const OS_REALM_EXCLUDE = new Set(['tenant']);

export function osAppsFromManifest(manifest: RealmManifestEntry[]): DesktopApp[] {
    return buildAppsFromManifest(manifest, {
        surfaceMap: SURFACE_MAP,
        exclude: OS_REALM_EXCLUDE,
        genericBinding: GENERIC_BINDING,
        surfaceInjection,
    });
}

// ── Host-supplied desktop chrome nodes ──────────────────────────────────────────────────────────────
function OsBrand() {
    return (
        <span className="os-brand">
            <Mark className="mark" />
            <span className="osname">
                <b>beam</b>·os
            </span>
        </span>
    );
}

function OsStatus() {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <span style={{ fontFamily: 'ui-monospace,monospace', fontSize: 10, color: '#94a3b8', letterSpacing: '.12em', textTransform: 'uppercase' }}>Window mode</span>
            <Clock />
        </div>
    );
}

const OS_BACKDROP = (
    <>
        <div className="wallmark">
            beam
            <br />
            os
        </div>
        <div className="hint">
            beam·<b>os</b> — the front-end CMS as an operating system. Each realm is a launchable app; a window frames its <b>real</b> surface (its actual page component).
        </div>
    </>
);

// ── Workspace persistence (host-owned localStorage) ─────────────────────────────────────────────────
const WORKSPACE_KEY = 'beam-starter.os.workspace.v1';

function readPersistedWorkspace(): PersistedWorkspace | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(WORKSPACE_KEY);

        return raw ? (JSON.parse(raw) as PersistedWorkspace) : null;
    } catch {
        return null;
    }
}

function writePersistedWorkspace(workspace: PersistedWorkspace): void {
    try {
        window.localStorage.setItem(WORKSPACE_KEY, JSON.stringify(workspace));
    } catch {
        /* best-effort */
    }
}

const OS_ENTER_KEY = 'os.enter';

/** APP-FIRST fallback: one maximized real surface, no desktop chrome (guest / unentitled principal). */
function AppFirstSurface({ apps }: { apps: DesktopApp[] }) {
    const primary = apps.find((a) => a.key === 'user' && !a.locked) ?? apps.find((a) => !a.locked) ?? apps[0] ?? null;
    const binding = primary ? (SURFACE_MAP[primary.key] ?? GENERIC_BINDING({ key: primary.key, title: primary.title, routeBase: primary.route ?? '/', locked: !!primary.locked, upsell: primary.upsell })) : null;

    return (
        <>
            <style dangerouslySetInnerHTML={{ __html: OS_SHELL_CSS }} />
            <ThemeShellStyle />
            <div className="st-app-root">
                {primary && binding ? (
                    <SurfaceBoundary title={primary.title} route={primary.route ?? '/'}>
                        {binding.render()}
                    </SurfaceBoundary>
                ) : (
                    <div style={{ padding: 40, color: '#64748b' }}>No surface available.</div>
                )}
            </div>
        </>
    );
}

/**
 * The OS desktop — menu bar + dock + launcher + floating window manager, mounting the shared roster.
 * Entitled (`can['os.enter']`) → the full desktop; unentitled → app-first (one maximized surface).
 */
export function OsShellDesktop() {
    const page = usePage<{ realmManifest?: RealmManifestEntry[]; can?: Record<string, boolean> }>();
    const manifest = (page.props.realmManifest as RealmManifestEntry[] | undefined) ?? [];
    const canEnterOs = !!(page.props.can as Record<string, boolean> | undefined)?.[OS_ENTER_KEY];
    const currentComponent = page.component;
    const apps = osAppsFromManifest(manifest);

    // DEV override: `?os=off` forces app-first to verify the unentitled fusion character in-browser.
    const devForceAppFirst =
        import.meta.env.DEV && typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('os') === 'off';

    if ((!canEnterOs && !import.meta.env.DEV) || devForceAppFirst) {
        return <AppFirstSurface apps={apps} />;
    }

    const activeKey = realmForComponent(currentComponent ?? '') ?? undefined;
    const osInjection = buildDesktopChrome({
        apps,
        brand: <OsBrand />,
        status: <OsStatus />,
        backdrop: OS_BACKDROP,
        statusLine: (
            <span style={{ fontFamily: 'ui-monospace,monospace', fontSize: 10 }}>
                {apps.length} realms · window manager live · layout persists per session
            </span>
        ),
        activeKey,
        launcherHeading: 'Realms',
        onNavigate: (a) => router.visit(a.route ?? '/'),
        persist: writePersistedWorkspace,
    });

    const persisted = readPersistedWorkspace();
    const hasPersisted = !!persisted && (persisted.windows?.length ?? 0) > 0;
    const initialOpen = apps
        .filter((a) => !a.locked)
        .slice(0, 3)
        .map((a) => a.key);

    return (
        <>
            <style dangerouslySetInnerHTML={{ __html: OS_SHELL_CSS }} />
            <ThemeShellStyle />
            <MainframeProvider injection={osInjection}>
                <div className="st-os-root" style={{ position: 'fixed', inset: 0, overflow: 'hidden' }}>
                    <MainframeOutlet
                        mode="os"
                        ctx={{
                            os: {
                                apps,
                                persisted,
                                initialOpen: hasPersisted ? [] : initialOpen,
                            },
                        }}
                    />
                </div>
            </MainframeProvider>
        </>
    );
}
