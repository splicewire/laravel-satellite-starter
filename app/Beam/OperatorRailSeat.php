<?php

namespace App\Beam;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Splicewire\Beam\Nav\NavSection;
use Splicewire\Beam\Nav\NavSectionRegistry;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Realm\RealmRegistry as Realms;
use Splicewire\Beam\Ux\Nav\NavSource;

/**
 * The OPERATOR realm's rail seat — the one section `/operator/frame/manifest` projects at this host.
 *
 * ## Why the host seats it, and not a package
 *
 * `@splicewire/beam-inertia` renders every `operator/*` page with a rail read from the operator realm's
 * manifest. Measured 2026-09-14 at this host and at the satellite starter: that rail showed Dashboard
 * and nothing else, because the manifest's `nav.items` was `[]`. The realm places `users` and `teams`
 * (`config/frame.php`), and neither declares a `section:`; the seats that DO reach this realm
 * (beam-ux's `authoring`/`ops`, beam-calendars' `calendars`) attach nothing here, so
 * `FrameNavContribution` drops them as empty. `splicewire/laravel-beam-accounts` seats no section on
 * purpose (its `tests/NavSectionTest.php` records why), so no package can fill this.
 *
 * Which operator surfaces exist is a fact only this host knows — it is the party that mounted them —
 * and api-surface-coherence 142's rule for that case is *"a list is the honest form"*. The list is not
 * written a third time here, though: both halves are READ from the two host lists that already say it.
 *
 *  1. **The realm's resources** — `config('frame.realms.operator')`, through
 *     {@see ParticleResourceRegistry::keysForRealm()}. These are the same leaves the operator console
 *     mount in `routes/web.php` derives its segments from, so a resource added to the realm list gains
 *     its console route AND its rail row with no second edit. Each row carries the resource's list
 *     `routeName`, which `FrameResourcesInvocable` joins back to the leaf href at request time and
 *     `FrameNavContribution::keepBound()` keeps because the realm's routeContext provides it.
 *  2. **The realm's bespoke pages** — the `realm: operator` rows of `resources/beam-ux/nav.yml`, the
 *     file whose header already declares *"operator STAFF — its own sitemap, staff-gated"*. A row at the
 *     realm's own base (`/operator`) is skipped: that is the rail's Dashboard item and this seat's own
 *     header. These rows carry no `routeName`, because they are not frame leaves and a name the
 *     routeContext cannot bind would be pruned; a nav.yml row is the host's statement that the page is
 *     mounted, and the manifest feature test holds each href to a matching route.
 *
 * ⚠️ This is NOT the account rail's `operator-seat` row in nav.yml — that is the door INTO the realm
 * from `<AccountShell>`. This is the navigation once inside it.
 *
 * ## Gated like the realm, and the gate is not the boundary
 *
 * `entitlement: ['os.operate']` — the key `beam.core.realm_gates` names for this realm and that
 * `/operator` and `/operator/frame/manifest` are routed behind. The manifest route already refuses a
 * principal without it, so the seat's gate is the declaration of intent rather than the lock.
 *
 * ## Registered once the application has booted
 *
 * The rows are computed when the seat is registered, and the resource half needs beam's resource
 * discovery to have run. `booted()` is after every provider's `boot()`, whatever order they ran in.
 * Every read is guarded: a host without beam-ux's nav source or a registry seats nothing rather than
 * fataling a boot, and a failed derivation seats nothing rather than half a rail.
 */
final class OperatorRailSeat
{
    public const REALM = 'operator';

    public const KEY = 'platform';

    public static function register(Application $app): void
    {
        if (! $app->bound(NavSectionRegistry::class)) {
            return;
        }

        $rows = rescue(fn (): array => self::rows($app), [], false);

        if ($rows === []) {
            return;
        }

        $app->make(NavSectionRegistry::class)->register(
            new NavSection(
                key: self::KEY,
                realm: self::REALM,
                label: 'Platform',
                icon: 'Server',
                href: self::base($app),
                // Ahead of beam-ux's `authoring` (30): this is the realm's own management surface.
                order: 10,
                entitlement: ['os.operate'],
                permission: null,
                static: $rows,
            ),
            by: 'app',
        );
    }

    /**
     * The seat's child rows: the realm's resources first, then its bespoke nav.yml pages, each in the
     * order its own list gives.
     *
     * @return list<array{title: string, href: string, icon?: string, routeName?: string}>
     */
    public static function rows(Application $app): array
    {
        $base = self::base($app);
        $rows = [];

        $resources = $app->make(ResourceRegistry::class);

        foreach ($app->make(ParticleResourceRegistry::class)->keysForRealm(self::REALM) as $key) {
            $definition = $resources->find($key);

            if ($definition === null) {
                continue;
            }

            $row = [
                'title' => $definition->nav->label,
                // The fallback only: `FrameResourcesInvocable` re-joins it to the leaf `routeName` names.
                'href' => $base.'/'.$key,
                'routeName' => $definition->nav->routeName ?? $key.'.index',
            ];

            if ($definition->nav->icon !== null && $definition->nav->icon !== '') {
                $row['icon'] = $definition->nav->icon;
            }

            $rows[] = $row;
        }

        $source = $app->make(NavSource::class);

        // Only an AUTHORED nav (config or file). The derived fallback queries the entries table, which
        // is neither a boot-time read nor a list this host wrote.
        if (! $source->isDerived('pages')) {
            foreach ($source->resolve('pages') as $row) {
                if ($row['realm'] !== self::REALM || $row['segment'] === null) {
                    continue;
                }

                $href = '/'.trim($row['segment'], '/');

                if ($href === $base) {
                    continue;
                }

                $rows[] = [
                    'title' => $row['title'] ?? Str::headline($row['slug']),
                    'href' => $href,
                ];
            }
        }

        return $rows;
    }

    private static function base(Application $app): string
    {
        return rtrim($app->make(Realms::class)->tryResolve(self::REALM)->routeBase ?? '/'.self::REALM, '/');
    }
}
