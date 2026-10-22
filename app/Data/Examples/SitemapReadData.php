<?php

declare(strict_types=1);

namespace App\Data\Examples;

use App\Data\SitemapData;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The existing Sitemap payload plus the generic Frame handler's withId() envelope. */
#[TypeScript]
final class SitemapReadData extends SitemapData
{
    #[Computed]
    #[Title('ID')]
    public string $id;
}
