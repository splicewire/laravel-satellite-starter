<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agreement ledger's two tables, in exactly the shape rushing/laravel-agreements' tests/TestCase.php carries
 * (the mechanism ships no DDL; this seam is the host's copy of it). Morph ids are strings so integer and UUID keys
 * both fit. Shared: beam-tenancy applies `shared/` to the central and tenant schemas (where a ledger lives under
 * tenancy is agreements ticket 13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('agreement_key');
            $table->string('kind');
            $table->string('title');
            $table->longText('content');
            $table->char('digest', 64);
            $table->string('label');
            $table->timestamp('effective_at');
            $table->boolean('material');
            $table->timestamp('frozen_at');

            $table->unique(['agreement_key', 'digest']);
            $table->index(['agreement_key', 'effective_at']);
        });

        Schema::create('agreement_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('agreement_version_id')->constrained('agreement_versions');
            $table->string('agreement_key');
            $table->string('act');
            $table->boolean('grants');
            $table->foreignUuid('revokes_entry_id')->nullable()->constrained('agreement_entries');
            $table->string('party_type')->nullable();
            $table->string('party_id')->nullable();
            $table->string('counterparty_contact')->nullable();
            $table->string('principal_type')->nullable();
            $table->string('principal_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('behavior')->nullable();
            $table->string('method');
            $table->string('source')->nullable();
            $table->string('channel')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['agreement_key', 'party_type', 'party_id']);
            $table->index(['agreement_key', 'counterparty_contact']);
            $table->index('revokes_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_entries');
        Schema::dropIfExists('agreement_versions');
    }
};
