<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * The OPERATOR realm's manifest, over the wire — the endpoint the operator app chrome's rail reads.
 *
 * `@splicewire/beam-inertia` gives `operator/*` pages `OperatorLayout`, whose sidebar fetches
 * `/operator/frame/manifest` (otb-ui-frontier-sidebar DESIGN-01, "Want 2"). If this mount is missing
 * the rail is empty with no error on screen, and if it rides the unscoped `/frame/manifest` default the
 * rail beside an operator page lists the TENANT realm's sections. {@see FrameManifestRouterTest} asks
 * the controller for each realm directly; only an HTTP request proves the mount, its
 * `->defaults('realm', 'operator')` and its gate.
 */
class OperatorFrameManifestTest extends TestCase
{
    use RefreshDatabase;

    /** The same operator cascade {@see OperatorOsEntitlementTest} grants — a realm grant, not a flag. */
    private function operator(): User
    {
        $user = User::factory()->create();
        $team = Team::create(['user_id' => $user->id, 'name' => 'Operators', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    public function test_the_operator_manifest_is_mounted_as_frames_own_controller_for_the_operator_realm(): void
    {
        $route = app('router')->getRoutes()->getByName('operator.frame.manifest');

        $this->assertNotNull($route, 'The operator manifest route must be MOUNTED, not merely callable.');
        $this->assertSame('operator/frame/manifest', $route->uri());
        $this->assertSame(FrameManifestController::class, $route->getActionName());
        $this->assertSame('operator', $route->defaults['realm'] ?? null);
        $this->assertContains('can:entitlement:os.operate', $route->gatherMiddleware());
    }

    public function test_an_operator_reads_the_operator_realms_manifest(): void
    {
        $response = $this->actingAs($this->operator())->getJson('/operator/frame/manifest');

        $response->assertOk();
        $this->assertSame(
            ['resources', 'contexts', 'nav', 'routeContext'],
            array_keys($response->json()),
        );

        // The realm is not a key on the wire; it is WHICH router table comes back. The operator realm
        // places `users` and `teams`, the tenant realm `beam-ux-entry` — disjoint, so a mount that fell
        // back to the default realm cannot satisfy both assertions.
        $routeNames = array_column($response->json('routeContext'), 'routeName');
        $this->assertContains('users.index', $routeNames);
        $this->assertContains('teams.index', $routeNames);
        $this->assertNotContains('beam-ux-entry.index', $routeNames);
    }

    public function test_an_ordinary_member_is_refused_the_operator_manifest(): void
    {
        $member = User::factory()->create(); // no realm grant ⇒ os.operate false

        $this->actingAs($member)->getJson('/operator/frame/manifest')->assertForbidden();
    }

    public function test_a_guest_is_refused_the_operator_manifest(): void
    {
        $this->getJson('/operator/frame/manifest')->assertUnauthorized();
    }
}
