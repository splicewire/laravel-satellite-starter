// The "Page properties" float — ported from rushing/audiostud's own os/page-properties.tsx (owner
// call 2026-08-07), trimmed to what this host actually has. audiostud's version reads/writes a
// `/beam/ux/meta` title/type/publish surface this starter has no equivalent of yet — this stays
// presentational-only, fed entirely off the `beam-ux:mode` broadcast the desk already tracks, so it
// adds no new endpoint. Grow it (title, realm, publish) once that surface exists here.
//
// One float per page slug (keyed `page:{slug}` by the desk), so several pages' properties can be open
// at once — the SAME reason audiostud's version does this. "Edit content" hands off to the desk (it
// minimizes this window, then dispatches `beam-ux:edit`), matching the audiostud handoff exactly.
export function PageProperties({
    slug,
    editable,
    editing,
    onEditContent,
    onExitContent,
}: {
    slug: string;
    editable: boolean;
    editing: boolean;
    onEditContent: () => void;
    onExitContent: () => void;
}) {
    return (
        <div className="pp">
            <style dangerouslySetInnerHTML={{ __html: PP_CSS }} />
            <div className="pp-row pp-meta">
                <span>
                    slug <b>{slug}</b>
                </span>
                <span className={editable ? 'pp-pub on' : 'pp-pub'}>{editable ? '● editable' : '○ read-only'}</span>
            </div>

            <div className="pp-actions">
                {editing ? (
                    <button type="button" className="pp-btn" onClick={onExitContent}>
                        ✕ Exit editing
                    </button>
                ) : (
                    <button type="button" className="pp-btn primary" disabled={!editable} onClick={onEditContent}>
                        ✎ Edit content
                    </button>
                )}
            </div>
        </div>
    );
}

// The window BODY chrome (`.op-win-body`) defaults to a light admin-table surface (the Dashboard
// tool's own look) — `.pp` overrides it full-bleed to the dark beam palette instead, since this is
// chrome/meta content, not tabular admin data. No brand-tokens.css here, so every `--beam-*` reference
// carries the same literal fallback the rest of this desk's chrome does.
const PP_CSS = `
.pp{min-height:100%;padding:18px 20px;display:flex;flex-direction:column;gap:14px;font-size:13px;color:var(--beam-ink, #dcede8);background:var(--beam-paper, #08120f)}
.pp-row{display:flex;flex-direction:column;gap:5px}
.pp-meta{flex-direction:row;gap:16px;font-family:ui-monospace,monospace;font-size:11px;color:var(--beam-ink-muted, rgba(220, 237, 232, .5));flex-wrap:wrap;align-items:center}
.pp-meta b{color:var(--beam-ink, #dcede8);font-weight:600}
.pp-pub.on{color:var(--beam-accent, #00b3c8)}
.pp-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px}
.pp-btn{padding:8px 14px;border-radius:8px;border:1px solid var(--beam-line, #1b2e2a);background:none;color:var(--beam-ink, #dcede8);cursor:pointer;font:inherit;font-size:12px}
.pp-btn:hover:not(:disabled){border-color:var(--beam-accent, #00b3c8)}
.pp-btn:disabled{opacity:.5;cursor:default}
.pp-btn.primary{background:var(--beam-accent, #00b3c8);border-color:var(--beam-accent, #00b3c8);color:#08120f}
.pp-btn.primary:hover:not(:disabled){opacity:.9}
`;
