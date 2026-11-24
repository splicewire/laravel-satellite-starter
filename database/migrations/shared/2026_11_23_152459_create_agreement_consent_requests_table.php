<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * agreements 12: a consent request, a counterparty's consent asked for by a signed-in person, about one subject. It is
 * the seam's own record with its own status (requested, then granted or declined; granted, then withdrawn); each
 * transition appends a ledger entry. Shared, like the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_consent_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('agreement_key');
            $table->foreignUuid('agreement_version_id')->constrained('agreement_versions');
            $table->string('requester_type');
            $table->string('requester_id');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('counterparty_contact');
            $table->string('status');
            $table->foreignUuid('grant_entry_id')->nullable()->constrained('agreement_entries');
            // The ledger entry the last status move appended (agreements 12 amend): no status without its entry.
            $table->foreignUuid('last_entry_id')->nullable()->constrained('agreement_entries');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();

            $table->index(['requester_type', 'requester_id']);
            $table->index(['agreement_key', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_consent_requests');
    }
};
