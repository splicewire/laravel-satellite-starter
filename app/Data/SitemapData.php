<?php

namespace App\Data;

use App\Models\SitemapRecord;
use App\Sitemap\NavItem;
use Schemastud\Frame\Attributes\AdminResource;
use Schemastud\Frame\Attributes\Column;
use Schemastud\Frame\Attributes\NotInList;
use Spatie\LaravelData\Data;

/**
 * The record leaf's edit+read shape (kind A) — the single authored artifact the Frame
 * editor writes and the {@see NavItem} projection reads. Mirrors the
 * satellite-publishing `PostData` exemplar (ONE `#[AdminResource]` is both list and edit),
 * but moat-free: it needs only `schemastud/laravel-frame`, never satellite.
 *
 * `externalUrl` is the load-bearing field: when set it makes the projected NavItem point
 * OFF-HOST — the cross-host affordance a content-derived (kind C) nav structurally cannot
 * express. `href` (in-host path) and `externalUrl` (off-host) are mutually informative: the
 * projection prefers `externalUrl` when present (see {@see SitemapRecord::toNavItem()}).
 *
 * Registered under the `sitemap` key at boot (see AppServiceProvider), which surfaces the
 * `frame/resources/sitemap` editor route so an owner can edit the nav via Frame.
 */
#[AdminResource(
    key: 'sitemap',
    model: SitemapRecord::class,
    label: 'Sitemap',
    group: 'Site',
    icon: 'map',
    section: 'links',
    navOrder: 99,
    routeName: 'frame.resources.sitemap',
)]
class SitemapData extends Data
{
    public function __construct(
        #[Column(label: 'Label', sort: 0)]
        public string $label,

        #[Column(label: 'Path', sort: 1)]
        public string $href,

        #[Column(label: 'Order', sort: 2)]
        public int $order = 0,

        // The cross-host escape hatch: an off-host URL a doc glob cannot express. When set,
        // the projection points the NavItem here and flags it external.
        #[NotInList]
        public ?string $externalUrl = null,
    ) {}
}
