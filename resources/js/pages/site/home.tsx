import { Head } from '@inertiajs/react';
import PageEditor from '@/editor/page-editor';
import SiteLayout from '@/layouts/site-layout';

/**
 * A public SITE-realm page rendering through the OOTB `<SiteLayout>` (`@splicewire/beam-ux/site`), with
 * the in-place VISUAL EDITOR (`@splicewire/beam-ux/canvas`) mounted where the page content goes. In READ
 * mode the editor renders the `home` body via the package's `TreeRender`; for an author, the
 * MainframeHost's `beam-ux:mode` broadcast flips the SAME mount into the editing canvas. So this one file
 * proves BOTH promoted surfaces: the site chrome AND the in-place editor, from config only.
 */
export default function SiteHome({
    entry = null,
}: {
    entry?: { id: string; slug: string } | null;
}) {
    return (
        <SiteLayout>
            <Head title="Home" />
            <div
                style={{
                    maxWidth: 900,
                    margin: '0 auto',
                    padding: 'clamp(24px,5vw,48px) clamp(18px,5vw,40px)',
                }}
            >
                {/* The editable page body. slug `home` → its default JsonDoc (editor/defaults.ts) until a
                    save persists a real body. Read mode = TreeRender; author mode = the canvas.
                    `entryId` is the ADDRESS the save goes to (ADR-0214 §2), shared server-side by
                    `App\Support\PageEntryRef`; the slug is only the seed key and the editor label. */}
                <PageEditor
                    slug="home"
                    body={null}
                    entryId={entry?.id ?? null}
                />
            </div>
        </SiteLayout>
    );
}
