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
    backing: SitemapRecord::class,
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

    /**
     * Fold the authored fields INTO the record's `payload` json before the write.
     *
     * The write-side twin of {@see self::project()}, and needed for the same reason: this DTO
     * describes what lives INSIDE `schema_records.payload`, while {@see SitemapRecord} only accepts
     * `schema_ref`/`payload`/`meta` as attributes. Without this the generic writer maps `label` and
     * `href` onto columns that do not exist, they are dropped, and a `create` answers **200 with an
     * empty row** — the worst available failure, since it reports success and loses the content.
     *
     * Measured 2026-09-05, once the policy opened the gate: `POST` with a flat body returned 200 and
     * persisted `payload = null`. That is a silent data-loss bug the closed gate had been hiding.
     *
     * `prepare` is beam's before-write hook ({@see \Splicewire\Beam\Particle\ParticleResource::$prepare}),
     * the same seam `Splicewire\Beam\Accounts\Data\InvitationData` uses to mint an invite token.
     *
     * ⚠️ `$input` is typed **array**, not `self`. The hook is handed whatever the write was given, and
     * this resource declares no `input:` class on its `#[ParticleResource]`, so what arrives is the
     * raw validated array — a `self` type hint here is a TypeError on every create, not a nicer
     * signature. `self::from()` recovers the typed object, and the DTO's own defaults with it.
     *
     * @param  array<string, mixed>  $input
     */
    public static function prepare(SitemapRecord $record, array $input): void
    {
        $data = self::from($input);

        $record->payload = [
            'label' => $data->label,
            'href' => $data->href,
            'order' => $data->order,
            'externalUrl' => $data->externalUrl,
        ];
    }

    /**
     * Project a stored record back out into this shape.
     *
     * ⚠️ Without this, every read of a `sitemap` row is a 500, and the reason is the whole point of
     * the kind-A pattern: {@see SitemapRecord} is a plain `schema_records` row whose authored fields
     * live inside a `payload` json column, so `label` and `href` are NOT model attributes and the
     * default `SitemapData::from($model)` finds nothing to fill them with — spatie throws
     * `CannotCreateData: … Parameters missing: label, href`.
     *
     * Measured 2026-09-05: the very first `POST /frame/resources/sitemap` this host ever served
     * WROTE its row correctly and then 500'd projecting the response. It had never surfaced because
     * the resource had no policy, so `Schemastud\Frame\Authorization\ResourceAuthorizer` refused every
     * write before it reached a handler, and the table was empty — a closed gate upstream of a broken
     * projection reads exactly like a working resource nobody has used.
     *
     * `project` is a convention method beam's `ParticleFrameResourceHandler::projectRead()` prefers
     * over `from()` — the same seam `Splicewire\Beam\Accounts\Data\TokenData` and `InvitationData` use.
     */
    public static function project(SitemapRecord $record): self
    {
        $payload = $record->payload ?? [];

        return new self(
            label: (string) ($payload['label'] ?? ''),
            href: (string) ($payload['href'] ?? ''),
            order: (int) ($payload['order'] ?? 0),
            externalUrl: $payload['externalUrl'] ?? null,
        );
    }
}
