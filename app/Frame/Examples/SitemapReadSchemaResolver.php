<?php

declare(strict_types=1);

namespace App\Frame\Examples;

use App\Data\Examples\SitemapReadData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        if ($resource->key === 'sitemap') {
            // The example endpoint projects exactly two postures, `write` (the default) and `read`.
            // Any other `purpose` is a client error, not a silent fall-through to the write schema:
            // a 200 for `purpose=unknown` would hand the caller the wrong document behind a success.
            $purpose = $request->query('purpose', 'write');
            if (! in_array($purpose, ['write', 'read'], true)) {
                throw ValidationException::withMessages([
                    'purpose' => "Unknown schema purpose [{$purpose}]; expected one of: write, read.",
                ]);
            }

            if ($purpose === 'read') {
                return app(Generator::class)->forResponse()->generate(new ReflectionClass(SitemapReadData::class));
            }
        }

        return $this->next?->schema($resource, $request);
    }
}
