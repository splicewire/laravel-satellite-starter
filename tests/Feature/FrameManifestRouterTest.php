<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\DataNav\NavGate;
use Rushing\DataNav\NavRegistry;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Tests\TestCase;

/**
 * This host emits a manifest-generated router table and writes NO controller to do it.
 *
 * Until OTB M2, `/frame/manifest` here carried `resources` and `contexts` and nothing else — `nav`
 * and `routeContext` existed at exactly ONE host in the estate, which had hand-written its own copy
 * of frame's manifest controller. The projection now ships in `splicewire/laravel-beam-ux` behind
 * frame's `FrameNavContributor` plug; what this host supplies is `config('frame.realms')`, a list it
 * spells out (`api-surface-coherence` 141/142).
 *
 * ⚠️ **Every assertion below is made on BOTH realms, and the two lists are genuinely disjoint.** A
 * test that only ever asked for `tenant` would pass against a projector that ignored the realm and
 * returned every resource to everyone — which is precisely the defect these assertions exist to
 * catch, and it is invisible to a single-realm fixture.
 */
class FrameManifestRouterTest extends TestCase
{
    /**
     * ⚠️ Added 2026-09-05 with the `frame.middleware` gate. Only ONE of these five tests needs a
     * database, and it needs it for a real reason rather than an incidental one: authenticating a
     * principal makes the nav collector ask `viewAny`, which routes through permission-cascade and
     * reads the `permissions` table. An unpersisted actor cannot avoid it — the gate is the point.
     */
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /**
     * ⚠️ This one goes over HTTP, and the other three do not — deliberately, and the split matters.
     *
     * `app->call()` on a controller cannot tell a MOUNTED route from an absent one, and skips
     * `config('frame.middleware')` entirely; a suite built only that way would stay green if the
     * route were deleted (AGENTS.md §*The estate's signature defect*, the 404-body row: *"read the
     * route table"*). So the mount is asserted here, once, against the real request pipeline —
     * including that the route this host serves is frame's OWN controller and not a host copy.
     *
     * The per-realm tests below cannot use HTTP: this host mounts the manifest once, unscoped, so
     * the `operator` realm is unreachable over the wire and only a direct call can ask for it.
     */
    public function test_the_mounted_route_serves_frames_controller_and_emits_the_new_keys(): void
    {
        $route = app('router')->getRoutes()->getByName('frame.manifest');

        $this->assertNotNull($route, 'The manifest route must be MOUNTED, not merely callable.');
        $this->assertSame(FrameManifestController::class, $route->getActionName());

        // ⚠️ `actingAs` since 2026-09-05. This host now sets `frame.middleware` to
        // `['web','auth']` (config/frame.php), because the package default `['web']` left the
        // manifest — and Frame's whole generic CRUD socket — answering anonymous callers: a bare
        // GET returned 200 with every nav seat and 12 resource definitions. Authenticating here is
        // not a workaround; it is this test finally traversing the pipeline its own docblock says
        // it exists to traverse, now that the pipeline has a gate in it.
        //
        // An UNPERSISTED `new User` on purpose: `auth` only asks whether a principal is present, and
        // this test asserts the MOUNT, not what any particular actor may see. A factory would drag a
        // database in and couple a routing assertion to a schema.
        $response = $this->actingAs(new User)->getJson('/frame/manifest');

        $response->assertOk();
        $this->assertSame(
            ['resources', 'contexts', 'nav', 'routeContext'],
            array_keys($response->json()),
            'Over the wire, from the package, with no controller in this host.'
        );
        $this->assertNotEmpty($response->json('routeContext'));
    }

    public function test_it_emits_nav_and_route_context_from_the_package_with_no_host_controller(): void
    {
        $this->assertSame(
            ['resources', 'contexts', 'nav', 'routeContext'],
            array_keys($this->manifestFor('tenant'))
        );
    }

    public function test_it_scopes_the_router_table_to_the_realm_the_host_placed_each_resource_in(): void
    {
        $tenant = $this->routeNames('tenant');
        $operator = $this->routeNames('operator');

        $this->assertContains('beam-ux-entry.index', $tenant);
        $this->assertContains('hooks.index', $tenant);
        $this->assertNotContains('users.index', $tenant);

        $this->assertContains('users.index', $operator);
        $this->assertContains('teams.index', $operator);
        $this->assertNotContains('beam-ux-entry.index', $operator);

        // The two realms must not merely differ — neither may overlap the other, or a projector
        // leaking one realm's list into the other could still satisfy both blocks above.
        $this->assertSame([], array_values(array_intersect($tenant, $operator)));
    }

    public function test_it_gives_a_resource_the_per_record_twin_its_own_declaration_allows(): void
    {
        $byName = $this->byName('tenant');

        // `editable` ⇒ an edit form.
        $this->assertSame('edit', $byName['beam-ux-entry.edit']->mounts);
        // `showable && ! editable` ⇒ a read-only detail, never an edit shell whose save the server 405s.
        $this->assertSame('detail', $byName['git-repo.edit']->mounts);
        // Neither ⇒ a list leaf and nothing else. `tokens` and `invitations` are the live cases here.
        $this->assertArrayNotHasKey('tokens.edit', $byName);
        $this->assertArrayNotHasKey('invitations.edit', $byName);
        $this->assertArrayHasKey('tokens.index', $byName);
    }

    public function test_an_unregistered_navigation_is_an_empty_tree_not_a_failure(): void
    {
        // "Is a navigation registered for this realm?" is a fact about the HOST, so the contributor
        // answers it with an empty tree beside a real routeContext instead of throwing. The router
        // half needs no navigation to exist, and declining outright would throw away the half that
        // works — which is how a seam ships and delivers nothing.
        // ⚠️ Rebinds an EMPTY NavRegistry since 2026-09-05, and the reason is the whole point of
        // the test rather than a detail of it. beam-ux now registers a `DeclaredSectionNavigation`
        // for every realm this host ships, so "no navigation is registered for tenant" stopped
        // being reachable here — this test began failing with a populated Ops seat, and was then
        // reported as a PRE-EXISTING failure by three separate later runs. It was not pre-existing:
        // it was a real regression, laundered into background noise by being counted rather than read.
        //
        // The property it guards is still true and still load-bearing: `NavRegistry::build()` throws
        // `RegistryMiss` on an unknown key, and "this host registered no navigation" must answer with
        // an empty tree beside a real routeContext rather than 500 the manifest. Delete the catch in
        // `FrameNavContribution::navigation()` and every host with no navigation breaks. So the test
        // now creates the condition explicitly instead of relying on the host to happen to be in it.
        $this->app->instance(NavRegistry::class, new NavRegistry(
            $this->app->make(NavGate::class),
            $this->app->make(NavExpander::class),
            $this->app->make(NavMatcher::class),
        ));

        $manifest = $this->manifestFor('tenant');

        $this->assertSame([], $manifest['nav']['items']);
        $this->assertNotEmpty($manifest['routeContext']);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestFor(string $realm): array
    {
        $request = Request::create('/frame/manifest', 'GET');
        $route = new RoutingRoute(['GET'], 'frame/manifest', []);
        $route->defaults('realm', $realm);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        return $this->app->call(FrameManifestController::class, ['request' => $request]);
    }

    /**
     * @return array<int, string>
     */
    private function routeNames(string $realm): array
    {
        return array_map(
            fn ($entry): string => $entry->routeName,
            $this->manifestFor($realm)['routeContext']
        );
    }

    /**
     * @return array<string, \Schemastud\Frame\Registry\RouteContextEntry>
     */
    private function byName(string $realm): array
    {
        $byName = [];

        foreach ($this->manifestFor($realm)['routeContext'] as $entry) {
            $byName[$entry->routeName] = $entry;
        }

        return $byName;
    }
}
