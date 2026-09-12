<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Satellite\Pairing\Data\PlatformConnectionData;

/**
 * The props of the operator realm's Platform-connection page.
 *
 * Two halves, and the second is the host's whole contribution. `connection` is the package's own
 * snapshot, passed through untouched. `endpoints` is where this host mounted the four declared
 * `#[ParticleOp]`s — resolved from the route table by name, never composed as a string — because
 * `Route::splicewirePlatformConnectionRoutes()` takes a prefix, so the package cannot know its own
 * URL and the packaged screen must be told rather than guess.
 *
 * Nothing is `Lazy` here, unlike `OperatorDashboardPageData` beside it: the panel renders every
 * field on first paint (the state badge, the platform URL, the identity), so deferring any of them
 * would buy a second round trip for a screen whose entire content is one small object.
 */
#[TypeScript]
final class PlatformConnectionPageData extends Data
{
    public function __construct(
        public PlatformConnectionData $connection,
        public PlatformConnectionEndpointsData $endpoints,
    ) {}
}
