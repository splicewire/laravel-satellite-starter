// beam-starter · os — the OPERATOR META-EDITOR OVERLAY, ported from rushing/audiostud's own
// resources/js/os/operator-desk.tsx (same architecture, same shared `@schemastud/mainframe/os`
// `<OperatorOverlay>` machinery, same `beam-ux:mode`/`beam-ux:edit`/`beam-ux:exit` event contract —
// all package-level, host-agnostic; see `@splicewire/beam-mainframe`'s `host.tsx`).
//
// You browse the LIVE site/app normally (every real page renders underneath, scrolls, and is fully
// interactive), and as an `os.enter` principal you get a floating window layer ON TOP: a start-menu
// launcher whose items are the operator tools. "Edit this page" flips the current page's MainframeHost
// into its in-place editor. Mounted on every authed page by `OsLayout` for an entitled principal.
//
// Deliberately narrower than audiostud's version: this host has no Customers/Reports back-office tools
// (single operator surface — the stats dashboard). It DOES carry audiostud's PageProperties float —
// "Edit this page" opens a per-slug `page:{slug}` window (its own dock item) rather than dispatching
// `beam-ux:edit` directly; see `./page-properties.tsx` for what it's trimmed down to here.
import { router, usePage } from '@inertiajs/react';
import { OperatorOverlay } from '@schemastud/mainframe/os';
import type { OverlayWindow, WindowManager } from '@schemastud/mainframe/os';
import { Suspense, lazy, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { PageProperties } from '@/os/page-properties';

// Lazy, not static - os/shell-config.tsx's own docblock already flags why: a page component ALSO
// statically imported elsewhere gets merged into that importer's chunk by Rollup instead of getting its
// own Vite-manifest entry, 500ing a direct visit to that page's real route ("Unable to locate file in
// Vite manifest"). Confirmed live against this exact file before switching to lazy().
const OperatorDashboard = lazy(() => import('@/pages/operator/dashboard'));

// A launchable operator tool: opens as a nested float rendering the REAL back-office component.
// `OperatorDashboard`'s own props are optional (renders with sensible fallbacks) precisely so it can
// mount standalone in a float, with no Inertia route props threaded in.
type Tool = {
    key: string;
    title: string;
    accent: string;
    render: () => ReactNode;
    size: { width: number; height: number };
};

const TOOLS: Tool[] = [
    {
        key: 'dashboard',
        title: 'Dashboard',
        accent: '#4B5563',
        render: () => (
            <Suspense fallback={<div className="p-6 text-sm text-slate-500">Loading…</div>}>
                <OperatorDashboard />
            </Suspense>
        ),
        size: { width: 720, height: 560 },
    },
];

// Resolve a window key → its chrome + content. A fixed TOOL, or a per-page properties window keyed
// `page:{slug}` (the operator "Edit this page" surface — several can be open at once, ported from
// audiostud's own desk). `editable`/`editing` close over the CURRENT `beam-ux:mode` broadcast (read
// from the OperatorDesk component below) since resolveWindow is called fresh on every render.
function resolveWindow(
    key: string,
    wm: WindowManager,
    editable: boolean,
    editing: boolean,
): OverlayWindow | null {
    if (key.startsWith('page:')) {
        const slug = key.slice('page:'.length);

        return {
            title: `Page · ${slug}`,
            accent: '#00b3c8',
            render: () => (
                <PageProperties
                    slug={slug}
                    editable={editable}
                    editing={editing}
                    onEditContent={() => {
                        // Minimize the properties window, then enter the in-place content editor —
                        // same handoff audiostud's desk uses.
                        wm.minimize(key);
                        window.dispatchEvent(new CustomEvent('beam-ux:edit'));
                    }}
                    onExitContent={() => window.dispatchEvent(new CustomEvent('beam-ux:exit'))}
                />
            ),
        };
    }

    const tool = TOOLS.find((t) => t.key === key);

    return tool ? { title: tool.title, accent: tool.accent, render: tool.render } : null;
}

// The beam mark — same glyph as splicewire's `@/components/app-logo-icon`, inlined so the accent node
// rides `--beam-accent` rather than that component's own hardcoded fill. This host has no
// brand-tokens.css of its own (that's splicewire.test's), so every `--beam-*` reference below carries
// a literal fallback — a project generated from this starter looks right out of the box, and gets
// live-themed for free the moment it defines its own `--beam-*` tokens.
function BeamMark({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path
                d="M3 7 C9 7 10 12 14 12 M3 17 C9 17 10 12 14 12 M14 12 H21"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
            />
            <circle cx="3" cy="7" r="1.6" fill="currentColor" />
            <circle cx="3" cy="17" r="1.6" fill="currentColor" />
            <circle cx="14" cy="12" r="2.7" fill="var(--beam-accent, #00b3c8)" />
        </svg>
    );
}

const OP_DESK_CSS = `
.op-desk-overlay{position:fixed;inset:0;z-index:2000;pointer-events:none;font-family:system-ui,sans-serif}
.op-desk-overlay > *{pointer-events:auto}
.op-win-inner{display:flex;flex-direction:column;height:100%;background:#f8fafc;border:1px solid rgba(15,23,42,.14);border-radius:12px;box-shadow:0 30px 90px -24px rgba(0,0,0,.5);overflow:hidden}
.op-win-bar{display:flex;align-items:center;gap:9px;height:38px;flex:none;padding:0 12px;background:var(--beam-paper-raised, #0e1b18);color:var(--beam-ink, #dcede8);cursor:move;user-select:none}
.op-win-dot{width:10px;height:10px;border-radius:30%;flex:none}
.op-win-title{font-family:ui-monospace,monospace;font-size:11px;letter-spacing:.12em;text-transform:uppercase}
.op-win-ctrls{margin-left:auto;display:flex;align-items:center;gap:1px}
.op-win-x{background:none;border:none;color:var(--beam-ink-muted, rgba(220, 237, 232, .5));font-size:16px;line-height:1;cursor:pointer;padding:4px 8px;border-radius:6px;display:flex;align-items:center;justify-content:center;min-width:28px}
.op-win-x:hover{color:var(--beam-ink, #dcede8);background:rgba(255,255,255,.1)}
.op-win-x.on{color:var(--beam-paper-raised, #0e1b18)}
.op-win-body{flex:1;min-height:0;overflow:auto;background:#f8fafc;color:#0f172a}
.op-taskbar{position:absolute;left:16px;bottom:16px;display:flex;align-items:center;gap:4px;padding:6px;border-radius:13px;border:1px solid rgba(255,255,255,.1);background:color-mix(in srgb, var(--beam-paper-raised, #0e1b18) 94%, transparent);backdrop-filter:blur(14px);box-shadow:0 18px 55px -14px rgba(0,0,0,.6)}
.op-taskbar button{display:flex;align-items:center;gap:7px;border:none;background:none;color:var(--beam-ink-muted, rgba(220, 237, 232, .5));cursor:pointer;font-family:ui-monospace,monospace;font-size:11px;letter-spacing:.06em;padding:8px 12px;border-radius:9px}
.op-taskbar button:hover{color:var(--beam-ink, #dcede8);background:rgba(255,255,255,.08)}
.op-taskbar button.focused{color:var(--beam-ink, #dcede8);background:rgba(255,255,255,.12)}
.op-taskbar button.minned{opacity:.55}
.op-taskbar .glyph{width:9px;height:9px;border-radius:30%;flex:none}
.op-orb{position:absolute;right:16px;bottom:16px;display:flex;align-items:center;gap:9px;padding:9px 15px;border-radius:13px;border:1px solid rgba(255,255,255,.1);background:color-mix(in srgb, var(--beam-paper-raised, #0e1b18) 94%, transparent);backdrop-filter:blur(14px);color:var(--beam-ink, #dcede8);cursor:pointer;font-family:ui-monospace,monospace;font-size:11px;letter-spacing:.14em;text-transform:uppercase;box-shadow:0 18px 55px -14px rgba(0,0,0,.6)}
.op-orb:hover{border-color:var(--beam-line, #1b2e2a)}
.op-orb.is-open{border-color:var(--beam-accent, #00b3c8);background:var(--beam-line, #1b2e2a)}
.op-orb .mark{width:16px;height:16px;flex:none;color:var(--beam-ink, #dcede8)}
.op-scrim{position:absolute;inset:0;background:transparent}
.op-menu{position:absolute;right:16px;bottom:66px;width:240px;padding:7px;border-radius:14px;border:1px solid rgba(255,255,255,.1);background:color-mix(in srgb, var(--beam-paper-raised, #0e1b18) 97%, transparent);backdrop-filter:blur(16px);box-shadow:0 26px 70px -18px rgba(0,0,0,.65);display:flex;flex-direction:column;gap:2px}
.op-menu-brand{display:flex;align-items:center;gap:8px;padding:9px 10px 11px;margin-bottom:3px;border-bottom:1px solid rgba(255,255,255,.08);color:var(--beam-ink, #dcede8);font-family:ui-monospace,monospace;font-size:13px;font-weight:600;letter-spacing:.02em}
.op-menu-brand-mark{width:18px;height:18px;flex:none;color:var(--beam-ink-muted, rgba(220, 237, 232, .5))}
.op-menu .head{padding:8px 10px 6px;color:var(--beam-ink-muted, rgba(220, 237, 232, .5));font-family:ui-monospace,monospace;font-size:9px;letter-spacing:.18em;text-transform:uppercase}
.op-menu button,.op-menu a{display:flex;align-items:center;gap:10px;width:100%;text-align:left;padding:9px 11px;border-radius:9px;border:none;background:none;color:var(--beam-ink, #dcede8);cursor:pointer;font:inherit;font-size:13px;text-decoration:none}
.op-menu button:hover,.op-menu a:hover{background:rgba(255,255,255,.07);color:var(--beam-ink, #dcede8)}
.op-menu button:disabled{opacity:.4;cursor:default}
.op-menu button:disabled:hover{background:none;color:var(--beam-ink, #dcede8)}
.op-menu button.active{color:var(--beam-ink, #dcede8);background:var(--beam-line, #1b2e2a)}
.op-menu .glyph{width:10px;height:10px;border-radius:30%;flex:none}
.op-menu .ico{width:16px;text-align:center;flex:none;opacity:.85}
.op-menu .op-div{height:1px;background:rgba(255,255,255,.1);margin:5px 4px}
.op-menu .muted{color:var(--beam-ink-muted, rgba(220, 237, 232, .5))}
`;

function StartMenu({
    openKeys,
    inOperator,
    editing,
    pageEditable,
    onEditToggle,
    onOpenTool,
    onClose,
}: {
    openKeys: string[];
    inOperator: boolean;
    editing: boolean;
    pageEditable: boolean;
    onEditToggle: () => void;
    onOpenTool: (tool: Tool) => void;
    onClose: () => void;
}) {
    return (
        <>
            <div className="op-scrim" onClick={onClose} />
            <div className="op-menu" role="menu">
                <div className="op-menu-brand">
                    <BeamMark className="op-menu-brand-mark" />
                    beam
                </div>
                <div className="head">Operator</div>
                <button
                    type="button"
                    className={editing ? 'active' : undefined}
                    disabled={!editing && !pageEditable}
                    onClick={() => {
                        if (!editing && !pageEditable) {
                            return;
                        }
                        onClose();
                        onEditToggle();
                    }}
                >
                    <span className="ico">{editing ? '✕' : '✎'}</span>
                    {editing ? 'Exit editing' : 'Edit this page'}
                    {!editing && !pageEditable ? (
                        <span className="muted" style={{ marginLeft: 'auto', fontSize: 11 }}>n/a</span>
                    ) : null}
                </button>
                <div className="op-div" />
                {TOOLS.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        className={openKeys.includes(t.key) ? 'active' : undefined}
                        onClick={() => {
                            onOpenTool(t);
                            onClose();
                        }}
                    >
                        <span className="glyph" style={{ background: t.accent }} />
                        {t.title}
                        {openKeys.includes(t.key) ? <span className="muted" style={{ marginLeft: 'auto', fontSize: 11 }}>open</span> : null}
                    </button>
                ))}
                <div className="op-div" />
                {inOperator ? (
                    <a href="/">
                        <span className="ico">◱</span> Front-end
                    </a>
                ) : (
                    <a href="/operator">
                        <span className="ico">▤</span> Control Panel
                    </a>
                )}
                <button type="button" onClick={() => router.post('/logout')}>
                    <span className="ico">⏻</span> Sign out
                </button>
            </div>
        </>
    );
}

export default function OperatorDesk() {
    const component = usePage().component;
    const inOperator = !!component?.startsWith('operator/');

    // The page's MainframeHost broadcasts its mode + editability + current entry slug via `beam-ux:mode`
    // (the shared `@splicewire/beam-mainframe` factory's own contract - identical on every host); we
    // track those so the start menu knows whether the page is editable and whether editing is active.
    const [editing, setEditing] = useState(false);
    const [pageEditable, setPageEditable] = useState(false);
    const [currentSlug, setCurrentSlug] = useState<string | null>(null);
    useEffect(() => {
        const onMode = (e: Event) => {
            const d = (e as CustomEvent<{ mode?: string; editable?: boolean; slug?: string }>).detail ?? {};
            setEditing(d.mode === 'window');
            setPageEditable(!!d.editable);
            setCurrentSlug(d.slug ?? null);
        };
        window.addEventListener('beam-ux:mode', onMode);

        return () => window.removeEventListener('beam-ux:mode', onMode);
    }, []);

    // Nav suppression: a click inside a float window that would trigger a full-page GET visit is
    // CANCELLED so the live backdrop isn't yanked away. Only GET *navigations* are suppressed; POST/PUT/
    // DELETE ACTIONS still go through. Armed on each window-body click, cleared shortly after.
    const suppressNextNav = useRef(false);
    const armSuppress = () => {
        suppressNextNav.current = true;
        window.setTimeout(() => {
            suppressNextNav.current = false;
        }, 60);
    };
    const subscribeNavSuppression = () =>
        router.on('before', (event) => {
            const method = (
                (event as CustomEvent<{ visit?: { method?: string } }>).detail?.visit?.method ?? 'get'
            ).toLowerCase();

            if (suppressNextNav.current && method === 'get') {
                suppressNextNav.current = false;

                return false; // cancel the visit — keep the backdrop put
            }
        });

    // ALL open windows (incl. minimized) in STABLE order (fixed tool order, then page windows sorted by
    // key) — so focusing a window never reshuffles the taskbar. Ported from audiostud's own desk.
    const stableKeys = (wm: WindowManager) => [
        ...TOOLS.map((t) => t.key),
        ...Object.keys(wm.state.windows)
            .filter((k) => k.startsWith('page:'))
            .sort(),
    ];

    // "Edit this page" opens the PAGE PROPERTIES float for the current slug (keyed `page:{slug}`) — its
    // own dock item, same as every other tool window (and the same audiostud behavior this replaces the
    // direct dispatch with). Exiting an already-active edit still dispatches straight through.
    const openProperties = (wm: WindowManager, slug: string) =>
        wm.open(`page:${slug}`, { geometry: { width: 380, height: 220 }, presentation: 'float' });
    const onEditToggle = (wm: WindowManager) => {
        if (editing) {
            window.dispatchEvent(new CustomEvent('beam-ux:exit'));
        } else if (currentSlug) {
            openProperties(wm, currentSlug);
        }
    };

    return (
        <>
            <style dangerouslySetInnerHTML={{ __html: OP_DESK_CSS }} />
            <OperatorOverlay
                stableKeys={stableKeys}
                resolveWindow={(key, wm) => resolveWindow(key, wm, pageEditable, editing)}
                orbLabel="Operator"
                orbIcon={<BeamMark className="mark" />}
                onWindowBodyClickCapture={armSuppress}
                onWindowManager={() => {
                    const off = subscribeNavSuppression();

                    return off;
                }}
                renderLauncher={({ wm, onClose }) => (
                    <StartMenu
                        openKeys={wm.state.zOrder}
                        inOperator={inOperator}
                        editing={editing}
                        pageEditable={pageEditable}
                        onEditToggle={() => onEditToggle(wm)}
                        onOpenTool={(tool) => wm.open(tool.key, { geometry: tool.size, presentation: 'float' })}
                        onClose={onClose}
                    />
                )}
            />
        </>
    );
}
