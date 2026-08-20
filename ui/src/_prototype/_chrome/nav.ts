// PROTOTYPE CHROME — host-owned rail/settings nav DATA for @splicewire/beam-ux-prototype.
// This is one of the few host-owned remainders: the generic chrome (Gallery, VariantBar,
// SettingsFrame, PrototypeDesk) ships from the package; only this nav data + the brand/nav-injecting
// wrappers live here. Edit freely — replace the sample groups with your app's real rail.
import type { NavGroup, NavTab } from '@splicewire/beam-ux-prototype';

// The rail groups the PrototypeDesk brand wrapper injects (realm → preset).
export const nav: NavGroup[] = [
    {
        label: 'Workspace',
        items: [
            { label: 'Home', href: '/_prototype', icon: 'Home' },
            { label: 'Starter', href: '/_prototype/starter', icon: 'Sparkles' },
        ],
    },
];

// The Settings meta-area sub-nav SettingsFrame takes as a prop.
export const settingsTabs: NavTab[] = [
    { label: 'General', href: '/_prototype/settings' },
    { label: 'Advanced', href: '/_prototype/settings/advanced' },
];
