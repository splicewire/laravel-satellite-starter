<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Illuminate\Support\Facades\Route;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One operator-realm surface, listed on the operator landing so it has a door.
 *
 * ⚠️ The operator realm has no navigation rail. `resources/beam-ux/nav.yml` declares its seats and
 * `splicewire:beam:ux:seed-nav` writes them into the operator sitemap, but the signed-in chrome is
 * the ACCOUNT realm's shell and nothing projects the operator sitemap — so an operator surface that
 * is not linked from `/operator` is reachable by typing its URL and by nothing else.
 *
 * This is the narrow fix rather than the rail: the host names its own operator surfaces (it is the
 * party that mounted them) and the packaged landing renders them. `mounted()` is what keeps it
 * honest — a surface whose route is not registered is omitted rather than offered as a link that
 * 404s, which is the failure this list exists to prevent for a package-owned screen a host might not
 * have mounted.
 */
#[TypeScript]
final class OperatorSurfaceData extends Data
{
    public function __construct(
        public string $title,
        public string $href,
        public ?string $blurb = null,
    ) {}

    /** @return list<self> */
    public static function forThisHost(): array
    {
        $surfaces = [];

        if (Route::has('operator.platform-connection') && PlatformConnectionEndpointsData::mounted()) {
            $surfaces[] = new self(
                title: 'Platform connection',
                href: route('operator.platform-connection', absolute: false),
                blurb: 'Pair this site with its Splicewire tower, and spend the pairing on a real capability read.',
            );
        }

        return $surfaces;
    }
}
