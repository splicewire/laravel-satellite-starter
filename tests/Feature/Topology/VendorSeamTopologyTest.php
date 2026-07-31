<?php

declare(strict_types=1);

namespace Tests\Feature\Topology;

use Rushing\Graphine\Dto\Edge;
use Rushing\PackageTopology\Contract\TopologyContract;
use Rushing\PackageTopology\Sources\ComposerManifestGraphSource;
use Tests\TestCase;

/**
 * Vendor-seam structural guard (beam-write-pipeline ticket 02, DESIGN §4): two package-graph rules over
 * the installed composer manifests that stop the vendor seam from silently inverting.
 *
 *   R1 — the OPEN foundation (`schemastud/*`) never requires the PAID ENGINE.
 *   R2 — a beam package (`splicewire/laravel-beam*`) never requires a satellite (`splicewire/laravel-satellite*`).
 *
 * Why this is a SIBLING of {@see GroundingKernelTopologyTest} and not folded into its
 * {@see TopologyContract} fluent chain: these are estate-wide PREFIX
 * rules ("no `schemastud/*` may require the paid engine"), while the contract vocabulary states only
 * exact-package edges (`mustNotRequire('a', 'b')`). Expressing a prefix rule as the contract would mean
 * enumerating every installed pair, which would miss a violation introduced by a package added later.
 * So we reuse the substrate's manifest reader ({@see ComposerManifestGraphSource} — the SAME require
 * graph the fluent contract reads) and apply the prefix predicate at evaluation time, which keeps teeth
 * against packages that don't exist yet.
 *
 * THE BEAM CARVE-OUT IS LOAD-BEARING (not a fudge). ADR-0092 moved the beam family from the `schemastud`
 * vendor to `splicewire/*` as Splicewire's FREE-TIER ARM; ADR-0138 wired `schemastud/laravel-frame` to
 * depend DOWN on `splicewire/laravel-beam` (the reviewed frame → beam → data-schemas diamond). The
 * Scriptorium `a-socket-lives-with-its-surface` apocryphon states the governing fact: *vendor-namespace
 * does not imply layer-height* — beam is `splicewire`-named yet structurally a base primitive UNDER the
 * open foundation. So R1 forbids `schemastud/* → the paid engine`, and the free-tier beam base is
 * explicitly NOT the paid engine — `schemastud/laravel-frame → splicewire/laravel-beam` is sanctioned,
 * asserted below as a positive control. This is the package-GRAPH axis; the source-import axis lives in
 * Architecture/EngineSpineDependencyDirectionTest.
 */
final class VendorSeamTopologyTest extends TestCase
{
    public function test_no_open_foundation_package_requires_the_paid_engine(): void
    {
        $violations = $this->vendorSeamViolations($this->realEdges());

        $this->assertSame([], $violations, "Vendor-seam contract violated:\n - ".implode("\n - ", $violations));
    }

    public function test_the_sanctioned_down_dependencies_are_not_flagged(): void
    {
        $edges = $this->edgeSet($this->realEdges());

        // frame → beam: the ADR-0138 diamond, a schemastud → splicewire edge that R1 must NOT flag
        // (beam is the free-tier base, not the paid engine).
        $this->assertContains(
            'schemastud/laravel-frame => splicewire/laravel-beam',
            $edges,
            'expected the sanctioned frame → beam diamond edge to be present in the installed tree',
        );

        // beam → data-schemas: a splicewire → schemastud down-dependency (DESIGN §4 "allowed"); neither
        // rule targets it. Confirm it is present and not among the violations.
        $this->assertContains(
            'splicewire/laravel-beam => schemastud/laravel-data-schemas',
            $edges,
            'expected the sanctioned beam → data-schemas down-dependency to be present',
        );

        $this->assertSame([], $this->vendorSeamViolations($this->realEdges()));
    }

    public function test_the_rules_have_teeth_against_planted_violations(): void
    {
        $vendor = $this->syntheticVendorTree([
            // R1 violation: an open-foundation package reaching UP into a paid-engine package.
            'schemastud/laravel-open-thing' => ['splicewire/laravel-composition-engine' => '^1.0'],
            // R2 violation: a beam package reaching sideways/up into a satellite package.
            'splicewire/laravel-beam-badness' => ['splicewire/laravel-satellite-secret-sauce' => '^1.0'],
            // Sanctioned: schemastud → beam base (the diamond) must stay CLEAN even in the synthetic tree.
            'schemastud/laravel-frame-clone' => ['splicewire/laravel-beam' => '^1.0'],
            // Sanctioned: beam → data-schemas down-dependency stays clean.
            'splicewire/laravel-beam' => ['schemastud/laravel-data-schemas' => '^1.0'],
            // Endpoints referenced above so they resolve in-scope.
            'splicewire/laravel-composition-engine' => [],
            'splicewire/laravel-satellite-secret-sauce' => [],
            'schemastud/laravel-data-schemas' => [],
        ]);

        $source = new ComposerManifestGraphSource($vendor, ['schemastud/*', 'splicewire/*']);
        $violations = $this->vendorSeamViolations($source->edges());

        $this->assertContains(
            'R1: schemastud/laravel-open-thing requires paid-engine splicewire/laravel-composition-engine',
            $violations,
            'R1 must catch an open-foundation → paid-engine require',
        );
        $this->assertContains(
            'R2: splicewire/laravel-beam-badness requires satellite splicewire/laravel-satellite-secret-sauce',
            $violations,
            'R2 must catch a beam → satellite require',
        );
        $this->assertCount(2, $violations, "the planted tree must produce exactly the two planted violations, got:\n - ".implode("\n - ", $violations));
    }

    /**
     * Apply the two prefix rules to a require-edge stream.
     *
     * @param  iterable<Edge>  $edges
     * @return list<string> human-readable violation lines (empty = clean)
     */
    private function vendorSeamViolations(iterable $edges): array
    {
        $violations = [];

        foreach ($edges as $edge) {
            $from = $edge->from->value;
            $to = $edge->to->value;

            // R1 — open foundation (schemastud/*) never requires the paid engine. The free-tier beam
            // base (splicewire/laravel-beam*) is NOT the paid engine — the ADR-0138 diamond is allowed.
            if (str_starts_with($from, 'schemastud/')
                && str_starts_with($to, 'splicewire/')
                && ! $this->isBeamFamily($to)) {
                $violations[] = "R1: {$from} requires paid-engine {$to}";
            }

            // R2 — a beam package never requires a satellite package (beam = shell, satellite = sauce).
            if ($this->isBeamFamily($from) && str_starts_with($to, 'splicewire/laravel-satellite')) {
                $violations[] = "R2: {$from} requires satellite {$to}";
            }
        }

        sort($violations);

        return $violations;
    }

    private function isBeamFamily(string $package): bool
    {
        return str_starts_with($package, 'splicewire/laravel-beam');
    }

    /** @return iterable<Edge> */
    private function realEdges(): iterable
    {
        return (new ComposerManifestGraphSource(base_path('vendor'), ['schemastud/*', 'splicewire/*']))->edges();
    }

    /**
     * @param  iterable<Edge>  $edges
     * @return list<string> "from => to" edge labels
     */
    private function edgeSet(iterable $edges): array
    {
        $labels = [];
        foreach ($edges as $edge) {
            $labels[] = "{$edge->from->value} => {$edge->to->value}";
        }

        return $labels;
    }

    /**
     * Materialise a throwaway `vendor/`-shaped tree of composer manifests so the SAME manifest reader
     * the real assertion uses can be pointed at planted violations.
     *
     * @param  array<string,array<string,string>>  $packages  name => (require map)
     */
    private function syntheticVendorTree(array $packages): string
    {
        $root = sys_get_temp_dir().'/vendor-seam-topology-'.substr(md5(implode('|', array_keys($packages))), 0, 12);

        foreach ($packages as $name => $require) {
            $dir = "{$root}/{$name}";
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents(
                "{$dir}/composer.json",
                json_encode(['name' => $name, 'require' => (object) $require], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );
        }

        return $root;
    }
}
