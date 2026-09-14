<?php

declare(strict_types=1);

namespace Tests\Feature;

use Rushing\Doctor\DoctorStatus;
use Splicewire\Beam\Surgeon\SchemaProjectionDriftAudit;
use Tests\TestCase;

/**
 * `resources/schemas/App/**` is a COMMITTED generated artifact — beam's particle doctrine says so
 * ("schemas:generate → resources/schemas (JSON Schema, committed)"), and `.prettierignore` already
 * carries it as "PHP Data generator output". Until now the tracking was simply never done, so a fresh
 * clone had no projections at all and nothing said a word.
 *
 * This is the verification half of docs/conventions/regenerating-committed-artifacts.md — *regeneration
 * is a command, verification is a test, the test never writes*. It writes nothing; `schemas:generate`
 * is the command, and the audit already names it in its own failure text.
 *
 * ⚠️ It drives {@see SchemaProjectionDriftAudit} rather than re-deriving the expected schema. That is
 * deliberate and the audit's own docblock says why: a second hand-rolled copy of the generation logic
 * DISAGREED with the real command and cost a host a permanent phantom "stale" finding no regeneration
 * could clear. One implementation, driven from a test — never a second one to compare against.
 */
class SchemaArtifactDriftTest extends TestCase
{
    public function test_every_declared_data_class_has_a_committed_schema_projection(): void
    {
        $findings = SchemaProjectionDriftAudit::forApp()->run();

        // Drift is reported one finding per offending class, so the clean shape is exactly one summary.
        // Print the details on failure — "expected 1, got 3" alone would not say WHICH class drifted.
        $this->assertCount(
            1,
            $findings,
            'schema projections are missing or stale: '
                .implode(' | ', array_map(fn ($f): string => $f->detail, $findings))
        );

        $finding = $findings[0];

        $this->assertSame(DoctorStatus::Pass, $finding->status, $finding->detail);

        // ⚠️ Both of the audit's vacuous outcomes report Pass, and one of them reports it CONCLUSIVELY:
        //   - an empty population  → Finding::inconclusive(), Pass + conclusive:false
        //   - an unavailable check → Finding::pass('... — schema-projection drift check skipped.')
        // So asserting Pass proves nothing on its own. The conclusive flag rejects the first, and
        // pinning the success detail's exact shape rejects the second — a "skipped" pass cannot match.
        $this->assertTrue($finding->conclusive, $finding->detail);
        $this->assertMatchesRegularExpression(
            '/^\d+ declared Data class\(es\) have fresh disk schema projections\.$/',
            $finding->detail
        );

        // ...and the population must be non-empty, so a scope regression that silently stops seeing
        // app/Data cannot pass as "clean".
        preg_match('/^(\d+) /', $finding->detail, $m);
        $this->assertGreaterThanOrEqual(1, (int) $m[1], $finding->detail);
    }

    /**
     * Regenerate into a throwaway directory and compare it, byte for byte, against the committed tree.
     *
     * Ported from laravel-beam-starter's recovery patch (5cd7667), where it replaced the structural
     * existence check. The audit above sees only DECLARED particle classes, so on its own it left every
     * other projection unguarded against STALENESS. Comparing a real regeneration closes four holes:
     * a class with no committed projection (missing), a projection whose class is gone (orphaned), a
     * stale projection and an empty/corrupt one (both: same path, different bytes).
     *
     * It drives the REAL command through its `--output` override rather than re-deriving a schema, and
     * writes only to a throwaway directory, never to the tracked tree.
     *
     * ⚠️ `--output` re-points `filesystems.disks.<schema disk>.root` and forgets the resolved disk. The
     * audit reads through that SAME disk, so a leaked redirect would make it compare the throwaway
     * directory against itself. The `finally` restores both.
     *
     * Environment note: committed projections are generated with SCHEMA_BASE_URI unset.
     *
     * Gate ownership: in CI, `composer setup` regenerates resources/schemas before this test runs, so the
     * `stale` and `missing` buckets cannot fail there; the tests.yml clean-tree step owns staleness in CI.
     * This test owns `orphaned` everywhere, and `stale`/`missing` in a local `composer ci:check` run without setup.
     */
    public function test_regenerates_to_exactly_the_committed_schema_projections(): void
    {
        $snapshot = function (string $root): array {
            if (! is_dir($root)) {
                return [];
            }

            $files = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (str_ends_with($file->getPathname(), '.schema.json')) {
                    $files[substr($file->getPathname(), strlen($root) + 1)] = (string) file_get_contents($file->getPathname());
                }
            }
            ksort($files);

            return $files;
        };

        $config = config('data-schemas');
        $disk = \Schemastud\DataSchemas\Support\SchemaDisk::name($config);
        $originalRoot = config("filesystems.disks.{$disk}.root");
        $tmp = sys_get_temp_dir().'/schema-regen-'.bin2hex(random_bytes(6));

        try {
            $exit = \Illuminate\Support\Facades\Artisan::call('schemas:generate', ['--output' => $tmp]);
            $this->assertSame(0, $exit, \Illuminate\Support\Facades\Artisan::output());

            $generated = $snapshot($tmp.'/App');
            $committed = $snapshot(resource_path('schemas/App'));

            // Guard the instrument: an empty regeneration would make every comparison below vacuous.
            $this->assertNotEmpty($generated, 'regeneration emitted nothing, so the comparison did not run');

            $missing = array_values(array_diff(array_keys($generated), array_keys($committed)));
            $orphaned = array_values(array_diff(array_keys($committed), array_keys($generated)));
            $stale = array_values(array_filter(
                array_keys(array_intersect_key($generated, $committed)),
                fn (string $path): bool => $generated[$path] !== $committed[$path],
            ));

            $this->assertSame(
                ['missing' => [], 'orphaned' => [], 'stale' => []],
                ['missing' => $missing, 'orphaned' => $orphaned, 'stale' => $stale],
                'committed schema projections do not match a fresh regeneration — run `php artisan schemas:generate`',
            );
        } finally {
            config(['data-schemas' => $config, "filesystems.disks.{$disk}.root" => $originalRoot]);
            \Illuminate\Support\Facades\Storage::forgetDisk($disk);
            \Illuminate\Support\Facades\File::deleteDirectory($tmp);
        }
    }
}
