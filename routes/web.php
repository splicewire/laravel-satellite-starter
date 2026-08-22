<?php

use App\Http\Controllers\SitemapResourceController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Splicewire\Beam\Ux\Models\BeamUxEntry;

// The FRONT DOOR is the OOTB site realm: `/` renders the promoted <SiteLayout> chrome (public) AND —
// behind the ux.author seam — mounts the in-place visual editor (@/editor). (frontend-surfaces wiring.)
Route::inertia('/', 'site/home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The beam-ux entry-body transport (beam.ux.entries.body.*) — load/save a page entry's particle
    // body, the server half `resources/js/editor`'s in-place editor (VisualEditorMount) round-trips
    // through (theme-entries-and-authoring STR-03: needed so an `ux.auth.author` holder can actually
    // edit the promoted auth pages' chrome). The macro + controller live in the beam-ux PACKAGE; this
    // app only mounts it, inside its own auth group — the package's PolicyWriteGate is permissive
    // (any authed user), the ROUTE's `auth` middleware is the gate, mirroring `rushing/audiostud`'s
    // identical mount.
    Route::beamUxEntries();

    // The authed home IS the OOTB account realm: <AccountShell> (@splicewire/beam-ux/account). Fortify
    // redirects login here (`config/fortify.php` home => /dashboard).
    Route::inertia('dashboard', 'account/home')->name('dashboard');

    // The host-owned Frame resource edit page for the editable sitemap (kind A).
    // Frame ships only frame/manifest; the host binds each resource's edit route.
    Route::get('frame/resources/sitemap', SitemapResourceController::class)
        ->name('frame.resources.sitemap');

    // The OPERATOR front-end realm (frontend-surfaces.md). A thin stats roll-up landing framed by the
    // promoted @splicewire/beam-mainframe host; resource lists ride Frame's generic particle CRUD socket.
    // Gated on the `os.operate` entitlement: the DefaultEntitlementResolver (laravel-beam-accounts) grants
    // it to a staff principal, so the seeded staff user reaches it and a non-staff user is 403'd.
    Route::get('operator', fn () => Inertia::render('operator/dashboard', [
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
