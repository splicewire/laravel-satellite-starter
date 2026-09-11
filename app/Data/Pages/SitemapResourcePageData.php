<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Schemastud\Frame\Registry\ResourceDefinition;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SitemapResourcePageData extends Data
{
    public function __construct(
        public ResourceDefinition $resource,
    ) {}
}
