<?php

use App\Data\Pages\EntryPageData;
use App\Data\Pages\OperatorDashboardPageData;
use App\Data\Pages\OperatorStaffData;
use App\Data\Pages\OperatorStatsData;
use App\Http\Controllers\SitemapResourceController;
use App\Models\User;
use App\Support\PageEntryRef;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\LaravelData\Lazy;
use Splicewire\Beam\Facades\Particle;
use Splicewire\Beam\Ux\Models\BeamUxEntry;

// The FRONT DOOR is the OOTB site realm: `/` renders the promoted <SiteLayout> chrome (public) AND —
// behind the ux.author seam — mounts the in-place visual editor (@/editor). (frontend-surfaces wiring.)
//
// It shares `entry` ({id, slug}) so the page addresses its beam-ux row by ID (ADR-0214 §2). The server
// is the only party that can: no compile-time frontend map can carry a per-database uuid.
Route::get('/', fn () => Inertia::render('site/home', EntryPageData::from([
    'entry' => PageEntryRef::for('home'),
])))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The beam-ux entry-body transport (ADR-0214 §1) — load/save a page entry's particle body, the
    // server half `resources/js/editor`'s in-place editor (VisualEditorMount) round-trips through
    // (theme-entries-and-authoring STR-03: needed so an `ux.auth.author` holder can actually edit the
    // promoted auth pages' chrome). Two ordinary particle OPERATIONS on the `beam-ux-entry` resource,
    // addressed by the entry's **id**.
    //
    // This replaces `Route::beamUxEntries()` (beam-docs-satellite ticket 40). That macro mounted a
    // bespoke `GET|PUT beam/ux/entries/{slug}/body` controller, and it was bespoke for exactly one
    // reason: it addressed by SLUG where the particle pipeline addresses by id — which is what let a
    // namespaced and a null-namespace entry sharing one slug serve the WRONG row with no error. The
    // three starters were the LAST holders of that mount.
    //
    // Both ops declare their own `ability: 'ux.author'` with `abilityModel: false` (§3) — that
    // declaration is the real gate and it is what travels; this group is only auth + verified, exactly
    // as it was for the macro.
    //
    // The read mounts GET (§4): `Particle::ops()` defaults to POST regardless of kind, and the body read is
    // the hot path on every editor open, takes no input, and is idempotent. Because `EntryBodyShowOp`
    // declares `input: false`, a GET carrying ANY query key is a 422 — the retired `?namespace=`
    // disambiguator now fails loudly rather than being silently ignored, which is the point.
    //
    // Resource segment is the single hyphenated `beam-ux-entries`, NOT `beam-ux/entries`. The reason
    // it was CHOSEN is history: a `/` in a resource name broke Wayfinder's generated-helper
    // relative-import depth calculation, and Wayfinder is retired fleet-wide (beam-runbook ADR-0004,
    // 2026-08-27). The segment stays as it is because it is now load-bearing for a different reason —
    // it is in the live URIs and route names, and the front end addresses these ops by string literal.
    Particle::ops('beam-ux-entries', 'beam-ux-entry', 'body');
    Particle::ops('beam-ux-entries', 'beam-ux-entry', 'save-body');

    // The authed home IS the OOTB account realm: <AccountShell> (@splicewire/beam-ux/account). Fortify
    // redirects login here (`config/fortify.php` home => /dashboard). Shares `entry` for the same
    // reason `/` does — the seeded row is `dashboard`, not the slash-swapped component name.
    Route::get('dashboard', fn () => Inertia::render('account/home', EntryPageData::from([
        'entry' => PageEntryRef::for('dashboard'),
    ])))->name('dashboard');

    // ── The ACCOUNT-REALM settings surfaces: API tokens and Team ─────────────────────────────────
    //
    // Two more `account/*` pages, so app.tsx's layout resolution frames them in the same
    // <AccountShell> `/dashboard` gets, and their nav seats come from the SAME place its does —
    // `resources/beam-ux/nav.yml`'s `account` realm, seeded into the account sitemap. That is the
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
    Route::get('account/tokens', fn () => Inertia::render('account/tokens'))->name('account.tokens');
    Route::get('account/team', fn () => Inertia::render('account/team'))->name('account.team');

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

    // The host-owned Frame resource edit page for the editable sitemap (kind A).
    // Frame ships only frame/manifest; the host binds each resource's edit route.
    Route::get('frame/resources/sitemap', SitemapResourceController::class)
        ->name('frame.resources.sitemap');

    // The OPERATOR front-end realm (frontend-surfaces.md). A thin stats roll-up landing framed by the
    // promoted @splicewire/beam-mainframe host; resource lists ride Frame's generic particle CRUD socket.
    // Gated on the `os.operate` entitlement: the DefaultEntitlementResolver (laravel-beam-accounts) grants
    // it to a staff principal, so the seeded staff user reaches it and a non-staff user is 403'd.
    Route::get('operator', fn () => Inertia::render('operator/dashboard', OperatorDashboardPageData::from([
        'entry' => PageEntryRef::for('operator-dashboard'),
        'staff' => Lazy::closure(fn () => new OperatorStaffData(
            name: request()->user()->name,
            email: request()->user()->email,
        )),
        'stats' => Lazy::closure(fn () => new OperatorStatsData(
            users: User::count(),
            // Sitemap was retired (theme-entries-and-authoring BUX-03) - BeamUxEntry's own namespace='realms'
            // rows are the realm-root replacement; "entries" is every entry (root or not) in that stack.
            sitemaps: BeamUxEntry::where('namespace', 'realms')->count(),
            entries: rescue(fn () => BeamUxEntry::count(), 0, false),
        )),
    ])))->middleware('can:entitlement:os.operate')->name('operator.home');

    // The OS-SHELL desktop (frontend-surfaces.md). The windowed realm composer. Route-gated on the
    // projected `os.enter` entitlement (`can:entitlement:os.enter`); the shell itself does the fusion pivot
    // off shared `can['os.enter']` (entitled → desktop, else app-first). Staff hold it via the
    // DefaultEntitlementResolver; a non-staff user is 403'd and the OS realm is omitted from the manifest.
    Route::inertia('os', 'os')->middleware('can:entitlement:os.enter')->name('os.shell');
});

require __DIR__.'/settings.php';

// ── The TENANT FRAME CONSOLE mount ──────────────────────────────────────────────────────────────────
//
// One page component (`resources/js/pages/frame/console.tsx`) served at every path the tenant realm
// projects, so the client router in `resources/js/frame/router.tsx` can match the leaf. Which surface
// renders is the MANIFEST's decision, read from the same `routeContext` the nav's hrefs come from — a
// second server-side resource→page mapping here would be a copy that can drift from the nav.
//
// ⚠️ **The segment list is DERIVED, never written down.** `RouteContextProjector::hrefs('tenant')` is
// the same call the manifest's own hrefs come from, so a resource this host adds to
// `config('frame.realms')['tenant']` is mounted by adding it there and nowhere else. Writing the
// segments out would be the third copy of a list that already exists twice.
//
// ⚠️ **Registered BEFORE `Route::beamUxSite()` and constrained to those segments.** The renderer below
// takes a `{path}` catch-all; an unconstrained catch-all here would swallow every authored page.
// `rescue(..., [])` means a projector failure mounts NOTHING rather than mounting everything.
Route::middleware(['auth', 'verified'])->group(function () {
    $hrefs = rescue(function (): array {
        $paths = array_values(app(Splicewire\Beam\Ux\Frame\RouteContextProjector::class)->hrefs('tenant'));

        $nav = app(Splicewire\Beam\Ux\Frame\FrameNavContribution::class)->contributeNav('tenant');
        $sections = array_map(
            fn (array $item): ?string => $item['href'] ?? null,
            $nav['nav']['items'] ?? []
        );

        return array_filter([...$paths, ...$sections]);
    }, [], false);

    // Only the FIRST segment is enumerated; the optional second matches the `/:id` record twin. Every
    // tenant leaf is one of those two shapes (the projector emits `path` and `path/:id`, nothing
    // deeper), so this stays exact rather than greedy.
    $segments = array_values(array_unique(array_map(
        fn (string $href): string => explode('/', trim($href, '/'))[0],
        $hrefs
    )));

    if ($segments !== []) {
        Route::get('{frameRoute}', fn () => Inertia::render('frame/console'))
            ->where('frameRoute', '('.implode('|', array_map(fn (string $s): string => preg_quote($s, '/'), $segments)).')(\/[^\/]+)?')
            ->name('frame.console');
    }
});

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
