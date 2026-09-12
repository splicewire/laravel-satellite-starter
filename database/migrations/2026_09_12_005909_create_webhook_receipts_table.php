<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rushing\SchemaConvergence\ConvergentTable;

/**
 * The inbound webhook spine's dedup/audit store. Shipped as a publish-only spatie/laravel-package-tools
 * stub (`runsMigrations` FALSE): the package publishes this timestamp-less `.php.stub` via
 * `configurePackage()`'s `->hasMigrations([...])` (`vendor:publish --tag=satellite-migrations`), which
 * re-stamps + sequences it into the host's `database/migrations/` at install time; the host's `migrate`
 * pass then runs it. The satellite package never `loadMigrationsFrom`'s it at runtime.
 *
 * CENTRAL-only table (no connection pin, no tenant twin) — `webhook_receipts` provisions on the default
 * connection, same as `scaffold_packs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        ConvergentTable::named('webhook_receipts')
            ->define(function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('provider');
                $table->string('event_id')->nullable();
                $table->string('event_type')->nullable();
                $table->string('party')->nullable();
                $table->json('payload')->nullable();
                $table->json('headers')->nullable();
                $table->string('status')->default('received');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                // Dedup key: a provider's own event id. Nulls stay distinct, so a
                // provider that sends no id is never collapsed. insertOrIgnore against
                // this index makes redelivery race-safe.
                $table->unique(['provider', 'event_id']);
            })
            ->assert();
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
    }
};
