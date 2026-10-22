<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SitemapExamplePageData extends Data
{
    public function __construct(
        public string $mode,
        public ?string $id,
        public string $manifestUrl,
        public string $resourceUrl,
        public string $schemaUrl,
        public string $recordUrlTemplate,
        public string $listHref,
        public string $newHref,
        public string $detailHrefTemplate,
    ) {}

    public static function forMode(string $mode, ?string $id = null): self
    {
        return new self(
            mode: $mode,
            id: $id,
            manifestUrl: route('frame.manifest', absolute: false),
            resourceUrl: route('frame.resources.index', ['resource' => 'sitemap'], absolute: false),
            schemaUrl: route('frame.resources.schema', ['resource' => 'sitemap'], absolute: false),
            recordUrlTemplate: route('frame.resources.show', ['resource' => 'sitemap', 'id' => '__id__'], absolute: false),
            listHref: route('examples.sitemap.index', absolute: false),
            newHref: route('examples.sitemap.create', absolute: false),
            detailHrefTemplate: route('examples.sitemap.show', ['id' => '__id__'], absolute: false),
        );
    }
}
