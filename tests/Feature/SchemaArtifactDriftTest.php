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
}
