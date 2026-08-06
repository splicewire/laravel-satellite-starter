<?php

namespace App\Models;

use App\Data\SitemapData;
use App\Sitemap\NavItem;
use App\Sitemap\RecordLeaf;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The record leaf's persistence (kind A). A plain uuid + `payload`/`meta` json store — so a
 * nav entry is a lightweight record with no bespoke columns. The Frame editor writes a
 * {@see SitemapData} shape into `payload`; {@see self::toNavItem()} projects it back out into
 * the {@see NavItem} the nav renders over.
 *
 * This is the record→project pattern the starter teaches: a model-backed resource that a single
 * `#[ParticleResource]` on {@see SitemapData} surfaces into Frame's admin (no provider
 * registration, no second declaration). A production host that wants schema-typed,
 * snapshot-versioned, migrate-on-read persistence composes beam's `PersistsBeamParticle` trait
 * (or extends `BeamParticle`) instead; the starter keeps the store plain to teach the
 * declaration seam without dragging in the versioning substrate.
 *
 * Cache-bust-on-save (dial #3): every save/delete forgets {@see RecordLeaf::CACHE_KEY}, so
 * the runtime-merged nav reflects an owner's Frame edit on the next request — no rebuild.
 */
class SitemapRecord extends Model
{
    use HasUuids;

    protected $table = 'schema_records';

    protected $fillable = ['schema_ref', 'payload', 'meta'];

    protected $casts = [
        'payload' => 'array',
        'meta' => 'array',
    ];

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
