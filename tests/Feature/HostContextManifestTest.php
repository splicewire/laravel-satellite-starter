<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\Fixtures\HostContext\CardBoundData;
use Tests\Fixtures\HostContext\CardFallbackData;
use Tests\Fixtures\HostContext\HostContextProvider;
use Tests\TestCase;

/**
 * A host-registered record-grain context reaches the served manifest (frame-foundation 92, box 3's server half).
 *
 * The browser half, the widget drawn and the fallback drawn, waits on browser-journeys: a mounted package suite cannot
 * yet bring its own fixtures or front-end entry, and this starter authors no browser tests of its own (ticket 13).
 */
class HostContextManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(HostContextProvider::class);
    }

    /** The operator cascade {@see RealmDashboardTest} grants: a realm grant, not a flag. */
    private function operator(): User
    {
        // Provisioned, not hand-made: the fixtures read SitemapRecord rows, and its cascade policy's viewAny admits
        // only a role the provisioner wrote permission rows for. A bare Membership row holds none.
        $user = User::factory()->create();
        $team = app(TeamProvisioner::class)->personalTeamFor($user);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    public function test_the_served_manifest_carries_a_host_context_in_known_inherits_and_by_node(): void
    {
        $contexts = $this->actingAs($this->operator())->getJson('/frame/manifest')->assertOk()->json('contexts');

        $bound = $contexts[CardBoundData::KEY];
        $this->assertContains(HostContextProvider::CONTEXT, $bound['known'], 'known lists the host context');
        $this->assertSame(['list-item'], $bound['inherits'][HostContextProvider::CONTEXT], 'inherits carries its edge');
        $this->assertSame(['participates' => true, 'widget' => 'host-card'], $bound['byNode'][''][HostContextProvider::CONTEXT]);

        // Unbound in the host context: it participates with no widget, so the kit falls back along the edge to list-item.
        $fallback = $contexts[CardFallbackData::KEY];
        $this->assertSame(['participates' => true], $fallback['byNode'][''][HostContextProvider::CONTEXT]);
        $this->assertSame(['participates' => true, 'widget' => 'host-list-item'], $fallback['byNode']['']['list-item']);
    }

    public function test_frames_seven_keep_their_order_ahead_of_the_host_context(): void
    {
        $known = $this->actingAs($this->operator())->getJson('/frame/manifest')->assertOk()
            ->json('contexts.'.CardBoundData::KEY.'.known');

        $this->assertSame(
            ['edit', 'detail', 'list-column', 'list-item', 'row-cell', 'summary', 'overview', HostContextProvider::CONTEXT],
            $known,
        );
    }
}
