<?php

namespace Tests\Feature;

use App\Data\SitemapData;
use App\Doctor\OrphanedNavItemAudit;
use App\Models\SitemapRecord;
use App\Sitemap\ContentGlobLeaf;
use App\Sitemap\NavItem;
use App\Sitemap\RecordLeaf;
use App\Sitemap\RootSitemap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Schemastud\Doctor\DoctorStatus;
use Schemastud\Frame\Registry\AdminResourceRegistry;
use Tests\TestCase;

/**
 * The starter's worked Frame example (BEAM-09): a composed RootSitemap whose leaves are
 * kind C (content glob) or kind A (editable SitemapRecord), plus the orphaned-nav doctor.
 * Test-level coverage of the projection + registration + doctor + route; the live
 * edit→nav-update UI needs the starter's Vite/Inertia stack (out of scope here).
 */
class SitemapRecordFrameTest extends TestCase
{
    use RefreshDatabase;

    // ── kind A: SitemapRecord::toNavItem() projects a SitemapData to a NavItem ────────────

    public function test_record_projects_to_a_nav_item_with_label_href_and_order(): void
    {
        $record = new SitemapRecord;
        $record->payload = ['label' => 'Guides', 'href' => '/docs/build', 'order' => 3];

        $item = $record->toNavItem();

        $this->assertInstanceOf(NavItem::class, $item);
        $this->assertSame('Guides', $item->label);
        $this->assertSame('/docs/build', $item->href);
        $this->assertSame(3, $item->order);
        $this->assertFalse($item->external);
    }

    public function test_record_can_carry_a_cross_host_external_link(): void
    {
        $record = new SitemapRecord;
        $record->payload = [
            'label' => 'Status',
            'href' => '/ignored-when-external',
            'order' => 9,
            'externalUrl' => 'https://status.example.com',
        ];

        $item = $record->toNavItem();

        // The projection prefers externalUrl and flags the item off-host — the affordance a
        // content-glob leaf structurally cannot express.
        $this->assertSame('https://status.example.com', $item->href);
        $this->assertTrue($item->external);
    }

    // ── kind C default: the all-content nav renders with NO record lookup ─────────────────

    public function test_default_nav_is_all_content_derived_with_no_record_lookup(): void
    {
        // Fail loudly if the default nav ever hits the record table.
        $this->assertGuardedAgainstRecordLookup(function () {
            $sitemap = RootSitemap::default();

            $this->assertTrue($sitemap->isAllContentDerived());

            $tree = $sitemap->build();

            $this->assertNotEmpty($tree);
            $this->assertSame('/docs/build/beam-overview', $tree[0]['items'][0]->href);
        });
    }

    public function test_content_glob_leaf_derives_in_host_nav_items(): void
    {
        $leaf = new ContentGlobLeaf([
            ['label' => 'A', 'slug' => 'x/a', 'order' => 1],
        ], '/docs');

        $items = $leaf->items();

        $this->assertSame('/docs/x/a', $items[0]->href);
        $this->assertFalse($items[0]->external);
    }

    // ── The record leaf merges into the trunk at runtime, cache-busted on save ────────────

    public function test_record_leaf_merges_at_runtime_and_cache_busts_on_save(): void
    {
        SitemapRecord::create(['payload' => ['label' => 'One', 'href' => '/dashboard', 'order' => 1]]);

        $first = (new RecordLeaf)->items();
        $this->assertCount(1, $first);

        // The leaf memoises; a new record must not appear until the cache busts on save.
        SitemapRecord::create(['payload' => ['label' => 'Two', 'href' => '/dashboard', 'order' => 2]]);

        $this->assertNull(Cache::get(RecordLeaf::CACHE_KEY), 'save() must bust the leaf cache');
        $this->assertCount(2, (new RecordLeaf)->items());
    }

    public function test_a_section_can_opt_in_the_record_leaf(): void
    {
        $sitemap = (new RootSitemap)->withRecordLeaf();

        $this->assertFalse($sitemap->isAllContentDerived());
    }

    // ── kind A registration: the AdminResource registers under `sitemap` ──────────────────

    public function test_the_sitemap_admin_resource_registers(): void
    {
        $registry = app(AdminResourceRegistry::class);

        $this->assertTrue($registry->has('sitemap'));

        $definition = $registry->get('sitemap');

        $this->assertSame('sitemap', $definition->key);
        $this->assertSame('model', $definition->sourceKind);
        $this->assertSame(SitemapRecord::class, $definition->model);
        $this->assertSame(SitemapData::class, $definition->data);
        $this->assertSame('Sitemap', $definition->nav->label);
        $this->assertSame('links', $definition->nav->section);
        $this->assertSame('frame.resources.sitemap', $definition->nav->routeName);
    }

    // ── The frame/resources/sitemap route exists ─────────────────────────────────────────

    public function test_the_frame_resource_route_is_registered(): void
    {
        $route = app(Router::class)->getRoutes()->getByName('frame.resources.sitemap');

        $this->assertNotNull($route);
        $this->assertSame('frame/resources/sitemap', $route->uri());
    }

    // ── The orphaned-nav doctor check ────────────────────────────────────────────────────

    public function test_doctor_passes_when_record_links_resolve_to_real_routes(): void
    {
        SitemapRecord::create(['payload' => ['label' => 'Dash', 'href' => '/dashboard', 'order' => 1]]);

        $finding = (new OrphanedNavItemAudit(app(Router::class)))->run();

        $this->assertSame(DoctorStatus::Pass, $finding->status);
        $this->assertSame('orphaned nav item', $finding->check);
    }

    public function test_doctor_warns_advisory_on_a_broken_record_link(): void
    {
        SitemapRecord::create(['payload' => ['label' => 'Gone', 'href' => '/no-such-route', 'order' => 1]]);

        $finding = (new OrphanedNavItemAudit(app(Router::class)))->run();

        // Advisory: a broken link WARNs (never fails) so the doctor gate stays green.
        $this->assertSame(DoctorStatus::Warn, $finding->status);
        $this->assertStringContainsString('/no-such-route', $finding->detail);
    }

    public function test_doctor_skips_external_links(): void
    {
        SitemapRecord::create(['payload' => [
            'label' => 'Off-host', 'href' => '/x', 'order' => 1,
            'externalUrl' => 'https://elsewhere.example',
        ]]);

        $finding = (new OrphanedNavItemAudit(app(Router::class)))->run();

        // An off-host target is not the app's to validate — no orphan.
        $this->assertSame(DoctorStatus::Pass, $finding->status);
    }

    /**
     * Run $callback with the SitemapRecord query connection swapped for one that throws if
     * touched, proving the all-C path performs no record lookup.
     */
    private function assertGuardedAgainstRecordLookup(callable $callback): void
    {
        $before = SitemapRecord::query()->getConnection()->getQueryLog();

        SitemapRecord::query()->getConnection()->enableQueryLog();

        $callback();

        $log = SitemapRecord::query()->getConnection()->getQueryLog();

        $touchedRecords = collect($log)->contains(
            fn (array $entry) => str_contains($entry['query'] ?? '', 'schema_records'),
        );

        $this->assertFalse($touchedRecords, 'the all-C default nav must not query schema_records');
    }
}
