import { Head, Link } from '@inertiajs/react';
import { ApiReference, EntryBody, ManifestTable, Prose, SiteNav, type SiteNavData } from '@splicewire/beam-ux/site';
import { useMemo } from 'react';
import SiteLayout from '@/layouts/site-layout';

/**
 * The host half of beam-ux's public entry renderer (ADR-0209 §6), and the page `Route::beamUxSite()`
 * in `routes/web.php` renders every resolved entry through.
 *
 * `/docs`, `/docs/api` and `/docs/mcp` are live on a fresh install because of this file plus that one
 * route line — the pages themselves are seeded by `splicewire:beam:install`, as `BeamUxEntry` rows this
 * site owns. Edit them, move them, delete them; nothing re-asserts them. Re-rooting the whole `/docs`
 * subtree is an edit to one row's `segment`.
 *
 * ## What is yours and what is the package's
 *
 * No beam package ships a *rendered* page, so everything that makes a beam site look like itself lives
 * here: the chrome, the palette, the measure, and the component map a body may reach for. The package
 * supplies the parts that are the same everywhere — `<EntryBody>` loads and calls the compiled artifact,
 * `<Prose>` supplies the typographic scale in `--beam-*` tokens and names no colour or font of its own.
 *
 * The component map below is the contribution contract (ADR-0210 §5): an installed beam package seeds a
 * page naming `<ApiReference>` or `<ManifestTable>` and ships **no frontend at all**, so this map is
 * what makes those names resolve. Installing another contributing package should not require editing
 * this file — which holds as long as packages compose the generic `/site` components rather than
 * inventing their own.
 *
 * Deliberately NOT mapped here: `@splicewire/beam-mdx/kit`'s prose components (`<Callout>`, `<Steps>`,
 * `<Terminal>`…). They are a way of *writing*, not a thing a package installs, and nothing a fresh
 * install seeds uses them. Add the kit when you start authoring guides that reach for it.
 */

type EntryProps = {
    entry: {
        id: string;
        slug: string;
        title: string | null;
        type: string | null;
        format: string | null;
        url: string | null;
    };
    artifact: {
        url: string;
        version: string | null;
    };
    nav: SiteNavData | null;
};

export default function SiteEntry({ entry, artifact, nav }: EntryProps) {
    // `<SiteNav>` is the one mapped component that needs DATA a body cannot pass: the projection
    // arrives as an Inertia prop on this page, while an MDX body only ever writes
    // `<SiteNav rootPath="/docs" />`. Unbound it reads an absent `nav` and renders nothing at all.
    const components = useMemo(
        () => ({
            ApiReference,
            ManifestTable,
            SiteNav: (props: Parameters<typeof SiteNav>[0]) => <SiteNav nav={nav} linkComponent={Link} {...props} />,
        }),
        [nav],
    );

    return (
        <SiteLayout>
            <Head title={entry.title ?? entry.slug} />

            {/* The measure is applied PER CHILD rather than to the wrapper, so one page component serves
                both a prose page and a full-width application surface: text gets a readable column, and
                a child marked `data-beam-full-bleed` — the API reference is a whole application, not an
                article — spans edge to edge. A `max-w` on the wrapper boxes the reference into a narrow
                column and nothing inside it can escape. */}
            <Prose
                className={[
                    'beam-entry w-full',
                    '[&>*]:mx-auto [&>*]:w-full [&>*]:max-w-3xl [&>*]:px-6',
                    '[&>[data-beam-full-bleed]]:max-w-none [&>[data-beam-full-bleed]]:px-0',
                    '[&>*:first-child]:pt-12 [&>*:last-child]:pb-16',
                    '[&>[data-beam-full-bleed]:first-child]:pt-0 [&>[data-beam-full-bleed]:last-child]:pb-0',
                ].join(' ')}
            >
                <EntryBody artifact={artifact} components={components} />
            </Prose>
        </SiteLayout>
    );
}
