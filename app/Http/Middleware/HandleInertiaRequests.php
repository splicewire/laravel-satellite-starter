<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;
use Rushing\DataNav\NavTree;
use Schemastud\Frame\Realm\RealmDefinition;
use Splicewire\Beam\Accounts\Contracts\AccountShellProvider;
use Splicewire\Beam\Accounts\Data\AccountShellData;
use Splicewire\Beam\Entitlements\CanMapBuilder;
use Splicewire\Beam\Realm\RealmManifestProjector;
use Splicewire\Beam\Realm\RealmRegistry;
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
            return $this->withoutUnentitledRealmSeats(app(NavProjector::class)->project('account'));
        } catch (Throwable) {
            return NavTree::make([]);
        }
    }

    /**
     * Drop any account-rail seat that leads into a realm this principal is not entitled to.
     *
     * ## Why the account rail has to answer this at all
     *
     * The signed-in chrome is `<AccountShell>`, and `AppSidebarBeam` renders exactly one thing: the
     * `accountNav` prop, i.e. `NavProjector::project('account')`. Measured on fresh-tower.test
     * 2026-09-11 as `demo-admin`, who holds `entitlement:os.operate`: the only links the shell offered
     * were Dashboard, Profile, API tokens and Team. `resources/beam-ux/nav.yml` DID declare the
     * operator seat (`operator-dashboard`, `/operator`, `realm: operator`) and `ux:seed-nav` DID seed
     * it — into the OPERATOR sitemap, which nothing at this host projects. So the operator realm was
     * reachable only by typing its URL: it had no door.
     *
     * The seat therefore lives in the ACCOUNT realm (the `tenant-console` precedent), which means the
     * rail would otherwise offer it to every signed-in user — including the member the route 403s.
     *
     * ## The gate is the realm's own declaration, not a second list
     *
     * `beam.core.realm_gates` already names each realm's entitlement, and the `RealmRegistry` already
     * names each realm's `routeBase`. This joins the two: a seat whose href enters a gated realm's
     * base is dropped when the principal lacks that realm's entitlement. So the seat and the route it
     * leads to are gated by ONE declaration and cannot disagree — the same key
     * `Splicewire\Beam\Realm\RealmEntitlementResourceGate` reads at the frame socket and
     * `RealmManifestProjector` reads for the realm manifest.
     *
     * Hiding is not the boundary and is not treated as one: `can:entitlement:os.operate` on the route
     * and the socket gate are what refuse; this only stops offering a door the reader cannot open.
     */
    protected function withoutUnentitledRealmSeats(NavTree $tree): NavTree
    {
        $blocked = [];

        foreach ((array) config('beam.core.realm_gates', []) as $realm => $gate) {
            $entitlement = is_array($gate) ? ($gate['entitlement'] ?? null) : null;

            if (! is_string($entitlement) || $entitlement === '') {
                continue;
            }

            if (Gate::allows('entitlement:'.$entitlement)) {
                continue;
            }

            $definition = app(RealmRegistry::class)->tryResolve($realm);

            if (! $definition instanceof RealmDefinition) {
                // A gate naming a realm nothing registers is inert config, not a seat to hide.
                continue;
            }

            $base = rtrim($definition->routeBase, '/');

            if ($base !== '') {
                $blocked[] = $base;
            }
        }

        if ($blocked === []) {
            return $tree;
        }

        return NavTree::make(array_values(array_filter(
            $tree->items,
            function (object $node) use ($blocked): bool {
                $href = is_string($node->href ?? null) ? rtrim($node->href, '/') : null;

                if ($href === null) {
                    return true;
                }

                foreach ($blocked as $base) {
                    if ($href === $base || str_starts_with($href, $base.'/')) {
                        return false;
                    }
                }

                return true;
            }
        )));
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
