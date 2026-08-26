<?php

use App\Http\Controllers\SitemapResourceController;
use App\Models\User;
use App\Support\PageEntryRef;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Splicewire\Beam\Ux\Models\BeamUxEntry;

// The FRONT DOOR is the OOTB site realm: `/` renders the promoted <SiteLayout> chrome (public) AND —
// behind the ux.author seam — mounts the in-place visual editor (@/editor). (frontend-surfaces wiring.)
//
// It shares `entry` ({id, slug}) so the page addresses its beam-ux row by ID (ADR-0214 §2). The server
// is the only party that can: no compile-time frontend map can carry a per-database uuid.
Route::get('/', fn () => Inertia::render('site/home', [
    'entry' => PageEntryRef::for('home'),
]))->name('home');

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
    // The read mounts GET (§4): `particleOp` defaults to POST regardless of kind, and the body read is
    // the hot path on every editor open, takes no input, and is idempotent. Because `EntryBodyShowOp`
    // declares `input: false`, a GET carrying ANY query key is a 422 — the retired `?namespace=`
    // disambiguator now fails loudly rather than being silently ignored, which is the point.
    //
    // Resource segment is the single hyphenated `beam-ux-entries`, NOT `beam-ux/entries`: a `/` in a
    // resource name breaks Wayfinder's generated-helper relative-import depth calculation.
    Route::particleOp('beam-ux-entries', 'beam-ux-entry', 'body', ['method' => 'get']);
    Route::particleOp('beam-ux-entries', 'beam-ux-entry', 'save-body');

    // The authed home IS the OOTB account realm: <AccountShell> (@splicewire/beam-ux/account). Fortify
    // redirects login here (`config/fortify.php` home => /dashboard). Shares `entry` for the same
    // reason `/` does — the seeded row is `dashboard`, not the slash-swapped component name.
    Route::get('dashboard', fn () => Inertia::render('account/home', [
        'entry' => PageEntryRef::for('dashboard'),
    ]))->name('dashboard');

    // The host-owned Frame resource edit page for the editable sitemap (kind A).
    // Frame ships only frame/manifest; the host binds each resource's edit route.
    Route::get('frame/resources/sitemap', SitemapResourceController::class)
        ->name('frame.resources.sitemap');

    // The OPERATOR front-end realm (frontend-surfaces.md). A thin stats roll-up landing framed by the
    // promoted @splicewire/beam-mainframe host; resource lists ride Frame's generic particle CRUD socket.
    // Gated on the `os.operate` entitlement: the DefaultEntitlementResolver (laravel-beam-accounts) grants
    // it to a staff principal, so the seeded staff user reaches it and a non-staff user is 403'd.
    Route::get('operator', fn () => Inertia::render('operator/dashboard', [
        'entry' => PageEntryRef::for('operator-dashboard'),
        'staff' => fn () => ['name' => request()->user()->name, 'email' => request()->user()->email],
        'stats' => fn () => [
            'users' => User::count(),
            // Sitemap was retired (theme-entries-and-authoring BUX-03) - BeamUxEntry's own namespace='realms'
            // rows are the realm-root replacement; "entries" is every entry (root or not) in that stack.
            'sitemaps' => BeamUxEntry::where('namespace', 'realms')->count(),
            'entries' => rescue(fn () => BeamUxEntry::count(), 0, false),
        ],
    ]))->middleware('can:entitlement:os.operate')->name('operator.home');

    // The OS-SHELL desktop (frontend-surfaces.md). The windowed realm composer. Route-gated on the
    // projected `os.enter` entitlement (`can:entitlement:os.enter`); the shell itself does the fusion pivot
    // off shared `can['os.enter']` (entitled → desktop, else app-first). Staff hold it via the
    // DefaultEntitlementResolver; a non-staff user is 403'd and the OS realm is omitted from the manifest.
    Route::inertia('os', 'os')->middleware('can:entitlement:os.enter')->name('os.shell');
});

require __DIR__.'/settings.php';

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
