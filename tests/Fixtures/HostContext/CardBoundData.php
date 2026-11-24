<?php

namespace Tests\Fixtures\HostContext;

use App\Models\SitemapRecord;
use Schemastud\Frame\Attributes\Column;
use Schemastud\Frame\Attributes\WidgetIn;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Particle\Attributes\ParticleResource;
use Splicewire\Beam\Particle\ParticleResource as ParticleResourceContract;

/**
 * A resource that binds its own widget in the host's `card` context, class-level (record grain). It is backed by
 * the starter's sitemap rows so the test needs no table of its own.
 */
#[ParticleResource(
    key: self::KEY,
    backing: SitemapRecord::class,
    input: self::class,
    label: 'Bound cards',
    readBoundary: ParticleResourceContract::READ_BOUNDARY_GLOBAL,
)]
#[WidgetIn(HostContextProvider::CONTEXT, 'host-card')]
class CardBoundData extends Data
{
    public const KEY = 'host-context-bound';

    public function __construct(
        #[Column(label: 'Label', sort: 0)]
        public string $label,
        #[Column(label: 'Path', sort: 1)]
        public string $href,
    ) {}

    /** The row's fields live in its `payload` json, as {@see \App\Data\SitemapData::fromModel()} reads them. */
    public static function fromModel(SitemapRecord $record): self
    {
        $payload = $record->payload ?? [];

        return new self(label: (string) ($payload['label'] ?? ''), href: (string) ($payload['href'] ?? ''));
    }
}
