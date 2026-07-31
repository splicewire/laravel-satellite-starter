<?php

declare(strict_types=1);

namespace Tests\Feature\Topology;

use Rushing\PackageTopology\Contract\TopologyContract;
use Rushing\PackageTopology\Testing\AssertsPackageTopology;
use Tests\TestCase;

/**
 * Satellite-tier structural stop-gap (package-graph axis). The satellite backend stack depends DOWN
 * on the beam foundation and the splicewire/client connector, and the whole in-scope estate stays a
 * DAG. Read off the installed composer manifests via rushing/laravel-package-topology, so a refactor
 * that inverts the tier (satellite reached UP into, or a cycle formed) fails here rather than silently.
 *
 * This is the tier ADDED on top of what the fork inherits from laravel-beam-starter — the
 * {@see VendorSeamTopologyTest} beam↔satellite prefix rules (R2: no beam package may require a
 * satellite package) now have real teeth because the satellite family is installed.
 */
final class SatelliteTierTopologyTest extends TestCase
{
    use AssertsPackageTopology;

    protected function vendorPath(): string
    {
        return base_path('vendor');
    }

    protected function topologyContract(): TopologyContract
    {
        return TopologyContract::for('satellite-tier-hierarchy')
            // The satellite stack depends DOWN on the beam base…
            ->mustRequire('splicewire/laravel-satellite', 'splicewire/laravel-beam')
            // …and on the connector it fronts.
            ->mustRequire('splicewire/laravel-satellite', 'splicewire/client')
            ->mustBeAcyclic(because: 'the package hierarchy must stay a DAG')
            ->build();
    }

    public function test_the_satellite_tier_hierarchy_holds(): void
    {
        $this->assertTopologyHolds();
    }
}
