<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Satellite\Pairing\Data\PlatformConnectionData;
use Splicewire\Satellite\Pairing\PlatformConnection;
use Splicewire\Satellite\Pairing\PlatformConnectionState;
use Tests\TestCase;

/**
 * The HOST half of the platform-connection surface: the mount, the gate, and the door.
 *
 * The pairing behaviour itself belongs to `splicewire/laravel-satellite` and is proved in its own
 * suite (`tests/PlatformConnectionTest.php`, the six-state machine against a mocked platform). What
 * can only be proved here is what a host contributes, and all three of those things have failed
 * silently in this estate before:
 *
 * - the OPERATIONS are actually mounted — `Route::splicewirePlatformConnectionRoutes()` is a macro
 *   nothing in the package calls, and `splicewire/tower` shipping controllers it never routed is the
 *   precedent that produced `DeviceAuthorizationFlowTest`;
 * - the GATE agrees with the declaration — the route carries `can:entitlement:os.operate` and each
 *   operation declares the same entitlement, so a member must be refused at the endpoint and not
 *   merely be missing a link;
 * - the DOOR exists — the operator realm has no navigation rail here, so a surface not linked from
 *   `/operator` is reachable by typing its URL and by nothing else.
 */
class PlatformConnectionSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $user = User::factory()->create();

        // The same grant `OperatorOsEntitlementTest` uses: `os.operate` resolves through a TEAM's
        // realm-grant cascade (an Owner holding `manage` on the operator realm's root entry), not off
        // an `is_staff` column — this host never bound its own resolver.
        $team = Team::create(['user_id' => $user->id, 'name' => 'A Team', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    /** Keep the real device flow off the wire: the host test is about routing and gating. */
    private function stubConnection(PlatformConnectionState $state = PlatformConnectionState::Unpaired): void
    {
        $this->instance(PlatformConnection::class, new class($state) extends PlatformConnection
        {
            public function __construct(private readonly PlatformConnectionState $state) {}

            public function snapshot(bool $probeIdentity = false): PlatformConnectionData
            {
                return new PlatformConnectionData(
                    state: $this->state,
                    tokenPresent: $this->state === PlatformConnectionState::Paired,
                    platformUrl: 'https://tower.test',
                    centralUrl: 'https://tower.test',
                );
            }
        });
    }

    public function test_every_operation_the_screen_drives_is_actually_mounted(): void
    {
        foreach (['connect', 'poll', 'invoke-capability', 'disconnect'] as $op) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has("platform-connection.{$op}"),
                "platform-connection.{$op} is not mounted; the screen's [{$op}] control would 404."
            );
        }
    }

    public function test_the_operations_mount_without_an_id_coordinate(): void
    {
        // `ActorSubject::pathParameters()` is `[]`, so the mount must carry no `{id}`. A pairing is a
        // fact about the site, and an `{id}` here would be a coordinate nothing reads.
        $uri = \Illuminate\Support\Facades\Route::getRoutes()->getByName('platform-connection.connect')->uri();

        $this->assertSame('operator/platform-connection/connect', $uri);
    }

    public function test_an_operator_reaches_the_screen_with_the_connection_and_the_endpoints(): void
    {
        $this->stubConnection();

        $this->actingAs($this->operator())
            ->get('/operator/platform-connection')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/platform-connection')
                ->where('connection.state', 'unpaired')
                ->where('connection.platformUrl', 'https://tower.test')
                // Read from the route table, so the screen's buttons address the real mount.
                ->where('endpoints.connect', '/operator/platform-connection/connect')
                ->where('endpoints.poll', '/operator/platform-connection/poll')
                ->where('endpoints.invokeCapability', '/operator/platform-connection/invoke-capability')
                ->where('endpoints.disconnect', '/operator/platform-connection/disconnect')
            );
    }

    public function test_a_non_operator_is_refused_the_screen(): void
    {
        $this->stubConnection();

        $this->actingAs(User::factory()->create())
            ->get('/operator/platform-connection')
            ->assertForbidden();
    }

    public function test_a_non_operator_is_refused_every_operation_directly_not_merely_hidden_from_the_ui(): void
    {
        $member = User::factory()->create();

        foreach (['connect', 'poll', 'invoke-capability', 'disconnect'] as $op) {
            $this->actingAs($member)
                ->postJson("/operator/platform-connection/{$op}", [])
                ->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login_rather_than_reaching_a_credential_writing_operation(): void
    {
        $this->post('/operator/platform-connection/connect', [])->assertRedirect('/login');
    }

    public function test_the_operator_landing_carries_a_door_to_the_screen(): void
    {
        $this->actingAs($this->operator())
            ->get('/operator')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/dashboard')
                ->where('surfaces.0.title', 'Platform connection')
                ->where('surfaces.0.href', '/operator/platform-connection')
            );
    }

    public function test_the_capability_operation_refuses_an_unknown_surface(): void
    {
        $this->actingAs($this->operator())
            ->postJson('/operator/platform-connection/invoke-capability', ['surface' => 'not-a-surface'])
            ->assertStatus(422);
    }

    public function test_the_pairing_label_is_constrained_before_it_reaches_the_tower(): void
    {
        // The label becomes the minted credential's NAME on a remote host, so it is validated here
        // rather than trusted to the tower.
        $this->actingAs($this->operator())
            ->postJson('/operator/platform-connection/connect', ['label' => str_repeat('x', 200)])
            ->assertStatus(422);
    }
}
