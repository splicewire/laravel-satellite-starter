<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * agreements 09: a version carries the publisher's summary of what changed (rushing/laravel-agreements
 * `agreement_versions.summary`, nullable text; the mechanism writes it only when one is given). Additive and
 * idempotent, so an existing ledger gains it in place. Shared, like the ledger tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('agreement_versions', 'summary')) {
            return;
        }

        Schema::table('agreement_versions', function (Blueprint $table): void {
            $table->text('summary')->nullable()->after('material');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('agreement_versions', 'summary')) {
            Schema::table('agreement_versions', fn (Blueprint $table) => $table->dropColumn('summary'));
        }
    }
};
