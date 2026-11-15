<?php

namespace App\Analytics;

use Splicewire\Beam\Analytics\Contracts\AnalyticsDestinations;
use Splicewire\Beam\Analytics\Destinations\AnalyticsDestination;
use Splicewire\Beam\Analytics\Scope\AnalyticsScope;

/**
 * Where the analytics figures lead on this host (analytics-area 18 AMEND; root's ruling (1), 2026-11-13). The analytics package
 * names its destinations and leaves the routes to the host (ADR-0242), so this host answers with its own pages.
 *
 * This starter has ONE of them, the site editor: the front door `/` (route `home`, mounted by `Route::beamUxHome('site/home')`
 * in routes/web.php), the packaged home page the in-place visual editor addresses, whose editor operations (EditorRoutes, behind
 * auth + verified) each declare `ux.author`. That is a central page, so it answers on the install side only. This host has no
 * Site settings page and no tenant-side editor, so every other name, and tenant scope, answers nothing and no link draws there.
 *
 * The answer names the opening ability; the package's DestinationLinks draws the link only for a viewer who passes it, and
 * never decides who sees the area.
 */
class StarterAnalyticsDestinations implements AnalyticsDestinations
{
    public function destination(string $name, AnalyticsScope $scope): ?AnalyticsDestination
    {
        if ($name !== AnalyticsDestinations::SITE_EDITOR || ! $scope->isInstall()) {
            return null;
        }

        return new AnalyticsDestination(routeName: 'home', ability: 'ux.author');
    }
}
