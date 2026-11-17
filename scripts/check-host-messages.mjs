// localization 35 (S1): the starter's converted pages read their copy through the messages seam, and with no catalog served they
// render EXACTLY the English they rendered before. Run: `pnpm test:messages` (node; the starter's own pinned vite, no new deps).
//
// WHAT IT PROVES, and its LIMIT (coord 13:28Z; the 28b lesson "test the real mount"):
//   - each converted page, rendered under a pseudo-locale MessageProvider, marks EVERY line of its copy (it reads the seam);
//   - rendered with NO provider, it is BYTE-IDENTICAL to the page as it was before the conversion (the original file from the
//     parent of the commit that introduced lib/messages.ts, rendered by the same harness): English parity;
//   - the call-argument strings read the seam: the team and tokens toast fallbacks are EXERCISED through the callbacks the
//     pages pass down; the review page's errors (behind a click or a selected Release) and the parameterised title are proven at
//     the catalog level, every
//     key through the same runtime the pages use (createMessages: English with no catalog, one marked line under pseudo);
//   It does NOT prove that the starter's real app MOUNTS a provider carrying the served catalog: that is launch ticket 59's half
//   (the page carries an initial client catalog) and the pseudo-locale walkthrough's (ticket 20), never this script's.
//
// How: vite's own SSR module loader (createServer + ssrLoadModule) loads each page with React, react-dom/server,
// @schemastud/messages, the page and its catalog REAL; the page's other imports (Inertia, the beam packages, sonner, the host's
// API clients) are tiny VIRTUAL stubs that render their children and capture the callbacks, so the check measures exactly the copy.
import { execFileSync } from 'node:child_process';
import { readFileSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const PAGES = [
    'pages/account/team.tsx',
    'pages/account/tokens.tsx',
    'pages/account/canonical-mdx-review.tsx',
    'pages/examples/sitemap-resource.tsx',
];
// modules outside the pages that read the catalog (S4: the sitemap transport's user-facing error)
const SOURCES = ['examples/sitemap-transport.ts'];
const git = (...args) =>
    execFileSync('git', args, { cwd: root, encoding: 'utf8' }).trim();

// the 'before' of each PAGE: the parent of the FIRST commit whose version of that page reads the seam (`git log -S useHostMessages`,
// oldest first), or HEAD while its conversion is still uncommitted. Never the converted tree itself (a comparison with itself proves
// nothing). Per page, because a downstream starter's pages are converted by different commits (its own, and the lineage root's that
// a `git merge upstream/main` brings): one base for every page crashed after a real lineage merge (review of S3, 15:02Z).
// HOST_MESSAGES_BASE overrides it for EVERY page, but only with an ANCESTOR of HEAD; each resolved sha is printed.
const verify = (rev) => git('rev-parse', '--verify', `${rev}^{commit}`);
const OVERRIDE = process.env.HOST_MESSAGES_BASE
    ? verify(process.env.HOST_MESSAGES_BASE)
    : null;

if (OVERRIDE) {
    try {
        git('merge-base', '--is-ancestor', OVERRIDE, 'HEAD');
    } catch {
        console.error(
            `FAIL HOST_MESSAGES_BASE ${OVERRIDE} is not an ancestor of HEAD`,
        );
        process.exit(1);
    }
}

const baseFor = (p) => {
    if (OVERRIDE) {
        return OVERRIDE;
    }

    const first = git(
        'log',
        '--reverse',
        '--format=%H',
        '-S',
        'useHostMessages',
        '--',
        `resources/js/${p}`,
    ).split('\n')[0];

    return verify(first ? `${first}^` : 'HEAD');
};
const BASES = Object.fromEntries(PAGES.map((p) => [p, baseFor(p)]));

for (const p of PAGES) {
    console.log(`base ${BASES[p]} for ${p} (the page before its conversion)`);
    const was = git('show', `${BASES[p]}:resources/js/${p}`);

    if (was.includes('useHostMessages')) {
        console.error(
            `FAIL the base ${BASES[p]} already has the converted ${p}: no 'before' to compare with`,
        );
        process.exit(1);
    }
}

// the virtual stubs: render children, expose what the page passes down
const STUBS = {
    '@inertiajs/react': `import { createElement } from 'react';
        export const Head = ({ title }) => createElement('title', null, title);
        export const usePage = () => ({ props: { auth: { user: globalThis.__noUser ? null : { id: 1 } } } });
        export const Link = ({ href, children }) => createElement('a', { href }, children);
        export const router = { reload() {} };`,
    sonner: `export const toast = { success: (m) => globalThis.__toasts.push(['success', m]), error: (m) => globalThis.__toasts.push(['error', m]) };`,
    '@splicewire/beam-accounts': `import { createElement, Fragment } from 'react';
        export const TeamProvider = ({ services, children }) => { globalThis.__services = services; return createElement(Fragment, null, children); };
        export const TokensProvider = TeamProvider;
        export const TeamPage = () => createElement('section', null, 'team-body');
        export const TokensRoster = () => createElement('section', null, 'tokens-body');
        export const createTokensClient = () => ({});`,
    '@schemastud/frame/session': `export const createSessionRequest = () => () => null;`,
    '@splicewire/beam-inertia': `export const bodyClient = {};`,
    '@splicewire/beam-ux': `import { createElement } from 'react';
        export const ReleasePanel = ({ onCommand }) => { globalThis.__onCommand = onCommand; return createElement('aside', null, 'release-panel'); };`,
    '@/lib/accounts-api': `export const teamClient = { stubbedBy: 'host-messages-stubs' };`,
    '@tanstack/react-query': `export const useQuery = () => globalThis.__query;`,
    '@schemastud/frame': `import { createElement, Fragment } from 'react';
        export const FrameProvider = ({ value, children }) => { globalThis.__injection = value; return createElement(Fragment, null, children); };
        export const DefaultFormBody = ({ schema }) => createElement('form', { title: schema.title }, Object.values(schema.properties ?? {}).map((f) => createElement('label', { key: f.title }, f.title)));
        export const ListShell = () => createElement('section', null, 'frame-shell');
        export const EditShell = ListShell;
        export const createWidgetRegistry = () => ({});
        export const useBrowserUrlState = () => [{}, () => {}];
        export const createResourceTransport = (impl) => impl;
        export const parseResourcePage = (x) => x;`,
    '@schemastud/frame/shadcn': `export const shadcnEditSlots = {}; export const shadcnListSlots = {};`,
    '@schemastud/ui': `import { createElement } from 'react';
        const C = (tag) => ({ children, onClick }) => createElement(tag, { onClick }, children);
        export const Button = C('button'); export const Input = C('input'); export const Label = C('label'); export const Badge = C('span');
        export const Skeleton = C('div'); export const Popover = C('div'); export const PopoverTrigger = C('div'); export const PopoverContent = C('div');
        export const SimpleSelect = C('select');`,
};
const stubPlugin = {
    name: 'host-messages-stubs',
    enforce: 'pre',
    resolveId(id) {
        // vite's alias runs FIRST, so an '@/lib/x' import arrives here as '<root>/resources/js/lib/x': map it back to its '@/' key
        // (before this the '@/' stubs silently never applied: harmless where the real module loads cleanly, wrong where it does not)
        const key = id.startsWith(root + '/resources/js/')
            ? '@/' +
              id
                  .slice((root + '/resources/js/').length)
                  .replace(/\.(tsx?|mjs|js)$/, '')
            : id;

        return Object.hasOwn(STUBS, key) ? '\0stub:' + key : null;
    },
    load(id) {
        return id.startsWith('\0stub:') ? STUBS[id.slice(6)] : null;
    },
};

// the ORIGINAL pages, from git, into a scratch dir inside the project (so '@/' resolves as for the real ones); removed after
const ORIG = resolve(root, 'scripts/.host-messages-original');
rmSync(ORIG, { recursive: true, force: true });

for (const p of PAGES) {
    const text = execFileSync(
        'git',
        ['show', `${BASES[p]}:resources/js/${p}`],
        {
            cwd: root,
            encoding: 'utf8',
        },
    );
    mkdirSync(dirname(resolve(ORIG, p)), { recursive: true });
    writeFileSync(resolve(ORIG, p), text);
}

const server = await createServer({
    root,
    configFile: false,
    logLevel: 'error',
    appType: 'custom',
    server: { middlewareMode: true, hmr: false },
    resolve: {
        alias: [
            { find: /^@\//, replacement: resolve(root, 'resources/js') + '/' },
        ],
    },
    esbuild: { jsx: 'automatic' },
    plugins: [stubPlugin],
    optimizeDeps: { noDiscovery: true, include: [] },
    // React and the messages runtime load through NODE (externalized), ONE instance each, never inlined by vite's runner
    // and the STUBBED packages are never externalized, so the stub plugin's resolveId sees them
    ssr: {
        external: ['react', 'react-dom', '@schemastud/messages'],
        noExternal: Object.keys(STUBS).filter((k) => !k.startsWith('@/')),
    },
});
let failed = 0;
const check = (name, cond, got) => {
    if (cond) {
        console.log('PASS ' + name);
    } else {
        failed++;
        console.log(
            'FAIL ' +
                name +
                (got === undefined
                    ? ''
                    : ': ' + JSON.stringify(got).slice(0, 400)),
        );
    }
};

try {
    const { createElement } = await import('react');
    const { renderToStaticMarkup } = await import('react-dom/server');
    const { MessageProvider } = await import('@schemastud/messages/react');
    const { hostMessageDefaults } = await server.ssrLoadModule(
        '/resources/js/lib/messages.ts',
    );
    // the en-XA catalog as the server serves a pseudo-locale (BeamTranslator marks the line; the runtime's pseudo flag does the same
    // here): every host key, its English as the line, rendered with pseudo=true
    const catalog = {
        version: 1,
        locale: 'en-XA',
        realm: null,
        direction: 'ltr',
        messages: Object.fromEntries(
            Object.entries(hostMessageDefaults).map(([k, v]) => [
                k,
                { text: v, sourceLocale: 'en' },
            ]),
        ),
    };
    // the sitemap example's state per render: loaded by default (create mode, the Back link shown), overridden per state below
    const LOADED = {
        isPending: false,
        isError: false,
        data: { contexts: { sitemap: { can: { create: true } } } },
        refetch() {},
    };
    globalThis.__query = LOADED;
    const pageProps = {
        manifestUrl: '/frame/manifest',
        resourceUrl: '/api/sitemap',
        schemaUrl: '/api/sitemap/schema',
        recordUrlTemplate: '/api/sitemap/__id__',
        mode: 'create',
        listHref: '/examples/sitemap-resource',
        endpoints: {},
        entryId: 'e1',
        title: 'Guide',
        versions: [],
        releases: [{ id: 'r1', title: 'Spring', status: 'draft' }],
    };
    const render = async (file, withProvider) => {
        globalThis.__toasts = [];
        globalThis.__services = null;
        globalThis.__onCommand = null;
        const Page = (await server.ssrLoadModule(file)).default;
        const tree = createElement(Page, pageProps);
        const html = renderToStaticMarkup(
            withProvider
                ? createElement(
                      MessageProvider,
                      { value: catalog, pseudo: true },
                      tree,
                  )
                : tree,
        );

        return {
            html,
            services: globalThis.__services,
            onCommand: globalThis.__onCommand,
        };
    };
    // the visible copy of a rendered page: its text nodes and copy attributes, in order
    const copyOf = (html) =>
        [...html.matchAll(/>([^<>]+)</g)]
            .map((m) => m[1].trim())
            .filter(Boolean)
            .concat(
                [
                    ...html.matchAll(
                        /(?:aria-label|title|placeholder|alt)="([^"]*)"/g,
                    ),
                ].map((m) => m[1]),
            );
    const DATA = new Set([
        'frame-shell',
        'team-body',
        'tokens-body',
        'release-panel',
        'Guide',
        'Spring (draft)',
    ]); // stub output + page DATA, not copy

    for (const p of PAGES) {
        const before = await render(
            '/scripts/.host-messages-original/' + p,
            false,
        );
        const plain = await render('/resources/js/' + p, false);
        const pseudo = await render('/resources/js/' + p, true);
        check(
            `${p}: with NO provider, byte-identical to the page before the conversion`,
            plain.html === before.html,
            { before: before.html, after: plain.html },
        );
        const unmarked = copyOf(pseudo.html).filter(
            (t) => !DATA.has(t) && !/^⟦[\s\S]*⟧$/.test(t),
        );
        check(
            `${p}: under the pseudo provider EVERY copy line is marked`,
            unmarked.length === 0 &&
                copyOf(pseudo.html).some((t) => t.startsWith('⟦')),
            unmarked,
        );

        // the call-argument strings (the ratchet does not count them; review note): read through the seam too
        if (pseudo.services?.onError) {
            globalThis.__toasts = [];
            pseudo.services.onError('not an Error');
            check(
                `${p}: the toast fallback is the marked catalog line`,
                /^⟦[\s\S]*⟧$/.test(globalThis.__toasts[0]?.[1] ?? ''),
                globalThis.__toasts,
            );
            globalThis.__toasts = [];
            plain.services.onError('not an Error');
            check(
                `${p}: ... and plain English with no provider`,
                globalThis.__toasts[0]?.[1] === 'Something went wrong.',
                globalThis.__toasts,
            );
        }

        if (pseudo.onCommand) {
            const err = await pseudo.onCommand({}).then(
                () => null,
                (e) => e.message,
            );
            check(
                `${p}: a thrown error (no release) is the marked catalog line`,
                /^⟦[\s\S]*⟧$/.test(err ?? ''),
                err,
            );
            const errPlain = await plain.onCommand({}).then(
                () => null,
                (e) => e.message,
            );
            check(
                `${p}: ... and plain English with no provider`,
                errPlain === 'Release commands are unavailable.',
                errPlain,
            );
        }
    }

    // EVERY catalog line through the runtime directly (review of 590e7f5b, MEDIUM): the page renders above reach the toast fallback
    // and ONE thrown error through the callbacks the page passes down; the other errors (the create / refresh paths need a click)
    // and the parameterised title are proven here, at the catalog level, with the same runtime the pages use
    const { createMessages } = await import('@schemastud/messages');
    const plainRt = createMessages(hostMessageDefaults);
    const pseudoRt = createMessages(hostMessageDefaults, {
        catalog,
        pseudo: true,
    });
    const keys = Object.keys(hostMessageDefaults);
    check(
        'every key, with NO catalog, is its English default',
        keys
            .filter((k) => k !== 'mdx_review.release_default_title')
            .every((k) => plainRt.t(k) === hostMessageDefaults[k]),
    );
    check(
        'every key, under the pseudo-locale, is ONE marked line',
        keys.every((k) =>
            /^⟦[\s\S]*⟧$/.test(pseudoRt.t(k, { title: 'Guide' })),
        ),
    );
    check(
        "the default Release title takes its parameter: 'Review: Guide'",
        plainRt.t('mdx_review.release_default_title', { title: 'Guide' }) ===
            'Review: Guide',
    );
    check(
        'a parameter value is never re-substituted or regex-expanded',
        plainRt.t('mdx_review.release_default_title', {
            title: 'a :title $& b',
        }) === 'Review: a :title $& b',
        plainRt.t('mdx_review.release_default_title', {
            title: 'a :title $& b',
        }),
    );
    // ':Title' IS one of the runtime's own replacement tokens for the parameter `title` (it replaces ':Title' with the
    // ucfirst value), so a runtime that re-substituted inside a value would rewrite it ('Review: :Title' would become
    // 'Review: :Title' -> ucfirst(':Title')); it must come out exactly as given
    check(
        "a value that looks like a replacement token stays as given (':Title')",
        plainRt.t('mdx_review.release_default_title', { title: ':Title' }) ===
            'Review: :Title',
        plainRt.t('mdx_review.release_default_title', { title: ':Title' }),
    );
    check(
        'the marked title keeps the parameter inside the mark',
        /^⟦[\s\S]*Guide[\s\S]*⟧$/.test(
            pseudoRt.t('mdx_review.release_default_title', { title: 'Guide' }),
        ),
    );

    // the '@/' stubs APPLY (vite's alias rewrites '@/lib/x' before resolveId; S1 follow-up): the team page holds the STUBBED client
    const team = await render('/resources/js/pages/account/team.tsx', false);
    check(
        "an '@/lib' stub applies (the team page's client is the accounts-api stub)",
        team.services?.client?.stubbedBy === 'host-messages-stubs',
        team.services?.client,
    );

    // the catalog: every key the pages use exists, and every key is used (no dead lines)
    // S4: the sitemap example in EVERY state, byte-identical with no provider and fully marked under pseudo
    const SITEMAP = 'pages/examples/sitemap-resource.tsx';

    // a REAL request failure for the error state: the real frameJson with fetch stubbed to a 404 (never a hand-made message)
    globalThis.document ??= { cookie: '' };
    const realFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: false,
        status: 404,
        json: async () => ({}),
    });
    const { frameJson: realFrameJson } = await server.ssrLoadModule(
        '/resources/js/examples/sitemap-transport.ts',
    );
    const httpError = await realFrameJson('/frame/manifest').then(
        () => null,
        (e) => e,
    );
    check(
        'a real failed request is the English request line',
        httpError?.message === 'GET /frame/manifest failed (404)',
        httpError?.message,
    );

    for (const [label, q] of [
        ['pending', { isPending: true }],
        [
            'error (a real 404)',
            { isPending: false, isError: true, error: httpError, refetch() {} },
        ],
        [
            'no access',
            { isPending: false, isError: false, data: { contexts: {} } },
        ],
    ]) {
        globalThis.__query = q;
        const before = await render(
            '/scripts/.host-messages-original/' + SITEMAP,
            false,
        );
        const plain = await render('/resources/js/' + SITEMAP, false);
        const pseudo = await render('/resources/js/' + SITEMAP, true);
        check(
            `${SITEMAP} (${label}): with NO provider, byte-identical to before`,
            plain.html === before.html,
            { before: before.html, after: plain.html },
        );
        const unmarked = copyOf(pseudo.html).filter(
            (x) => !DATA.has(x) && !/^⟦[\s\S]*⟧$/.test(x),
        );
        check(
            `${SITEMAP} (${label}): under pseudo EVERY copy line is marked`,
            unmarked.length === 0,
            unmarked,
        );
    }

    globalThis.__query = LOADED;
    // its FORM: the field titles come from the FormBody slot the page injects into Frame; rendered against the ORIGINAL page's slot
    const schema = {
        title: 'from the schema',
        properties: {
            id: { title: 'id' },
            label: { title: 'label' },
            href: { title: 'href' },
            order: { title: 'order' },
            externalUrl: { title: 'externalUrl' },
        },
    };
    const formOf = async (file, withProvider) => {
        await render(file, withProvider);
        const Body = globalThis.__injection.editSlots.FormBody;
        const el = createElement(Body, { schema });

        return renderToStaticMarkup(
            withProvider
                ? createElement(
                      MessageProvider,
                      { value: catalog, pseudo: true },
                      el,
                  )
                : el,
        );
    };
    const formBefore = await formOf(
        '/scripts/.host-messages-original/' + SITEMAP,
        false,
    );
    check(
        `${SITEMAP}: the form's field titles, with NO provider, byte-identical to before`,
        (await formOf('/resources/js/' + SITEMAP, false)) === formBefore,
        formBefore,
    );
    const formPseudo = await formOf('/resources/js/' + SITEMAP, true);
    const formUnmarked = copyOf(formPseudo).filter(
        (x) => !/^⟦[\s\S]*⟧$/.test(x),
    );
    check(
        `${SITEMAP}: under pseudo every field title (and the form title) is marked`,
        formUnmarked.length === 0 && /⟦/.test(formPseudo),
        formUnmarked,
    );
    // its TRANSPORT's user-facing error, end to end: no signed-in user -> the transport the page built rejects with the seam's line
    globalThis.__noUser = true;

    for (const [withProvider, expect, label] of [
        [true, (m) => /^⟦[\s\S]*⟧$/.test(m), 'marked'],
        [
            false,
            (m) => m === 'Sign in before using the Sitemap example.',
            'plain English',
        ],
    ]) {
        await render('/resources/js/' + SITEMAP, withProvider);
        const msg = await globalThis.__injection.transport
            .get('sitemap', 1)
            .then(
                () => null,
                (e) => e.message,
            );
        check(
            `${SITEMAP}: the transport's sign-in error, from the page's own transport, is ${label}`,
            expect(msg ?? ''),
            msg,
        );
    }

    globalThis.__noUser = false;
    // the page's OWN transport and schemaFetcher on a real 500: the keyed request line (marked under pseudo, English with none)
    globalThis.fetch = async () => ({
        ok: false,
        status: 500,
        json: async () => ({}),
    });

    for (const [withProvider, expect, label] of [
        [
            true,
            (m) =>
                /^⟦[\s\S]*⟧$/.test(m) &&
                m.includes('/api/sitemap') &&
                m.includes('500'),
            'marked, method/url/status inside',
        ],
        [
            false,
            (m) => m === 'GET /api/sitemap failed (500)',
            'the English request line',
        ],
    ]) {
        await render('/resources/js/' + SITEMAP, withProvider);
        const listMsg = await globalThis.__injection.transport
            .list('sitemap', {})
            .then(
                () => null,
                (e) => e.message,
            );
        check(
            `${SITEMAP}: the transport's failed request is ${label}`,
            expect(listMsg ?? ''),
            listMsg,
        );
        const schemaMsg = await globalThis.__injection
            .schemaFetcher('/api/sitemap')
            .then(
                () => null,
                (e) => e.message,
            );
        check(
            `${SITEMAP}: schemaFetcher's failed request is ${label}`,
            expect(schemaMsg ?? ''),
            schemaMsg,
        );
    }

    globalThis.fetch = realFetch;
    // the transport is built ONCE: its memo depends on the stable text function, never on the messages (no rebuild per catalog)
    // (S4 confirm nit 3) read with readFileSync, and take EXACTLY the useMemo(...) call by matching its parentheses, never a slice
    // up to whatever declaration happens to come next
    const pageSrc = readFileSync(
        resolve(root, 'resources/js', SITEMAP),
        'utf8',
    );
    const memoAt = pageSrc.indexOf('const transport = useMemo(');
    const memo = (() => {
        if (memoAt < 0) {
            return '';
        }

        const open = pageSrc.indexOf('(', memoAt);

        for (let i = open, depth = 0; i < pageSrc.length; i++) {
            if (pageSrc[i] === '(') {
                depth++;
            } else if (pageSrc[i] === ')' && --depth === 0) {
                return pageSrc.slice(memoAt, i + 1);
            }
        }

        return '';
    })();
    check(
        `${SITEMAP}: the transport memo depends on the stable text, not on the messages`,
        /\btext,/.test(memo) && !/\bmessages\b/.test(memo),
        memo.slice(-200),
    );

    // the record NOUN Frame shows ('New link', …): the manifest the page injects carries it through the seam
    for (const [withProvider, expect, label] of [
        [true, (s) => /^⟦[\s\S]*⟧$/.test(s), 'marked'],
        [false, (s) => s === 'link', "'link'"],
    ]) {
        await render('/resources/js/' + SITEMAP, withProvider);
        const noun =
            globalThis.__injection.manifestFor('sitemap')?.singularLabel;
        check(
            `${SITEMAP}: the injected manifest's singularLabel is ${label}`,
            expect(noun ?? ''),
            noun,
        );
    }

    const src = PAGES.concat(SOURCES)
        .map((p) =>
            execFileSync('cat', [resolve(root, 'resources/js', p)], {
                encoding: 'utf8',
            }),
        )
        .join('\n');
    const used = new Set(
        [...src.matchAll(/(?:messages\.t|text)\(\s*'([a-z_]+\.[a-z_]+)'/g)].map(
            (m) => m[1],
        ),
    );
    const declared = new Set(Object.keys(hostMessageDefaults));
    check(
        'every key a page reads is in messages.default.json',
        [...used].every((k) => declared.has(k)),
        [...used].filter((k) => !declared.has(k)),
    );
    check(
        'every key in messages.default.json is read by a page',
        [...declared].every((k) => used.has(k)),
        [...declared].filter((k) => !used.has(k)),
    );
    check(
        'every key is host grammar (unnamespaced group.item)',
        [...declared].every((k) => /^[a-z][a-z_]*\.[a-z][a-z0-9_]*$/.test(k)),
        [...declared],
    );
} finally {
    await server.close();
    rmSync(ORIG, { recursive: true, force: true });
}

console.log(
    failed ? `host messages: ${failed} FAILED` : 'host messages: ALL OK',
);
process.exit(failed ? 1 : 0);
