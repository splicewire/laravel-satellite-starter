// MDX island binding for the visual editor — a MINIMAL host stub.
//
// The packaged `CanvasConfig` requires an `MdxView`/`MdxEdit` pair (the canvas calls them for an `Mdx`
// tree node). This starter's default bodies emit NO `Mdx` node, so these stubs never actually render — a
// fresh host that wants rich `@splicewire/beam-mdx` content swaps these for the real `MdxView`/`MdxEdit`
// (wiring `<BeamMdxProvider>` + `withContentDescriptor`, per audiostud's `editor/mdx.tsx`) and adds the
// `@splicewire/beam-mdx` dep + its `@mdxeditor`/`@mdx-js`/`@rjsf` peer set. Keeping the starter free of
// that heavy chain is deliberate: the OOTB editor is block-only until a host opts into MDX.
export function MdxView({ md }: { file?: string; md?: string }) {
    if (typeof md === 'string' && md.trim() !== '') {
        return <div style={{ whiteSpace: 'pre-wrap' }}>{md}</div>;
    }

    return null;
}

export function MdxEdit({ md, onChange }: { file?: string; md?: string; onChange: (next: string) => void }) {
    return (
        <textarea
            value={typeof md === 'string' ? md : ''}
            onChange={(e) => onChange(e.target.value)}
            style={{ width: '100%', minHeight: 120, padding: 12, fontFamily: 'ui-monospace, monospace', fontSize: 13 }}
            placeholder="Markdown content — swap this stub for @splicewire/beam-mdx to get the rich editor."
        />
    );
}
