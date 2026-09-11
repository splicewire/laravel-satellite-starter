import { createInertiaApp } from '@inertiajs/react';
import { beamInertiaOptions } from '@splicewire/beam-inertia';
import type { PageModule } from '@splicewire/beam-inertia';
import AppLogoIcon from './components/app-logo-icon';
import { authFeatures } from './beam';

const pages = import.meta.glob<PageModule>([
    './pages/**/*.tsx',
    '!./pages/_prototype.tsx',
]);

if (import.meta.env.DEV) {
    pages['_prototype'] = () =>
        import('./pages/_prototype') as Promise<PageModule>;
}

createInertiaApp({
    ...beamInertiaOptions({
        name: import.meta.env.VITE_APP_NAME || 'Laravel',
        logo: AppLogoIcon,
        development: import.meta.env.DEV,
        features: authFeatures,
        pages,
    }),
});
