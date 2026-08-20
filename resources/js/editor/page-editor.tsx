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

export function PageEditor({ slug, body = null }: { slug: string; body?: unknown }) {
    // theme-entries-and-authoring ticket `str-01`: server-resolved theme, NEUTRAL_THEME as the
    // degrade-safe fallback (mirrors mount.tsx's VisualEditorMount).
    const page = usePage<{ theme?: { canvas?: Partial<CanvasTheme> } }>();
    const theme = page.props.theme?.canvas ?? NEUTRAL_THEME;

    return (
        <CanvasProvider config={canvasConfig}>
            <CanvasPageEditor
                slug={slug}
                body={asDoc(body)}
                transport={{ saveBody: (s, doc) => bodyClient.saveBody(s, doc as unknown as Record<string, unknown>) }}
                notify={{ success: (m) => toast.success(m), error: (m) => toast.error(m) }}
                fallbackDoc={defaultTreeFor}
                theme={theme}
                brand="beam-starter · editor"
            />
        </CanvasProvider>
    );
}

export default PageEditor;
