// Window-mode ("Edit content") mount — a THIN host wrapper over @splicewire/beam-ux/canvas's promoted
// VisualEditor. Loads the entry's body (JsonDoc) through the host transport, edits it, and saves back. A
// body that isn't a JsonDoc yet (or none) starts from the slug default (or an empty root). Mounted by the
// Mainframe host's window (author) mode.
import { usePage } from '@inertiajs/react';
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

const EMPTY: JsonDoc = [{ kind: 'block', name: 'div', isComponent: false, dynamic: false, props: [{ name: 'className', kind: 'string', value: 'page' }], children: [] }];

export function VisualEditorMount({ slug }: { slug: string }) {
    const [doc, setDoc] = useState<JsonDoc | null>(null);
    // theme-entries-and-authoring ticket `str-01`: the server-resolved theme (ThemeResolver cascade)
    // replaces the static NEUTRAL_THEME import — NEUTRAL_THEME stays as the degrade-safe fallback for
    // when the prop is absent (a stale build, or a request that never reached HandleInertiaRequests).
    const page = usePage<{ theme?: { canvas?: Partial<CanvasTheme> } }>();
    const theme = page.props.theme?.canvas ?? NEUTRAL_THEME;

    useEffect(() => {
        let live = true;
        const fallback = () => defaultTreeFor(slug) ?? EMPTY;
        bodyClient
            .loadBody(slug)
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
    }, [slug]);

    if (!doc) {
        return <div style={{ padding: 24, color: '#64748b', fontSize: 13 }}>Loading editor…</div>;
    }

    const save = async () => {
        try {
            await bodyClient.saveBody(slug, doc as unknown as Record<string, unknown>);
            toast.success('Saved');
        } catch {
            toast.error('Save failed');
        }
    };

    return (
        <CanvasProvider config={canvasConfig}>
            <VisualEditor value={doc} onChange={setDoc} onSave={save} theme={theme} brand="beam-starter · visual editor" />
        </CanvasProvider>
    );
}

export default VisualEditorMount;
