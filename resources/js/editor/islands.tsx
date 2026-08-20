// Two tiny DEMO island components for the visual editor's opaque-island registry. A tree node whose
// `name` is a registered key (PascalCase) renders the REAL component (sealed — select/reorder/delete, no
// drill-in) instead of an intrinsic element. A fresh host swaps these for its own designed sections; they
// exist only to prove the island seam renders in place.
export function DemoHero() {
    return (
        <section
            style={{
                padding: 'clamp(40px,7vw,84px) clamp(18px,5vw,56px)',
                background: 'linear-gradient(180deg,#f8fafc,#eef2ff)',
                borderRadius: 16,
                textAlign: 'center',
            }}
        >
            <h1 style={{ fontSize: 'clamp(30px,5vw,46px)', fontWeight: 700, letterSpacing: '-0.02em', margin: '0 0 12px', color: '#0f172a' }}>
                An editable hero, in place.
            </h1>
            <p style={{ fontSize: 17, lineHeight: 1.55, color: '#475569', maxWidth: 520, margin: '0 auto' }}>
                This block is a sealed island — the real component renders in the canvas. Reorder or delete
                it, add blocks around it, and save.
            </p>
        </section>
    );
}

export function DemoFeatureRow() {
    const cells = [
        { title: 'Config, not machinery', body: 'The host writes a registry + defaults; the canvas is packaged.' },
        { title: 'One body, two lenses', body: 'The editor edits — and the page renders — the same tree.' },
        { title: 'Neutral tokens', body: 'Brand look is host config; the package bakes no palette.' },
    ];

    return (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 16, padding: '24px 0' }}>
            {cells.map((c) => (
                <div key={c.title} style={{ padding: 20, border: '1px solid #e2e8f0', borderRadius: 12, background: '#fff' }}>
                    <div style={{ fontWeight: 600, marginBottom: 6, color: '#0f172a' }}>{c.title}</div>
                    <div style={{ fontSize: 14, lineHeight: 1.5, color: '#64748b' }}>{c.body}</div>
                </div>
            ))}
        </div>
    );
}
