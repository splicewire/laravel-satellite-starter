// In-place page editor — a THIN host wrapper over @splicewire/beam-ux/canvas's promoted PageEditor. All
// machinery (read/edit-mode fork, floating panels, canvas, inspector) lives in the package; the host
// injects only its CanvasConfig, transport, toast, theme, and defaults. Mount `<PageEditor slug body/>`
// where a page's content goes; read mode renders the same body via the package's TreeRender.
import { usePage } from '@inertiajs/react';
import type { JsonDoc } from '@splicewire/beam-ux/blockdoc/json';
import { CanvasProvider, PageEditor as CanvasPageEditor } from '@splicewire/beam-ux/canvas';
import type { CanvasTheme } from '@splicewire/beam-ux/canvas';
import { toast } from 'sonner';
import { canvasConfig } from './canvas-config';
import { defaultTreeFor } from './defaults';
import { NEUTRAL_THEME } from './theme';
import { bodyClient } from './transport';

/** A persisted body is a JsonDoc only when it's an array of `{kind}` nodes; anything else → fall back. */
export function asDoc(body: unknown): JsonDoc | null {
    return Array.isArray(body) && body.every((n) => !!n && typeof n === 'object' && 'kind' in (n as object))
        ? (body as JsonDoc)
        : null;
}

export interface PageEditorProps {
    slug: string;
    body?: unknown;
    /**
     * The entry's uuid — supplied by the page from its server-shared `entry` prop
     * (`App\Support\PageEntryRef`).
     *
     * The BODY transport is addressed by id (ADR-0214 §2), so without this the surface can render the
     * body it was handed but cannot persist one. `null` is the honest answer for a page whose row is
     * absent (a database that was never seeded): the default tree still renders, Save reports it.
     */
    entryId?: string | null;
}

export function PageEditor({ slug, body = null, entryId = null }: PageEditorProps) {
    // theme-entries-and-authoring ticket `str-01`: server-resolved theme, NEUTRAL_THEME as the
    // degrade-safe fallback (mirrors mount.tsx's VisualEditorMount).
    const page = usePage<{ theme?: { canvas?: Partial<CanvasTheme> } }>();
    const theme = page.props.theme?.canvas ?? NEUTRAL_THEME;

    return (
        <CanvasProvider config={canvasConfig}>
            <CanvasPageEditor
                slug={slug}
                body={asDoc(body)}
                transport={{
                    // CanvasPageEditor's transport seam is keyed by its `slug` prop, which stays a slug
                    // — it is the editor's display label and `defaultTreeFor()` key. The BODY transport
                    // underneath is addressed by the entry ID (ADR-0214 §2). So the incoming `s` is
                    // deliberately unused: it names the page, not the row.
                    saveBody: (_s, doc) => {
                        if (entryId === null) {
                            throw new Error('no entry id — nothing to save against');
                        }

                        return bodyClient.saveBody(entryId, doc as unknown as Record<string, unknown>);
                    },
                }}
                notify={{ success: (m) => toast.success(m), error: (m) => toast.error(m) }}
                fallbackDoc={defaultTreeFor}
                theme={theme}
                brand="beam-starter · editor"
            />
        </CanvasProvider>
    );
}

export default PageEditor;
