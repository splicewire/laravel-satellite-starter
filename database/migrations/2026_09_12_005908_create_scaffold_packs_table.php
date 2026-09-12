<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rushing\SchemaConvergence\ConvergentTable;
use Splicewire\Satellite\Support\SatelliteTenancy;

/**
 * Scaffold Packs. Shipped as a publish-only spatie/laravel-package-tools stub (`runsMigrations` FALSE):
 * the package publishes this timestamp-less `.php.stub` via `configurePackage()`'s
 * `->hasMigrations([...])` (`vendor:publish --tag=satellite-migrations`), which re-stamps + sequences it
 * into the host's `database/migrations/` at install time; the host's `migrate` pass then runs it. The
 * satellite package never `loadMigrationsFrom`'s it at runtime.
 *
 * Squashed pre-prod (no deployed data to preserve migration history for) from the original create plus
 * two additive ALTERs (`add_tenant_sync_id_to_scaffold_packs`, `add_kind_to_scaffold_packs`) and one
 * column drop (`drop_scaffold_pack_scope_columns`, which removed `content_filter`/`silo_template`/
 * `context_scopes`/`assistants` — omitted below rather than added-then-dropped) into one clean create —
 * see git history for the per-column rationale if it's ever needed again.
 *
 * CENTRAL-only table (no tenant twin): a single-DB satellite resolves `SatelliteTenancy::centralConnection()`
 * to its default connection; a real multi-tenant host resolves its declared central connection. There is
 * no tenant estate for this table, so it ships as one stub, not a flat + `tenant/` pair.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // scaffold_packs is a CENTRAL-estate table — created on the resolved central connection so the
        // create and the later ALTERs all target the same estate (single-DB: the default connection).
        ConvergentTable::named('scaffold_packs')
            ->on(SatelliteTenancy::centralConnection())
            ->define(function (Blueprint $table) {
                $table->uuid('id')->primary();

                // Cross-cut breadcrumb (from add_tenant_sync_id_to_scaffold_packs). Names a row in
                // `tenant_syncs`, a TOWER-tier table (`Splicewire\Tower\Models\TenantSync`) that no package
                // ships a create for and that a satellite host does not have at all — so this is an
                // unconstrained, indexed column, NOT a foreign key. A published stub is stamped at install
                // time and cannot order itself behind a host's committed create, and a FK to a table the
                // cascade does not guarantee killed a fresh `splicewire:tower:install` on
                // `relation "tenant_syncs" does not exist` (beam-docs-satellite 25). Same shape as
                // `device_authorization_grants.issued_token_id`. Where the column's real home is (a
                // tower-tier guarded ALTER) is splicewire-recohere T12's call.
                $table->uuid('tenant_sync_id')->nullable()->after('id')->index();

                $table->string('slug')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('vertical')->nullable(); // maps to docs/verticals/

                // A first-class `kind` (`demo`|`scaffold`) on Scaffold Packs — the version-seeding branch
                // point (record-versioning ticket 09). Non-null, defaults to `scaffold` so existing packs
                // (and any future insert that omits it) never suddenly replay history.
                $table->string('kind')->default('scaffold')->after('vertical');

                $table->string('source_tenant_id')->nullable(); // tenant to clone content from; null ⇒ the designated system tenant (Tenant::system()) resolved at apply time
                $table->timestamps();
            })
            ->assert();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(SatelliteTenancy::centralConnection())->dropIfExists('scaffold_packs');
    }
};
