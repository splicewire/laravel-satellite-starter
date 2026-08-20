import { Link, usePage } from '@inertiajs/react';
import { SiteNav as BeamSiteNav } from '@splicewire/beam-ux/site';
import type { CSSProperties } from 'react';

/**
 * Data-driven public-site nav. Thin host wrapper over the package `<SiteNav>`
 * (`@splicewire/beam-ux/site`): reads the shared `nav` prop — the `site` sitemap projected by
 * beam-ux's NavProjector (structurally a `{ items }` tree) — and renders its top-level items as
 * `.navlink` anchors, routed through Inertia's `<Link>`. The CONTENT links (Home / About) come from
 * `resources/beam-ux/nav.yml`; add a `site`-realm row there and it appears here, no edit to this file.
 */
type NavNode = { title: string; href?: string | null };
type PageProps = { nav?: { items: NavNode[] } };

export default function SiteNav({ style }: { style?: CSSProperties }) {
    const { nav } = usePage<PageProps>().props;

    return <BeamSiteNav nav={nav} linkComponent={Link} itemClassName="navlink" itemStyle={style} />;
}
