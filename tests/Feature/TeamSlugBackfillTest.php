<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * `add_slug_to_teams_table` backfills a slug per team, deriving a personal team's from its
 * OWNER's handle. It reads the team as a raw query-builder row, and every column read on that
 * row is guarded (`$team->personal_team ?? false`, `$team->name ?? ''`) — except, once,
 * `$team->user_id`, which was handed straight to `where('id', …)`.
 *
 * A personal team whose owner column is null or absent therefore issued `where id = null`: a
 * query that matches nothing everywhere and errors on some drivers, and an undefined-property
 * access when the column is missing outright. This pins the guard.
 */
class TeamSlugBackfillTest extends TestCase
{
    // RefreshDatabase is load-bearing, not habit: without a migrated `users` table the guarded
    // and unguarded readings are indistinguishable — Schema::hasTable() is false either way and
    // neither issues the lookup, so the mutation test below passes against the defect.
    use RefreshDatabase;

    public function test_a_personal_team_with_no_owner_id_falls_back_to_its_own_name(): void
    {
        $this->assertSame(
            'Acme',
            $this->source((object) ['personal_team' => true, 'user_id' => null, 'name' => 'Acme']),
        );
    }

    public function test_a_personal_team_with_no_owner_id_issues_no_owner_lookup(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->source((object) ['personal_team' => true, 'user_id' => null, 'name' => 'Acme']);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // The guard short-circuits before Schema::hasTable() and before the users lookup, so a
        // null owner costs no round trip at all.
        $this->assertSame([], $queries, 'a null owner id must not reach the database');
    }

    /**
     * Invoke the migration's private `source()` — the projection under test — on a raw row.
     */
    private function source(object $team): string
    {
        $migration = require database_path('migrations/shared/2026_08_27_120000_add_slug_to_teams_table.php');

        $method = new ReflectionMethod($migration, 'source');
        $method->setAccessible(true);

        return $method->invoke($migration, $team);
    }
}
