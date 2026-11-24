<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Ux\Compile\EntryBodyCompiler;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Publish\EntryPublication;
use Tests\TestCase;

/**
 * The analytics Site area (laravel-beam-ux SiteArea, registered by WiresAnalyticsArea) counts the host's published and
 * sitemap-indexed pages from the Site map resource. Its rows come from the resource handler's index() envelope, so the
 * figures must count the envelope's rows, not its keys (before the fix, measure() received ints and threw a TypeError).
 * Port of laravel-tower-starter's AnalyticsSiteTenantConfinementTest figures assertion to this single-site host.
 * Publishing uses a mocked EntryBodyCompiler, so publishedReadable is really set without a Node compile.
 */
class AnalyticsSiteAreaFiguresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('beam.ux.compile.disk', 'beam-ux-artifacts'));
        Storage::fake((string) config('beam.ux.storage.mirror_disk', 'beam-ux')); // keep entry sources off the real resources/beam-ux
        $compiler = Mockery::mock(EntryBodyCompiler::class);
        $compiler->shouldReceive('handles')->andReturnTrue();
        $compiler->shouldReceive('compile')->andReturn('export default () => null;');
        $this->app->instance(EntryBodyCompiler::class, $compiler);
    }

    private function publishPage(string $slug): BeamUxEntry
    {
        $page = BeamUxEntry::create([
            'slug' => $slug, 'title' => ucfirst($slug), 'type' => 'page', 'format' => 'tsx',
            'realm' => 'site', 'namespace' => '',
        ]);
        $pub = app(EntryPublication::class);
        $pub->publishVersion($page->fresh(), $pub->recordDraft($page->fresh(), [
            ['kind' => 'block', 'name' => 'p', 'isComponent' => false, 'dynamic' => false, 'props' => [], 'children' => []],
        ])->headVersion);

        return $page->fresh();
    }

    /** An operator: a personal team granted manage on the operator root (as FrameManifestRouterTest). */
    private function operator(): User
    {
        $user = User::factory()->create();
        $team = app(TeamProvisioner::class)->personalTeamFor($user);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    /** @return array<string, mixed> the analytics Site area's figure id => value */
    private function siteFigures(User $user): array
    {
        $out = [];
        foreach ($this->actingAs($user)->getJson('/stud/frame/resources/analytics-site')->assertOk()->json('data') as $row) {
            $out[$row['id']] = $row['value'];
        }

        return $out;
    }

    public function test_the_site_area_counts_the_published_and_indexed_pages(): void
    {
        $this->publishPage('about');
        $this->publishPage('pricing');

        $figures = $this->siteFigures($this->operator());

        // The operator realm root (BeamUxEntry::rootFor('operator'), namespace 'realms') is a page born published, and
        // the Install branch counts published pages across realms (as beam-ux's own SiteAreaTestCase does). So the
        // operator sees three published pages: about, pricing and that root. The sitemap indexes the two site pages,
        // and the root is the one published page it leaves out. Root ruled a realm root is never a sitemap issue:
        // sitemap-issues moves to 0 at the repin that takes laravel-beam-ux site-area-sitemap-issues 01.
        $this->assertSame(3, $figures['published'] ?? null, 'about, pricing and the operator realm root');
        $this->assertSame(2, $figures['indexed'] ?? null, 'about and pricing are in the sitemap feed');
        $this->assertSame(1, $figures['sitemap-issues'] ?? null, 'the operator realm root: published, not indexed');
    }
}
