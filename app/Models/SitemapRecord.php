<?php

namespace App\Models;

use App\Data\SitemapData;
use App\Sitemap\NavItem;
use App\Sitemap\RecordLeaf;
use Illuminate\Support\Facades\Cache;
use Splicewire\Beam\Models\SchemaRecord;

/**
 * The record leaf's persistence (kind A). Extends beam's {@see SchemaRecord} — the generic
 * uuid7 + `payload`/`meta` json store — so a nav entry is a plain schema record with no
 * bespoke table. The Frame editor writes a {@see SitemapData} shape into `payload`;
 * {@see self::toNavItem()} projects it back out into the {@see NavItem} the nav renders over.
 *
 * This is the record→project pattern the starter teaches. Beam's projection seam is
 * `extract(): void` — an inert hook for FANNING a payload OUT into persisted derived rows
 * (a write side-effect). The sitemap projects a payload into a single in-memory nav leaf
 * (a pure read), so it honors that seam's void contract as a no-op and exposes the actual
 * projection as {@see self::toNavItem()} — a returning projector the void seam can't type.
 * (Frame-API note: `extract()`'s void return type cannot be narrowed to `NavItem`; the
 * `toNavItem()` name keeps the record→project teaching without fighting the base signature.)
 *
 * Cache-bust-on-save (dial #3): every save/delete forgets {@see RecordLeaf::CACHE_KEY}, so
 * the runtime-merged nav reflects an owner's Frame edit on the next request — no rebuild.
 */
class SitemapRecord extends SchemaRecord
{
    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(RecordLeaf::CACHE_KEY);

        static::saved($bust);
        static::deleted($bust);
    }

    /**
     * Project the persisted payload into a NavItem (label + href + order, and the off-host
     * external link when `externalUrl` is set). Prefers `externalUrl` over the in-host `href`
     * — the cross-host affordance a content-glob leaf cannot express.
     */
    public function toNavItem(): NavItem
    {
        $payload = $this->payload ?? [];

        $external = ! empty($payload['externalUrl']);

        return new NavItem(
            label: $payload['label'] ?? '',
            href: $external ? $payload['externalUrl'] : ($payload['href'] ?? ''),
            order: (int) ($payload['order'] ?? 0),
            external: $external,
        );
    }
}
