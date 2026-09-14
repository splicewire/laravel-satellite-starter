import type {
    AuthEntryPageData,
    ProfilePageData,
    ResetPasswordPageData,
} from '../../resources/js/generated/Splicewire/Beam/Accounts/Data/Pages';
import type {
    FrameConsolePageData,
    SitemapResourcePageData,
} from '../../resources/js/generated/App/Data/Pages';

const profile: ProfilePageData = {
    entry: null,
    mustVerifyEmail: false,
    status: null,
};
// `realm` is nullable to match beam-inertia's `FrameRealmContext` — an unscoped console passes null.
const frameConsole: FrameConsolePageData = {
    realm: null,
    basename: '/operator',
    manifestUrl: '/operator/frame/manifest',
};
const auth: AuthEntryPageData = {
    slug: 'confirm-password',
    entry: null,
    body: null,
};
const reset: ResetPasswordPageData = {
    slug: 'reset-password',
    entry: null,
    body: null,
    email: null,
    token: 'token',
    passwordRules: '',
};
const sitemap: SitemapResourcePageData = {
    resource: {
        key: 'sitemap',
        data: 'App.Data.SitemapData',
        creatable: true,
        query: null,
        editData: null,
        policy: null,
        form: 'bare',
        layout: null,
        deletable: true,
        editable: true,
        showable: true,
        createAffordance: 'frame',
        singularLabel: '',
        nav: {
            label: 'Sitemap',
            group: null,
            icon: null,
            section: null,
            navOrder: null,
            routeName: null,
        },
    },
};

// These must fail even if a generator starts emitting any or loses a nested reference.
// @ts-expect-error profile status is nullable text, not a number
const badStatus: ProfilePageData['status'] = 7;
// @ts-expect-error the console's manifest address is a required string, not optional
const badConsole: FrameConsolePageData = { realm: 'operator', basename: '/operator' };
// @ts-expect-error the package-owned resource reference must not decay to any
const badResource: SitemapResourcePageData['resource'] = 'sitemap';
// @ts-expect-error nested package navigation metadata is also typed
const badNav: SitemapResourcePageData['resource']['nav']['label'] = 42;
const badDemo: AuthEntryPageData['demoAccounts'] = [
    // @ts-expect-error demo account links carry string URLs
    { key: 'demo', label: 'Demo', url: 7 },
];

void [
    profile,
    frameConsole,
    auth,
    reset,
    sitemap,
    badStatus,
    badConsole,
    badResource,
    badNav,
    badDemo,
];
