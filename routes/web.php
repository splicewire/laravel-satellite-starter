<?php

use App\Data\Pages\PlatformConnectionEndpointsData;
use App\Data\Pages\PlatformConnectionPageData;
use App\Data\Pages\SitemapExamplePageData;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Splicewire\Beam\Accounts\Support\TokensEndpoints;
use Splicewire\Beam\Agreements\Routing\AgreementsRoutes;
use Splicewire\Beam\Authorization\SeatGate;
use Splicewire\Beam\Ia\Side;
use Splicewire\Beam\Ia\Sides;
use Splicewire\Beam\Ux\Data\Pages\EntryPageData;
use Splicewire\Beam\Ux\Support\PageEntryRef;
use Splicewire\Satellite\Pairing\PlatformConnection;

require __DIR__.'/canonical-mdx.php';

// The opt-in home includes the entry's Preview, head and intake props.
Route::beamUxHome('site/home');

Route::middleware(['auth', 'verified'])->group(function () {
    // An explicit host consumer of Frame's write/read contracts; the writable /sitemap stays intact.
    Route::get('examples/sitemap-resource', fn () => Inertia::render('examples/sitemap-resource', SitemapExamplePageData::forMode('list')))
        ->name('examples.sitemap.index');
    Route::get('examples/sitemap-resource/new', fn () => Inertia::render('examples/sitemap-resource', SitemapExamplePageData::forMode('create')))
        ->name('examples.sitemap.create');
    Route::get('examples/sitemap-resource/{id}', fn (string $id) => Inertia::render('examples/sitemap-resource', SitemapExamplePageData::forMode('read', $id)))
        ->whereUuid('id')->name('examples.sitemap.show');

    // The host owns auth + verified; the packaged operations each declare ux.author.
    Splicewire\Beam\Ux\Support\EditorRoutes::mount();

    // The authed home IS the OOTB account realm, and it is the TENANT realm's dashboard leaf. Fortify
    // redirects login here (`config/fortify.php` home => /dashboard).
    //
    // `splicewire/laravel-beam-ux` registers one read-only `{realm}-dashboard` resource per realm and
    // mounts its list leaf at `/{realmBase}/dashboard` (realm-dashboards ticket 04) — for the tenant
    // realm, whose base is `/`, that is this path. The page is the same packaged frame console the
    // `{frameRoute}` catch-all below serves for every other tenant leaf, rendered with the same
    // (absent) props so the client reads one realm context and one manifest cache entry for the whole
    // realm; the console's client router matches `dashboard` and mounts the list shell, whose rows are
    // the realm's summary cards and jump-to tiles (`GET /frame/resources/tenant-dashboard`). Declared
    // here rather than left to the catch-all so the route keeps its name: Fortify, the tests and the
    // seeded account rail all address it as `dashboard`.
    //
    // This replaces the packaged `account/home` placeholder, and with it the `entry` share that page
    // carried: a frame list is not an authored page, so there is no chrome for the visual editor to
    // address. The `/` and `/account/theme` routes keep theirs.
    Route::get('dashboard', fn () => Inertia::render('frame/console'))->name('dashboard')->defaults(SeatGate::OPEN_TO_MEMBERS, true);

    // ── The ACCOUNT-REALM settings surfaces: API tokens and Team ─────────────────────────────────
    //
    // Two more `account/*` pages, so app.tsx's layout resolution frames them in <AccountShell>, and
    // their nav seats come from `resources/beam-ux/nav.yml`'s `account` realm, seeded into the account
    // sitemap. (`/dashboard` no longer shares that shell: since de4f17b it renders `frame/console`
    // under AppLayout, whose sidebar lists the same account seats.) That is the
    // whole reason these are account-realm pages rather than `settings/*` ones: the packaged
    // SettingsLayout's sub-nav (Profile / Security / Appearance) is a hardcoded list inside
    // `@splicewire/beam-inertia`, so a `settings/tokens` page would have been reachable only by URL,
    // which is the defect this change exists to fix.
    //
    // The page bodies are `@splicewire/beam-accounts`' own <TokensRoster> and <TeamPage>, wired to
    // the transport below. Nothing about either surface is re-implemented here — see
    // `resources/js/pages/account/{tokens,team}.tsx`, which are transport adapters and nothing else.
    //
    // ⚠️ These are NOT the tenant frame console's `tokens` / `members` / `invitations` leaves, and
    // those three have been removed from `config('frame.realms')['tenant']` in the same change. Read
    // that file's realm comment for why one capability may not have two surfaces here.
    // The tokens page's endpoints come from laravel-beam-accounts' own mounted routes, so the page follows
    // `ACCOUNT_API_ROOT` and writes no URL (frame-foundation 51).
    Route::get('account/tokens', fn () => Inertia::render('account/tokens', ['endpoints' => TokensEndpoints::props()]))->name('account.tokens')->defaults(SeatGate::OPEN_TO_MEMBERS, true);
    Route::get('account/team', fn () => Inertia::render('account/team'))->name('account.team')->defaults(SeatGate::OPEN_TO_MEMBERS, true);
    Route::get('account/agreements', fn () => Inertia::render('account/agreements', ['resource' => 'agreement-entries']))
        ->name('account.agreements')->defaults(SeatGate::OPEN_TO_MEMBERS, true);

    // Agreements resources inherit the host's authenticated and verified account boundary.
    AgreementsRoutes::mount();

    // ── The THEME entry's editor seat (G2-BEAM-THEME-NAV) ────────────────────────────────────────
    //
    // A third account-realm page, for the same reason the two above are account-realm pages: its nav
    // seat comes from `resources/beam-ux/nav.yml`'s `account` realm and `<AccountShell>` renders that
    // rail. Before this row, changing a theme token meant hand-editing `ThemeSeeder` and reseeding —
    // the theme entry is `type = theme`, and `UxType`'s own doctrine keeps a theme body out of the
    // visual canvas (its body is TOKENS, not a composable tree), so the editor dock had no page to
    // attach to and no surface anywhere mounted the row.
    //
    // ⚠️ **Not a bespoke theme UI, deliberately.** `theme-entries-and-authoring`'s PRD closed Part A §5
    // with the ruling that the theme editor is Frame's form on the `ParticleResource` — so the page
    // body is `@splicewire/beam-ux`'s own `<ThemeEditor>`, which is `useEntryBody` →
    // `RegionInspector`'s `form` body (the REAL `@schemastud/seam` SchemaForm) → Save draft and
    // explicit Publish through the SAME entry operations and fixed-version Release service mounted above. The fields are generated from the
    // server's schema (`EntryBodyEnvelope::schemaFor()` answers a theme entry with `ThemeSchemas`'
    // `{canvas, site}`), so adding a token in PHP adds a field here with no frontend change.
    //
    // It shares `entry` for the same reason `/` and `/dashboard` do, and it is the ONLY way this page
    // can work: the theme row's id is a per-database uuid, so only the server can say which row the
    // page means (`PageEntryRef::theme()`).
    //
    // **Gated on `can:ux.author`.** The `body`/`save-body` operations declare `ability: 'ux.author'`
    // and refuse a guest or a member on their own — that is the lock. This is the door: a screen whose
    // every read and write a principal would be refused should not be offered to them as a page.
    Route::get('account/theme', fn () => Inertia::render('account/theme', EntryPageData::from([
        'entry' => PageEntryRef::theme(),
    ])))->middleware('can:ux.author')->name('account.theme');

    // The account-tier REST survivors the two pages above talk to — tokens (reveal-once mint +
    // archive/rotate/renew), members (role change + remove) and invitations (send/resend/revoke),
    // all shipped by `splicewire/laravel-beam-accounts`.
    //
    // ⚠️ **A macro the HOST calls; the package mounts nothing.** api-surface-coherence 141 ruled that
    // middleware is part of an exposure and "the route file owns it", and these verbs mint bearer
    // credentials and change who can reach a team. It is also what keeps the package from deciding,
    // by provider order, which `beam.accounts.tokens.index` a host that already mounts its own
    // (`~/Herd/splicewire-app`) actually serves.
    //
    // Called INSIDE this group, so the surface inherits `auth` + `verified` from it; the macro's own
    // middleware default is dropped by passing an explicit empty list rather than being applied
    // twice.
    Route::splicewireAccountApiRoutes(middleware: []);

    // The PLATFORM CONNECTION surface — the satellite half of the RFC 8628 pairing with a tower, as
    // an operator-realm screen rather than only `php artisan splicewire:connect`.
    //
    // The page component is `@splicewire/beam-inertia`'s `operator/platform-connection` (resolved
    // from the package map, not published here — see `beam-inertia/src/pages.ts`), and every moving
    // part of the pairing lives in `splicewire/laravel-satellite`'s `Pairing\` namespace. This mount
    // supplies the two things only a host can: WHERE the page lives, and where the four operations
    // it drives were mounted (below).
    //
    // ⚠️ `snapshot(probeIdentity: true)` costs one round trip to the tower on page load, and it is
    // the round trip that makes this screen honest: a stored credential that has been archived at
    // the tower is byte-identical here, so "paired" without a probe is a claim the satellite cannot
    // support. The probe is what turns it into `revoked` when it is.
    // The CLIENT side of pairing (ux-walkthrough UX-07, IA-6), like the operations the macro below mounts.
    Sides::serve(Side::Client, 'Splicewire connection screen', function () {
        Route::get('operator/platform-connection', fn () => Inertia::render(
            'operator/platform-connection',
            PlatformConnectionPageData::from([
                'connection' => app(PlatformConnection::class)->snapshot(probeIdentity: true),
                'endpoints' => PlatformConnectionEndpointsData::fromRoutes(),
            ]),
        ))->middleware('can:entitlement:os.operate')->name('operator.platform-connection');
    });

    // The four declared #[ParticleOp]s the screen above drives, from
    // `splicewire/laravel-satellite`'s own route file.
    //
    // ⚠️ **A macro the HOST calls; the package mounts nothing** — the same rule
    // `Route::splicewireAccountApiRoutes()` follows one surface up, and for a stronger reason: these
    // verbs WRITE a bearer credential into this host's `.env`. A package that mounted them would be
    // deciding, for every satellite that installs it, that an operator-realm pairing surface exists.
    //
    // Called INSIDE this group, so `web` + `auth` + `verified` are already applied; the explicit
    // middleware list adds only the entitlement, which is the same one each operation declares
    // (`ability: 'entitlement:os.operate'`, `abilityModel: false`). Door and operation agree, so a
    // direct POST from a member is refused by the operation itself and not merely hidden from the rail.
    Route::splicewirePlatformConnectionRoutes('operator', ['can:entitlement:os.operate']);

    // The OS-SHELL desktop (frontend-surfaces.md). The windowed realm composer. Route-gated on the
    // projected `os.enter` entitlement (`can:entitlement:os.enter`); the shell itself does the fusion pivot
    // off shared `can['os.enter']` (entitled → desktop, else app-first). Staff hold it via the
    // DefaultEntitlementResolver; a non-staff user is 403'd and the OS realm is omitted from the manifest.
    Route::inertia('os', 'os')->middleware('can:entitlement:os.enter')->name('os.shell');
});

// ── Getting onto a team: `teams.create` and the emailed `invitations.accept` link ───────────────────
//
// `splicewire/laravel-beam-accounts`' self-service team creation (the `teams.create` page + the
// `CreateTeam` op at `teams.store`) and invitation acceptance (the signed `invitations.accept` page every
// sent invitation now emails + the `RedeemInvitation` op at `invitations.redeem`). A fresh registration
// here provisions NO team, so these are how a new user gets onto one; `DashboardWelcome` offers
// "Create a team" and mentions invitation links only because these two names exist.
//
// OUTSIDE the `auth` group above on purpose: the accept page must open for a guest, who registers or
// logs in from it and is returned to the link (it is stored as the intended URL). The three signed-in
// routes carry the same `auth` + `verified` as the group; the guest page adds nothing to `web`.
// Mounted here, ahead of the frame console's `{frameRoute}` and the entry renderer's catch-alls.
Route::splicewireTeamRoutes(middleware: ['auth', 'verified'], guestMiddleware: []);

require __DIR__.'/settings.php';

// The tenant console derives its segments from the realm before the site's catch-all.
Route::beamRealmConsole('tenant', ['auth', 'verified']);

// ── The OPERATOR FRAME CONSOLE mount ────────────────────────────────────────────────────────────────
//
// `splicewire/laravel-beam-ux`'s realm-console macro: the operator realm's front door (`operator.home`, a redirect
// to its dashboard leaf), its manifest (`operator.frame.manifest`, frame's own controller with `defaults('realm')`)
// and its console (`operator.frame.console`, constrained to the realm's projected leaves and declared seats, with
// `RealmConsolePageData`'s three props). The door is the realm's own gate (`can:entitlement:os.operate`), so the
// console cannot be a softer door than the dashboard behind it. Registered BEFORE `Route::beamUxSite()`, whose
// `{path}` catch-all would otherwise take the realm's URLs.
//
Route::beamRealmConsole('operator', ['auth', 'verified']);

// The PUBLIC ENTRY RENDERER (ADR-0209 §2) — resolves any unclaimed URL against the `site` realm's
// containment tree and renders the entry through `resources/js/pages/site/entry.tsx`. This is what
// makes the seeded `/docs`, `/docs/api` and `/docs/mcp` live on a fresh install, and what serves every
// page authored after it.
//
// **LAST, and that is load-bearing.** It registers a `{path}` catch-all, so every named route above
// must already be declared or it gets swallowed. `claimRoot: false` (the default) leaves `/` to the
// `site/home` route above; a site served WHOLLY from entries passes `claimRoot: true` instead.
//
// It also mounts the compiled-artifact route the page shell imports, above its own catch-all. The
// renderer 404s uniformly on anything it cannot resolve, gate, and read — so an incumbent catch-all
// of your own must be registered BELOW this line, or `/{any}` never sees a request again.
Route::beamUxSite('site/entry');
