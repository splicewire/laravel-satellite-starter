import type {
    AuthEntryPageData,
    ProfilePageData,
    ResetPasswordPageData,
} from '../../resources/js/generated/Splicewire/Beam/Accounts/Data/Pages';
import type {
    OperatorDashboardPageData,
    SitemapResourcePageData,
} from '../../resources/js/generated/App/Data/Pages';

const profile: ProfilePageData = {
    entry: null,
    mustVerifyEmail: false,
    status: null,
};
const partialOperator: OperatorDashboardPageData = { entry: null };
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
        model: null,
        data: 'App\\Data\\SitemapData',
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
const operator: OperatorDashboardPageData = {
    // `format` and `artifact` are REQUIRED members of the generated `PageEntryData`, nullable in value
    // — which editor may open this entry, and where to read its compiled body (G2-BEAM-AUTHOR-ENTRY).
    // Required-but-nullable is deliberate: a host that forgets them fails to compile rather than
    // shipping a page whose editor guesses.
    entry: { id: 'entry-id', slug: 'operator-dashboard', format: null, artifact: null },
    staff: { name: 'Operator', email: 'operator@example.test' },
    stats: { users: 3, sitemaps: 2, entries: 4 },
};

// These must fail even if a generator starts emitting any or loses a nested reference.
// @ts-expect-error profile status is nullable text, not a number
const badStatus: ProfilePageData['status'] = 7;
// @ts-expect-error a lazy staff value is still a declared object
const badStaff: OperatorDashboardPageData['staff'] = 'operator';
const badStats: OperatorDashboardPageData['stats'] = {
    // @ts-expect-error nested counts must remain numeric
    users: '3',
    sitemaps: 2,
    entries: 4,
};
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
    partialOperator,
    operator,
    auth,
    reset,
    sitemap,
    badStatus,
    badStaff,
    badStats,
    badResource,
    badNav,
    badDemo,
];
