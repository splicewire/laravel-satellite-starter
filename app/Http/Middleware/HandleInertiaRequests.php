<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Rushing\DataNav\NavTree;
use Splicewire\Beam\Accounts\Contracts\AccountShellProvider;
use Splicewire\Beam\Accounts\Data\AccountShellData;
use Splicewire\Beam\Entitlements\CanMapBuilder;
use Splicewire\Beam\Realm\RealmManifestProjector;
use Splicewire\Beam\Ux\Containment\NavProjector;
use Splicewire\Beam\Ux\Theme\ThemeResolver;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Drives the in-place authoring chrome gate (only a site admin sees the edit UI).
                'canAuthorUx' => $request->user()?->can('ux.author') ?? false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Data-driven SITE nav — the `site` sitemap projected via beam-ux's NavProjector. Consumed
            // by <SiteNav>. Resilient: an unseeded/broken sitemap yields an empty tree, never a 500.
            'nav' => $this->siteNav(),
            // The authed-only ACCOUNT nav (signed-in only) — the `account` realm projection.
            'accountNav' => $this->accountNav(),
            // The account-shell data contract (plan/profile/account) filled by the host-bound
            // AccountShellProvider. Null for a guest (and for the Null default provider).
            'accountShell' => $this->accountShell($request),
            // The flat feature-plane can-map (Record<abilityString, bool>) the frame reads verbatim.
            'can' => $this->canMap($request),
            // The per-principal realm MANIFEST the launcher renders — hard-gated realms ABSENT.
            'realmManifest' => $this->realmManifest($request),
            // The resolved theme (package default → central → tenant, ThemeResolver — ticket
            // theme-entries-and-authoring/01) — {canvas, shell, site}. Consumed by editor/theme.ts's
            // swap off NEUTRAL_THEME and the shell/site CSS-var <style> blocks.
            'theme' => $this->theme(),
        ];
    }

    /**
     * Build the flat `can` map for the current principal via the beam {@see CanMapBuilder}. Degrades
     * to an empty map on any failure so it never takes down an Inertia response.
     *
     * @return array<string, bool>
     */
    protected function canMap(Request $request): array
    {
        try {
            return app(CanMapBuilder::class)
                ->withFeatureKeys(['os.enter'])
                ->forPrincipal($request->user());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Project the realm manifest for the current principal. Degrades to an empty manifest on failure.
     *
     * @return list<array<string, mixed>>
     */
    protected function realmManifest(Request $request): array
    {
        try {
            return app(RealmManifestProjector::class)->project($request->user());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Project the account-shell contract for a signed-in user through the host-bound provider.
     */
    protected function accountShell(Request $request): ?AccountShellData
    {
        if (! Auth::check()) {
            return null;
        }

        try {
            return app(AccountShellProvider::class)->shellFor($request->user());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Project the `site` realm's containment tree into a NavTree, degrading to an empty tree on any
     * failure.
     */
    protected function siteNav(): NavTree
    {
        try {
            return app(NavProjector::class)->project('site');
        } catch (Throwable) {
            return NavTree::make([]);
        }
    }

    /**
     * Project the `account` realm's containment tree for a signed-in user only. A guest gets an empty
     * tree.
     */
    protected function accountNav(): NavTree
    {
        if (! Auth::check()) {
            return NavTree::make([]);
        }

        try {
            return app(NavProjector::class)->project('account');
        } catch (Throwable) {
            return NavTree::make([]);
        }
    }

    /**
     * Resolve the cascaded theme (package default → central → tenant, {@see ThemeResolver}).
     * Degrades to an empty array on failure — {@see ThemeResolver::resolve()} already never throws,
     * but this stays defensive like every other share() lookup here (an absent/missing entry still
     * resolves through schema defaults inside the resolver itself, so this catch is belt-and-suspenders
     * against a future change, not the resolver's actual degrade path).
     *
     * @return array<string, mixed>
     */
    protected function theme(): array
    {
        try {
            return app(ThemeResolver::class)->resolve();
        } catch (Throwable) {
            return [];
        }
    }
}
