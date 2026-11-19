<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint as B;
use Illuminate\Support\Facades\Schema;

/**
 * Email verification (7.2) and trial dates (7.5).
 *
 * `tenants.activated_at` NULL means "registered but not verified" — a third
 * state alongside the existing active/suspended boolean, which is why it is
 * a separate column rather than another `status` value: changing `status`
 * to a string would break `isActive()`, the super panel and every existing
 * row at once.
 *
 * EXISTING TENANTS ARE BACKFILLED as activated. They were created before
 * verification existed, by a super admin or by registration, and locking
 * them out on deploy would be a far worse bug than the one this prevents.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('status');
            $table->timestamp('trial_ends_at')->nullable()->after('activated_at');
        });

        // Backfill: everything that already exists was vouched for.
        DB::table('tenants')->whereNull('activated_at')->update([
            'activated_at' => DB::raw('created_at'),
        ]);

        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => DB::raw('created_at'),
        ]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['activated_at', 'trial_ends_at']);
        });
    }
};
