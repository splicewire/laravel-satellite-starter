<?php

namespace App\Beam;

use Illuminate\Contracts\Foundation\Application;
use Splicewire\Beam\Nav\NavAudience;
use Splicewire\Beam\Nav\NavSection;
use Splicewire\Beam\Nav\NavSectionRegistry;

/**
 * This host's operator pages, seated in the operator task sections (ux-walkthrough UX-09 C, IA-10). It replaced
 * `OperatorRailSeat`, which read the `realm: operator` rows of nav.yml into a host 'platform' seat.
 *
 * One page: the satellite's client half of the pairing with a tower, Splicewire connection (UX-04), in System
 * (lead 13:28Z, option a). No package declares `operator-system` here (tower does, at the hub), so this seat is the
 * section's header, at System's IA-10 place. Where a package does declare the key, laravel-beam merges a same-key,
 * same-gate seat into it, and the lowest order supplies the header.
 *
 * Open gates on purpose: the operator realm's route gate (`os.operate`) is the lock, and the screen's route and every
 * operation carry it too.
 */
class OperatorPages
{
    /** System's place among the operator task sections (IA-10: Tenants 10, People 20, … System 60). */
    private const ORDER = 60;

    /** [section key, label, icon, audience, rows of [title, path under /operator, icon, navOrder]]. */
    private const SEATS = [
        ['operator-system', 'System', 'Server', NavAudience::Product, [['Splicewire connection', 'platform-connection', 'link', 1]]],
    ];

    /** @return list<string> every page this host seats, as a rail href */
    public static function hrefs(): array
    {
        $out = [];
        foreach (self::SEATS as [, , , , $rows]) {
            foreach ($rows as $row) {
                $out[] = '/operator/'.$row[1];
            }
        }

        return $out;
    }

    public static function register(Application $app): void
    {
        if (! $app->bound(NavSectionRegistry::class)) {
            return;
        }

        foreach (self::SEATS as [$key, $label, $icon, $audience, $rows]) {
            $app->make(NavSectionRegistry::class)->register(new NavSection(
                key: $key, realm: 'operator', label: $label, icon: $icon, href: '/'.$key, order: self::ORDER,
                entitlement: null, permission: null, audience: $audience,
                static: array_map(fn (array $row): array => [
                    'title' => $row[0], 'href' => '/operator/'.$row[1], 'icon' => $row[2], 'navOrder' => $row[3],
                ], $rows),
            ), by: 'app');
        }
    }
}
