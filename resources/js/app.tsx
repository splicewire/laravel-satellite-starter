import { createInertiaApp } from '@inertiajs/react';
import { beamInertiaOptions } from '@splicewire/beam-inertia';
import type { PageModule } from '@splicewire/beam-inertia';
import { ApiReference, configureDocs } from '@splicewire/beam-docs';
import { beamDocsPages } from '@splicewire/beam-docs/pages';
import '@splicewire/beam-ux/tokens-docs.css';
import { authFeatures } from './beam';

const pages = import.meta.glob<PageModule>([
    './pages/**/*.tsx',
    '!./pages/_prototype.tsx',
]);

if (import.meta.env.DEV) {
    pages['_prototype'] = () =>
        import('./pages/_prototype') as Promise<PageModule>;
}

const options = beamInertiaOptions({
    development: import.meta.env.DEV,
    features: authFeatures,
    // Where MDX pages hold blocks (BEAM_UX_MDX_HOLDS_BLOCKS), what their profile offers beyond the packaged islands:
    // beam-inertia registers SiteNav and ManifestTable, and this starter the docs pages' ApiReference. This starter does
    // not register the beam-mdx kit yet, so the editor still refuses MDX pages with the switch on.
    mdxHoldsBlocks: import.meta.env.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true',
    components:
        import.meta.env.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true'
            ? { ApiReference }
            : {},
    pages: { ...beamDocsPages, ...pages },
});
configureDocs();

createInertiaApp(options);
