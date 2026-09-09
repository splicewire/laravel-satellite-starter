// Window-mode ("Edit content") mount — a THIN host wrapper over @splicewire/beam-ux/canvas's promoted
// VisualEditor. Loads the entry's body (JsonDoc) through the host transport, edits it, and saves back. A
// body that isn't a JsonDoc yet (or none) starts from the slug default (or an empty root). Mounted by the
// Mainframe host's window (author) mode.
//
// ID-ADDRESSED as of beam-docs-satellite ticket 40. It is mounted by the Mainframe host, which hands it
// an `EntryRef` — `{id, slug}` — instead of the bare slug it used to resolve off the Inertia component
// name. The slug survives only as the `defaultTreeFor()` SEED key: it names the page, not the row.
import { usePage } from '@inertiajs/react';
import type { EntryRef } from '@splicewire/beam-mainframe';
import type { JsonDoc } from '@splicewire/beam-ux/blockdoc/json';
import { CanvasProvider, VisualEditor } from '@splicewire/beam-ux/canvas';
import type { CanvasTheme } from '@splicewire/beam-ux/canvas';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { canvasConfig } from './canvas-config';
import { defaultTreeFor } from './defaults';
import { asDoc } from './page-editor';
import { NEUTRAL_THEME } from './theme';
import { bodyClient } from './transport';

const EMPTY: JsonDoc = [
    {
        kind: 'block',
        name: 'div',
        isComponent: false,
        dynamic: false,
        props: [{ name: 'className', kind: 'string', value: 'page' }],
        children: [],
    },
];

/**
 * The per-slug SEED tree for a page with no persisted body yet. Keyed by slug because it is a FRONTEND
 * default, not an address — a ref carrying no slug simply gets the empty root.
 */
function seedFor(slug: string | null): JsonDoc {
    return slug === null ? EMPTY : (defaultTreeFor(slug) ?? EMPTY);
}

export function VisualEditorMount({ entryRef }: { entryRef: EntryRef }) {
    const entryId = entryRef.id;
    const slug = entryRef.slug;
    // With no id there is nothing to load, so the seed IS the initial state — no effect, no loading
    // flash. Reachable only for a ref that genuinely has no id (a hand-typed `?beam_entry=<slug>`,
    // which this starter deliberately does not resolve — see mainframe-host.tsx).
    const [doc, setDoc] = useState<JsonDoc | null>(() =>
        entryId === null ? seedFor(slug) : null,
    );
    // theme-entries-and-authoring ticket `str-01`: the server-resolved theme (ThemeResolver cascade)
    // replaces the static NEUTRAL_THEME import — NEUTRAL_THEME stays as the degrade-safe fallback for
    // when the prop is absent (a stale build, or a request that never reached HandleInertiaRequests).
    const page = usePage<{ theme?: { canvas?: Partial<CanvasTheme> } }>();
    const theme = page.props.theme?.canvas ?? NEUTRAL_THEME;

    useEffect(() => {
        if (entryId === null) {
            return;
        }

        let live = true;
        const fallback = () => seedFor(slug);
        bodyClient
            .loadBody(entryId)
            .then((env) => {
                const body = (env as { body?: unknown })?.body;

                if (live) {
                    setDoc(asDoc(body) ?? fallback());
                }
            })
            .catch(() => live && setDoc(fallback()));

        return () => {
            live = false;
        };
    }, [entryId, slug]);

    if (!doc) {
        return (
            <div style={{ padding: 24, color: '#64748b', fontSize: 13 }}>
                Loading editor…
            </div>
        );
    }

    const save = async () => {
        // Unconditional now: without an id there is no address to save to. This used to be reachable
        // with a slug and no id at all, which is exactly the shape ADR-0214 §2 made unrepresentable.
        if (entryId === null) {
            toast.error('This page is not bound to an entry');

            return;
        }

        try {
            await bodyClient.saveBody(
                entryId,
                doc as unknown as Record<string, unknown>,
            );
            toast.success('Saved');
        } catch {
            toast.error('Save failed');
        }
    };

    return (
        <CanvasProvider config={canvasConfig}>
            <VisualEditor
                value={doc}
                onChange={setDoc}
                onSave={save}
                theme={theme}
                brand="beam-starter · visual editor"
            />
        </CanvasProvider>
    );
}

export default VisualEditorMount;
