<?php

declare(strict_types=1);

namespace App\Frame\Examples;

use App\Data\Examples\SitemapReadData;
use Illuminate\Http\Request;
use ReflectionClass;
use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\Frame\Contracts\ResourceSchemaResolver;
use Schemastud\Frame\Registry\ResourceDefinition;

final class SitemapReadSchemaResolver implements ResourceSchemaResolver
{
    public function __construct(private ?ResourceSchemaResolver $next = null) {}

    /** @return array<string, mixed>|null */
    public function schema(ResourceDefinition $resource, Request $request): ?array
    {
        if ($resource->key === 'sitemap' && $request->query('purpose', 'write') === 'read') {
            return app(Generator::class)->forResponse()->generate(new ReflectionClass(SitemapReadData::class));
        }

        return $this->next?->schema($resource, $request);
    }
}
