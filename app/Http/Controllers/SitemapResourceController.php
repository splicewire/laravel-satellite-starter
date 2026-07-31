<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Schemastud\Frame\Registry\AdminResourceRegistry;

/**
 * The host-owned Frame resource page for the editable sitemap (kind A). Frame ships only
 * `frame/manifest` (data); the HOST wires the edit route/page for each resource. This binds
 * the `sitemap` resource under `frame/resources/sitemap` (route name `frame.resources.sitemap`,
 * matching SitemapData's #[AdminResource(routeName:)]) and hands the manifest definition to the
 * Inertia editor shell.
 *
 * The live edit→nav-update flow renders in the React/Inertia EditShell (needs the starter's
 * Vite dev server); this controller supplies the PHP-side props (the resource definition from
 * the registry) so the route + wiring are exercisable headlessly.
 */
class SitemapResourceController extends Controller
{
    public function __invoke(AdminResourceRegistry $registry): Response
    {
        return Inertia::render('frame/resource', [
            'resource' => $registry->get('sitemap'),
        ]);
    }
}
