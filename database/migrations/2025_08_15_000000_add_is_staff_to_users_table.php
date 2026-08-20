<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The staff signal the DefaultEntitlementResolver (splicewire/laravel-beam-accounts) reads: a truthy
 * `is_staff` marks a principal staff, resolving the staff bundle (author-ux/os.enter/app-operator) so the
 * operator (/operator) and OS-shell (/os) realms + gates open. The cheapest host-owned convention — no
 * spatie roles required in the starter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_staff')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_staff');
        });
    }
};
