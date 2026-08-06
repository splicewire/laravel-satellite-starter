<?php

namespace App\Data;

use App\Models\SitemapRecord;
use App\Sitemap\NavItem;
use Schemastud\Frame\Attributes\Column;
use Schemastud\Frame\Attributes\NotInList;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The record leaf's edit+read shape (kind A) — the single authored artifact the Frame
 * editor writes and the {@see NavItem} projection reads. ONE `#[ParticleResource]` is the
 * whole declaration: label + model + nav placement + route identity, all carried on this Data
 * class. Beam's attributed discovery reflects it into the admin manifest at boot — no
 * `registerClass()` in a provider, no second declaration. Dropping this one annotated file into
 * `app/Data/` IS the wiring (it sits under the scanned `discover_paths`).
 *
 * `externalUrl` is the load-bearing field: when set it makes the projected NavItem point
 * OFF-HOST — the cross-host affordance a content-derived (kind C) nav structurally cannot
 * express. `href` (in-host path) and `externalUrl` (off-host) are mutually informative: the
 * projection prefers `externalUrl` when present (see {@see SitemapRecord::toNavItem()}).
 *
 * A non-empty `label` marks the resource FRAMED — it lights up the `@schemastud/frame` editor
 * and surfaces the `frame/resources/sitemap` editor route so an owner can edit the nav.
 */
#[ParticleResource(
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
